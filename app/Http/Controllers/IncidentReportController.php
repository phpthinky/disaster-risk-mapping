<?php

namespace App\Http\Controllers;

use App\Models\AffectedArea;
use App\Models\Barangay;
use App\Models\HazardType;
use App\Models\Household;
use App\Models\IncidentReport;
use App\Services\AffectedAreaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class IncidentReportController extends Controller
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = IncidentReport::with(['hazardType', 'reporter'])
            ->withSum('affectedAreas as total_affected', 'affected_population')
            ->latest('incident_date');

        // Barangay staff: only see incidents that have an affected area in their barangay
        $user = Auth::user();
        if ($user->isBarangayStaff()) {
            $query->whereHas('affectedAreas', fn ($q) => $q->where('barangay_id', $user->barangay_id));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(fn ($q) => $q->where('title', 'like', $search)
                ->orWhereHas('hazardType', fn ($q2) => $q2->where('name', 'like', $search)));
        }

        $incidents = $query->paginate(20)->withQueryString();

        $stats = [
            'total'    => IncidentReport::count(),
            'ongoing'  => IncidentReport::where('status', 'ongoing')->count(),
            'affected' => AffectedArea::sum('affected_population'),
        ];

        $hazardTypes = HazardType::orderBy('name')->get();

        return view('incidents.index', compact('incidents', 'stats', 'hazardTypes'));
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create()
    {
        $this->authorizeWrite();
        $hazardTypes = HazardType::orderBy('name')->get();
        return view('incidents.create', compact('hazardTypes'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $this->authorizeWrite();
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'hazard_type_id'   => 'required|exists:hazard_types,id',
            'incident_date'    => 'required|date',
            'status'           => 'required|in:ongoing,monitoring,resolved',
            'description'      => 'nullable|string',
            'affected_polygon' => 'nullable|string',
        ]);

        $validated['reported_by'] = Auth::id();

        DB::transaction(function () use ($validated, &$incident) {
            $incident = IncidentReport::create($validated);
            $this->recomputeAffectedAreas($incident);
        });

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'Incident report created and affected areas computed.');
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(IncidentReport $incident)
    {
        $incident->load(['hazardType', 'reporter', 'affectedAreas.barangay']);

        $totals = [
            'affected_households' => $incident->affectedAreas->sum('affected_households'),
            'affected_population' => $incident->affectedAreas->sum('affected_population'),
            'affected_pwd'        => $incident->affectedAreas->sum('affected_pwd'),
            'affected_seniors'    => $incident->affectedAreas->sum('affected_seniors'),
            'affected_infants'    => $incident->affectedAreas->sum('affected_infants'),
            'affected_minors'     => $incident->affectedAreas->sum('affected_minors'),
            'affected_pregnant'   => $incident->affectedAreas->sum('affected_pregnant'),
            'ip_count'            => $incident->affectedAreas->sum('ip_count'),
        ];

        return view('incidents.show', compact('incident', 'totals'));
    }

    // ── Edit ──────────────────────────────────────────────────────────────────

    public function edit(IncidentReport $incident)
    {
        $this->authorizeWrite();
        $hazardTypes = HazardType::orderBy('name')->get();
        return view('incidents.edit', compact('incident', 'hazardTypes'));
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, IncidentReport $incident)
    {
        $this->authorizeWrite();
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'hazard_type_id'   => 'required|exists:hazard_types,id',
            'incident_date'    => 'required|date',
            'status'           => 'required|in:ongoing,monitoring,resolved',
            'description'      => 'nullable|string',
            'affected_polygon' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $incident) {
            $incident->update($validated);
            $this->recomputeAffectedAreas($incident);
        });

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'Incident report updated and affected areas recomputed.');
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(IncidentReport $incident)
    {
        $this->authorizeWrite();
        $incident->delete();
        return redirect()->route('incidents.index')->with('success', 'Incident report deleted.');
    }

    // ── API: map data (households + boundaries) ───────────────────────────────

    public function mapData()
    {
        $households = Household::with('barangay:id,name')
            ->select('id', 'household_head', 'barangay_id', 'latitude', 'longitude',
                     'family_members', 'pwd_count', 'senior_count', 'infant_count', 'pregnant_count')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude',  '!=', 0)
            ->where('longitude', '!=', 0)
            ->whereBetween('latitude',  [12.50, 13.20])
            ->whereBetween('longitude', [120.50, 121.20])
            ->get()
            ->map(fn ($h) => [
                'id'              => $h->id,
                'household_head'  => $h->household_head,
                'barangay_name'   => $h->barangay?->name,
                'latitude'        => (float) $h->latitude,
                'longitude'       => (float) $h->longitude,
                'family_members'  => (int) $h->family_members,
                'has_vulnerable'  => ($h->pwd_count + $h->senior_count + $h->infant_count + $h->pregnant_count) > 0,
            ]);

        $boundaries = Barangay::whereNotNull('boundary_geojson')
            ->where('boundary_geojson', '!=', '')
            ->get(['id', 'name', 'boundary_geojson']);

        return response()->json(compact('households', 'boundaries'));
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /** Division chiefs are read-only — deny write access with a 403. */
    private function authorizeWrite(): void
    {
        if (Auth::user()->isDivisionChief()) {
            abort(403, 'Division chiefs have read-only access to incident reports.');
        }
    }

    private function recomputeAffectedAreas(IncidentReport $incident): void
    {
        // Clear previous affected area records
        $incident->affectedAreas()->delete();

        if (blank($incident->affected_polygon)) {
            return;
        }

        $result = AffectedAreaService::compute($incident->affected_polygon);

        foreach ($result['by_barangay'] as $row) {
            AffectedArea::create(array_merge(['incident_id' => $incident->id], $row));
        }
    }
}
