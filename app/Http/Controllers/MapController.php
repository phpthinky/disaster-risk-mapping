<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\EvacuationCenter;
use App\Models\HazardType;
use App\Models\HazardZone;
use App\Models\Household;
use App\Models\IncidentReport;
use Illuminate\Support\Facades\DB;

class MapController extends Controller
{
    // ── Page ──────────────────────────────────────────────────────────────────

    public function index()
    {
        // Stats passed to blade for sidebar (avoids extra AJAX on load)
        $stats = [
            'barangays'    => Barangay::count(),
            'hazard_zones' => HazardZone::count(),
            'households'   => Household::whereNotNull('latitude')->where('latitude', '!=', 0)->count(),
            'at_risk'      => Barangay::sum('at_risk_count'),
            'incidents'    => IncidentReport::whereIn('status', ['ongoing', 'monitoring'])->count(),
            'evac_centers' => EvacuationCenter::where('status', 'operational')->count(),
        ];

        $hazardTypes = HazardType::orderBy('name')->get(['id', 'name', 'color', 'icon']);

        return view('map.index', compact('stats', 'hazardTypes'));
    }

    // ── API: Barangay boundaries + population stats ───────────────────────────

    public function apiBarangays()
    {
        $rows = Barangay::select(
            'id', 'name', 'population', 'household_count', 'pwd_count',
            'senior_count', 'infant_count', 'pregnant_count', 'ip_count',
            'at_risk_count', 'boundary_geojson'
        )
        ->withCount('hazardZones')
        ->orderBy('name')
        ->get()
        ->map(fn ($b) => [
            'id'             => $b->id,
            'name'           => $b->name,
            'population'     => (int) $b->population,
            'household_count'=> (int) $b->household_count,
            'pwd_count'      => (int) $b->pwd_count,
            'senior_count'   => (int) $b->senior_count,
            'infant_count'   => (int) $b->infant_count,
            'pregnant_count' => (int) $b->pregnant_count,
            'ip_count'       => (int) $b->ip_count,
            'at_risk_count'  => (int) $b->at_risk_count,
            'hazard_zones_count' => (int) $b->hazard_zones_count,
            'boundary_geojson'   => $b->boundary_geojson,
        ]);

        return response()->json($rows);
    }

    // ── API: Hazard zones with type info ──────────────────────────────────────

    public function apiHazardZones()
    {
        $rows = HazardZone::with(['hazardType', 'barangay:id,name'])
            ->get()
            ->map(fn ($hz) => [
                'id'                  => $hz->id,
                'risk_level'          => $hz->risk_level,
                'area_km2'            => $hz->area_km2,
                'affected_population' => (int) $hz->affected_population,
                'coordinates'         => $hz->coordinates,
                'description'         => $hz->description,
                'barangay_name'       => $hz->barangay?->name,
                'hazard_name'         => $hz->hazardType?->name,
                'hazard_color'        => $hz->hazardType?->color ?? '#6c757d',
                'hazard_icon'         => $hz->hazardType?->icon ?? 'fa-exclamation-triangle',
            ]);

        return response()->json($rows);
    }

    // ── API: Households (GPS-valid, within Sablayan bounds) ───────────────────

    public function apiHouseholds()
    {
        $rows = Household::with('barangay:id,name')
            ->select('id', 'household_head', 'barangay_id', 'latitude', 'longitude',
                     'family_members', 'pwd_count', 'senior_count', 'infant_count',
                     'pregnant_count', 'ip_non_ip', 'sitio_purok_zone')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude',  '!=', 0)
            ->where('longitude', '!=', 0)
            ->whereBetween('latitude',  [12.50, 13.20])
            ->whereBetween('longitude', [120.50, 121.20])
            ->get()
            ->map(fn ($h) => [
                'id'             => $h->id,
                'household_head' => $h->household_head,
                'barangay_name'  => $h->barangay?->name,
                'latitude'       => (float) $h->latitude,
                'longitude'      => (float) $h->longitude,
                'family_members' => (int) $h->family_members,
                'sitio'          => $h->sitio_purok_zone,
                'is_ip'          => $h->ip_non_ip === 'IP',
                'has_vulnerable' => ((int)$h->pwd_count + (int)$h->senior_count +
                                     (int)$h->infant_count + (int)$h->pregnant_count) > 0,
                'vuln_flags'     => array_filter([
                    $h->pwd_count      > 0 ? 'PWD'      : null,
                    $h->senior_count   > 0 ? 'Elderly'  : null,
                    $h->infant_count   > 0 ? 'Infant'   : null,
                    $h->pregnant_count > 0 ? 'Pregnant' : null,
                    $h->ip_non_ip === 'IP' ? 'IP'       : null,
                ]),
            ]);

        return response()->json($rows);
    }

    // ── API: Active & monitoring incidents ───────────────────────────────────

    public function apiIncidents()
    {
        $rows = IncidentReport::with('hazardType:id,name,color,icon')
            ->withSum('affectedAreas as total_affected', 'affected_population')
            ->whereIn('status', ['ongoing', 'monitoring'])
            ->whereNotNull('affected_polygon')
            ->where('affected_polygon', '!=', '')
            ->latest('incident_date')
            ->get()
            ->map(fn ($inc) => [
                'id'               => $inc->id,
                'title'            => $inc->title,
                'status'           => $inc->status,
                'incident_date'    => $inc->incident_date?->format('Y-m-d'),
                'affected_polygon' => $inc->affected_polygon,
                'total_affected'   => (int) $inc->total_affected,
                'hazard_name'      => $inc->hazardType?->name,
                'hazard_color'     => $inc->hazardType?->color ?? '#dc3545',
                'hazard_icon'      => $inc->hazardType?->icon ?? 'fa-exclamation-triangle',
            ]);

        return response()->json($rows);
    }

    // ── API: Evacuation centers ───────────────────────────────────────────────

    public function apiEvacuationCenters()
    {
        $rows = EvacuationCenter::with('barangay:id,name')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude',  '!=', 0)
            ->where('longitude', '!=', 0)
            ->get()
            ->map(fn ($ec) => [
                'id'               => $ec->id,
                'name'             => $ec->name,
                'barangay_name'    => $ec->barangay?->name,
                'latitude'         => (float) $ec->latitude,
                'longitude'        => (float) $ec->longitude,
                'status'           => $ec->status,
                'capacity'         => (int) $ec->capacity,
                'current_occupancy'=> (int) $ec->current_occupancy,
                'availability'     => $ec->availability,
                'facilities'       => $ec->facilities,
                'contact_person'   => $ec->contact_person,
                'contact_number'   => $ec->contact_number,
            ]);

        return response()->json($rows);
    }
}
