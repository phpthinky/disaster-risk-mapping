<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\EvacuationCenter;
use Illuminate\Http\Request;

class EvacuationCenterController extends Controller
{
    // ── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user  = auth()->user();
        $query = EvacuationCenter::with('barangay')->latest();

        if ($user->isBarangayStaff()) {
            $query->where('barangay_id', $user->barangay_id);
        }

        if ($request->filled('barangay_id') && ! $user->isBarangayStaff()) {
            $query->where('barangay_id', $request->barangay_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $centers = $query->paginate(25)->withQueryString();

        $statsBase = EvacuationCenter::query();
        if ($user->isBarangayStaff()) {
            $statsBase->where('barangay_id', $user->barangay_id);
        }

        $stats = [
            'total'       => (clone $statsBase)->count(),
            'operational' => (clone $statsBase)->where('status', 'operational')->count(),
            'capacity'    => (clone $statsBase)->sum('capacity'),
            'occupancy'   => (clone $statsBase)->sum('current_occupancy'),
        ];

        $barangays = Barangay::orderBy('name')->get();

        return view('evacuations.index', compact('centers', 'stats', 'barangays'));
    }

    // ── Create / Store ───────────────────────────────────────────────────────

    public function create()
    {
        $this->denyDivisionChief();
        $barangays = $this->scopedBarangays();

        return view('evacuations.create', compact('barangays'));
    }

    public function store(Request $request)
    {
        $this->denyDivisionChief();
        $validated = $this->validateCenter($request);
        $this->checkScope($validated['barangay_id']);

        EvacuationCenter::create($validated);

        return redirect()->route('evacuations.index')
            ->with('success', 'Evacuation center created successfully.');
    }

    // ── Show ─────────────────────────────────────────────────────────────────

    public function show(EvacuationCenter $evacuation)
    {
        $evacuation->load('barangay');
        return view('evacuations.show', compact('evacuation'));
    }

    // ── Edit / Update ────────────────────────────────────────────────────────

    public function edit(EvacuationCenter $evacuation)
    {
        $this->denyDivisionChief();
        $this->checkScope($evacuation->barangay_id);
        $barangays = $this->scopedBarangays();

        return view('evacuations.edit', compact('evacuation', 'barangays'));
    }

    public function update(Request $request, EvacuationCenter $evacuation)
    {
        $this->denyDivisionChief();
        $this->checkScope($evacuation->barangay_id);
        $validated = $this->validateCenter($request, $evacuation);
        $this->checkScope($validated['barangay_id']);

        $evacuation->update($validated);

        return redirect()->route('evacuations.show', $evacuation)
            ->with('success', 'Evacuation center updated successfully.');
    }

    // ── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(EvacuationCenter $evacuation)
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        $evacuation->delete();

        return redirect()->route('evacuations.index')
            ->with('success', 'Evacuation center deleted.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function validateCenter(Request $request, ?EvacuationCenter $center = null): array
    {
        return $request->validate([
            'name'              => 'required|string|max:255',
            'barangay_id'       => 'required|exists:barangays,id',
            'capacity'          => 'required|integer|min:1',
            'current_occupancy' => 'required|integer|min:0',
            'latitude'          => 'nullable|numeric|between:-90,90',
            'longitude'         => 'nullable|numeric|between:-180,180',
            'facilities'        => 'nullable|string|max:1000',
            'contact_person'    => 'nullable|string|max:255',
            'contact_number'    => 'nullable|string|max:20',
            'status'            => 'required|in:operational,maintenance,closed',
        ]);
    }

    private function denyDivisionChief(): void
    {
        if (auth()->user()->isDivisionChief()) {
            abort(403, 'Division chief access is read-only.');
        }
    }

    private function checkScope(int $barangayId): void
    {
        $user = auth()->user();
        if ($user->isBarangayStaff() && $user->barangay_id !== $barangayId) {
            abort(403);
        }
    }

    private function scopedBarangays()
    {
        $user = auth()->user();
        if ($user->isBarangayStaff()) {
            return Barangay::where('id', $user->barangay_id)->get();
        }
        return Barangay::orderBy('name')->get();
    }
}
