<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\HazardType;
use App\Models\HazardZone;
use App\Services\SyncService;
use Illuminate\Http\Request;

class HazardZoneController extends Controller
{
    // ── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user  = auth()->user();
        $query = HazardZone::with(['hazardType', 'barangay'])->latest();

        if ($user->isBarangayStaff()) {
            $query->where('barangay_id', $user->barangay_id);
        }

        if ($request->filled('hazard_type_id')) {
            $query->where('hazard_type_id', $request->hazard_type_id);
        }
        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->risk_level);
        }
        if ($request->filled('barangay_id') && ! $user->isBarangayStaff()) {
            $query->where('barangay_id', $request->barangay_id);
        }

        $zones = $query->paginate(25)->withQueryString();

        // Summary stats (scoped to user's barangay if staff)
        $statsBase = HazardZone::query();
        if ($user->isBarangayStaff()) {
            $statsBase->where('barangay_id', $user->barangay_id);
        }

        $highRiskLevels = ['High Susceptible', 'Prone',
            'PEIS VIII - Very destructive to devastating ground shaking'];

        $stats = [
            'total'       => (clone $statsBase)->count(),
            'high_risk'   => (clone $statsBase)->whereIn('risk_level', $highRiskLevels)->count(),
            'affected'    => (clone $statsBase)->sum('affected_population'),
            'total_area'  => (clone $statsBase)->sum('area_km2'),
        ];

        $hazardTypes = HazardType::orderBy('name')->get();
        $barangays   = Barangay::orderBy('name')->get();
        $riskLevels  = HazardZone::RISK_LEVELS;

        return view('hazards.index', compact('zones', 'hazardTypes', 'barangays', 'riskLevels', 'stats'));
    }

    // ── Create / Store ───────────────────────────────────────────────────────

    public function create()
    {
        $this->denyDivisionChief();
        $hazardTypes = HazardType::orderBy('name')->get();
        $barangays   = $this->scopedBarangays();

        return view('hazards.create', compact('hazardTypes', 'barangays'));
    }

    public function store(Request $request)
    {
        $this->denyDivisionChief();
        $validated = $this->validateZone($request);
        $this->checkScope($validated['barangay_id']);

        $zone = HazardZone::create($validated);

        // Compute affected_population via point-in-polygon on the drawn zone
        SyncService::syncHazardZones($zone->barangay_id);

        return redirect()->route('hazards.index')
            ->with('success', 'Hazard zone created successfully.');
    }

    // ── Show ─────────────────────────────────────────────────────────────────

    public function show(HazardZone $hazard)
    {
        $hazard->load(['hazardType', 'barangay']);
        return view('hazards.show', compact('hazard'));
    }

    // ── Edit / Update ────────────────────────────────────────────────────────

    public function edit(HazardZone $hazard)
    {
        $this->denyDivisionChief();
        $this->checkScope($hazard->barangay_id);
        $hazardTypes = HazardType::orderBy('name')->get();
        $barangays   = $this->scopedBarangays();

        return view('hazards.edit', compact('hazard', 'hazardTypes', 'barangays'));
    }

    public function update(Request $request, HazardZone $hazard)
    {
        $this->denyDivisionChief();
        $this->checkScope($hazard->barangay_id);
        $validated = $this->validateZone($request);
        $this->checkScope($validated['barangay_id']);

        $oldBarangayId = $hazard->barangay_id;
        $hazard->update($validated);

        // Recompute affected_population via PIP for the (possibly new) barangay
        SyncService::syncHazardZones($hazard->barangay_id);
        if ($oldBarangayId !== $hazard->barangay_id) {
            SyncService::syncHazardZones($oldBarangayId);
        }

        return redirect()->route('hazards.show', $hazard)
            ->with('success', 'Hazard zone updated successfully.');
    }

    // ── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(HazardZone $hazard)
    {
        $this->denyDivisionChief();
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }
        $hazard->delete();

        return redirect()->route('hazards.index')
            ->with('success', 'Hazard zone deleted.');
    }

    // ── GeoJSON API ──────────────────────────────────────────────────────────

    public function geojson(Request $request)
    {
        $query = HazardZone::with(['hazardType', 'barangay'])
            ->whereNotNull('coordinates');

        if ($request->filled('barangay_id')) {
            $query->where('barangay_id', $request->barangay_id);
        }
        if ($request->filled('hazard_type_id')) {
            $query->where('hazard_type_id', $request->hazard_type_id);
        }

        $features = $query->get()->map(function (HazardZone $zone) {
            $geometry = json_decode($zone->coordinates, true);
            if (! $geometry) {
                return null;
            }

            return [
                'type'       => 'Feature',
                'geometry'   => $geometry,
                'properties' => [
                    'id'                  => $zone->id,
                    'hazard_type'         => $zone->hazardType->name ?? '—',
                    'color'               => $zone->hazardType->color ?? '#e74c3c',
                    'icon'                => $zone->hazardType->icon ?? 'fa-triangle-exclamation',
                    'barangay'            => $zone->barangay->name ?? '—',
                    'risk_level'          => $zone->risk_level,
                    'badge_class'         => HazardZone::riskBadgeClass($zone->risk_level ?? ''),
                    'area_km2'            => $zone->area_km2,
                    'affected_population' => $zone->affected_population,
                    'description'         => $zone->description,
                ],
            ];
        })->filter()->values();

        return response()->json([
            'type'     => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function validateZone(Request $request): array
    {
        return $request->validate([
            'hazard_type_id' => 'required|exists:hazard_types,id',
            'barangay_id'    => 'required|exists:barangays,id',
            'risk_level'     => 'required|in:' . implode(',', HazardZone::RISK_LEVELS),
            'area_km2'       => 'nullable|numeric|min:0|max:9999.9999',
            'description'    => 'nullable|string|max:1000',
            'coordinates'    => 'nullable|string',
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
