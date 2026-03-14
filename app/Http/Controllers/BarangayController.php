<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\BarangayBoundaryLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BarangayController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'active']);
    }

    // ── Index ────────────────────────────────────────────────────────────────

    public function index(): View|RedirectResponse
    {
        $user = auth()->user();

        // Barangay staff go directly to their own barangay's show page
        if ($user->isBarangayStaff()) {
            return redirect()->route('barangays.show', $user->barangay_id);
        }

        $barangays = Barangay::withCount('households')
            ->with(['users' => fn($q) => $q->where('role', 'barangay_staff')->select('id', 'username', 'barangay_id')])
            ->orderBy('name')
            ->get();

        $summary = [
            'total'           => $barangays->count(),
            'with_boundary'   => $barangays->whereNotNull('boundary_geojson')->count(),
            'total_population'=> $barangays->sum('population'),
            'total_households'=> $barangays->sum('household_count'),
        ];

        return view('barangays.index', compact('barangays', 'summary'));
    }

    // ── Create / Store (admin only) ──────────────────────────────────────────

    public function create(): View
    {
        $this->authorize('create', Barangay::class);
        $staffUsers = User::where('role', 'barangay_staff')->orderBy('username')->get(['id', 'username', 'barangay_id']);
        return view('barangays.edit', ['barangay' => null, 'staffUsers' => $staffUsers]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Barangay::class);

        $data = $request->validate([
            'name'                => 'required|string|max:100|unique:barangays,name',
            'area_km2'            => 'nullable|numeric|min:0',
            'coordinates'         => 'nullable|string|max:255',
            'staff_user_id'       => 'nullable|exists:users,id',
            'boundary_geojson'    => 'nullable|json',
            'calculated_area_km2' => 'nullable|numeric|min:0',
        ]);

        $barangay = Barangay::create([
            'name'                => $data['name'],
            'area_km2'            => $data['area_km2'] ?? null,
            'coordinates'         => $data['coordinates'] ?? null,
            'boundary_geojson'    => $data['boundary_geojson'] ?? null,
            'calculated_area_km2' => $data['calculated_area_km2'] ?? null,
        ]);

        $this->assignStaff($barangay->id, $data['staff_user_id'] ?? null);

        if ($barangay->boundary_geojson) {
            BarangayBoundaryLog::create([
                'barangay_id' => $barangay->id,
                'action'      => 'created',
                'drawn_by'    => auth()->id(),
            ]);
        }

        return redirect()->route('barangays.index')
            ->with('success', "Barangay \"{$barangay->name}\" created successfully.");
    }

    // ── Show ─────────────────────────────────────────────────────────────────

    public function show(Barangay $barangay): View
    {
        $this->authorize('view', $barangay);

        $barangay->load([
            'hazardZones.hazardType',
            'populationData' => fn($q) => $q->latest()->limit(1),
            'evacuationCenters',
            'users'          => fn($q) => $q->where('role', 'barangay_staff'),
        ]);

        // Hazard type distribution
        $hazardStats = $barangay->hazardZones
            ->groupBy('hazard_type_id')
            ->map(fn($zones) => [
                'name'  => $zones->first()->hazardType->name ?? 'Unknown',
                'color' => $zones->first()->hazardType->color ?? '#888',
                'count' => $zones->count(),
            ])->values();

        return view('barangays.show', compact('barangay', 'hazardStats'));
    }

    // ── Edit / Update ────────────────────────────────────────────────────────

    public function edit(Barangay $barangay): View
    {
        $this->authorize('update', $barangay);
        $staffUsers = User::where('role', 'barangay_staff')->orderBy('username')->get(['id', 'username', 'barangay_id']);
        return view('barangays.edit', compact('barangay', 'staffUsers'));
    }

    public function update(Request $request, Barangay $barangay): RedirectResponse
    {
        $this->authorize('update', $barangay);

        $data = $request->validate([
            'name'                => "required|string|max:100|unique:barangays,name,{$barangay->id}",
            'area_km2'            => 'nullable|numeric|min:0',
            'coordinates'         => 'nullable|string|max:255',
            'staff_user_id'       => 'nullable|exists:users,id',
            'boundary_geojson'    => 'nullable|json',
            'calculated_area_km2' => 'nullable|numeric|min:0',
        ]);

        $hadBoundary  = (bool) $barangay->boundary_geojson;
        $newBoundary  = $data['boundary_geojson'] ?? null;

        $barangay->update([
            'name'                => $data['name'],
            'area_km2'            => $data['area_km2'] ?? $barangay->area_km2,
            'coordinates'         => $data['coordinates'] ?? $barangay->coordinates,
            'boundary_geojson'    => $newBoundary,
            'calculated_area_km2' => $data['calculated_area_km2'] ?? $barangay->calculated_area_km2,
        ]);

        if (auth()->user()->isAdmin()) {
            $this->assignStaff($barangay->id, $data['staff_user_id'] ?? null);
        }

        if ($newBoundary) {
            BarangayBoundaryLog::create([
                'barangay_id' => $barangay->id,
                'action'      => $hadBoundary ? 'updated' : 'created',
                'drawn_by'    => auth()->id(),
            ]);
        }

        return redirect()->route('barangays.show', $barangay)
            ->with('success', "Barangay \"{$barangay->name}\" updated successfully.");
    }

    // ── Destroy (admin only) ─────────────────────────────────────────────────

    public function destroy(Barangay $barangay): RedirectResponse
    {
        $this->authorize('delete', $barangay);

        if ($barangay->households()->exists()) {
            return back()->with('error', "Cannot delete: households are linked to \"{$barangay->name}\".");
        }

        // Unassign staff before deleting
        User::where('barangay_id', $barangay->id)->update(['barangay_id' => null]);

        $name = $barangay->name;
        $barangay->delete();

        return redirect()->route('barangays.index')
            ->with('success', "Barangay \"{$name}\" deleted successfully.");
    }

    // ── AJAX: delete boundary only ───────────────────────────────────────────

    public function deleteBoundary(Request $request, Barangay $barangay): JsonResponse
    {
        $this->authorize('update', $barangay);

        $barangay->update(['boundary_geojson' => null, 'calculated_area_km2' => null]);

        return response()->json(['ok' => true, 'msg' => 'Boundary removed.']);
    }

    // ── AJAX: all existing boundaries (for map overlays) ─────────────────────

    public function boundaries(): JsonResponse
    {
        $rows = Barangay::whereNotNull('boundary_geojson')
            ->where('boundary_geojson', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'boundary_geojson']);

        return response()->json($rows);
    }

    // ── AJAX: staff users for the assignment dropdown ─────────────────────────

    public function staffUsers(): JsonResponse
    {
        $this->authorize('create', Barangay::class);

        $users = User::where('role', 'barangay_staff')
            ->orderBy('username')
            ->get(['id', 'username', 'barangay_id']);

        return response()->json($users);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function assignStaff(int $barangayId, ?int $staffUserId): void
    {
        // Unassign previous staff for this barangay
        User::where('barangay_id', $barangayId)
            ->where('role', 'barangay_staff')
            ->update(['barangay_id' => null]);

        // Assign new staff
        if ($staffUserId) {
            User::where('id', $staffUserId)
                ->where('role', 'barangay_staff')
                ->update(['barangay_id' => $barangayId]);
        }
    }
}
