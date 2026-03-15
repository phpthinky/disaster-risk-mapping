<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Barangay;
use App\Models\EvacuationCenter;
use App\Models\HazardType;
use App\Models\HazardZone;
use App\Models\Household;
use App\Models\IncidentReport;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('active');
    }

    // ── Admin ─────────────────────────────────────────────────────────────────

    public function admin()
    {
        $stats = [
            'barangays'       => Barangay::count(),
            'households'      => Household::count(),
            'population'      => Barangay::sum('population'),
            'active_incidents'=> IncidentReport::whereIn('status', ['ongoing', 'monitoring'])->count(),
            'hazard_zones'    => HazardZone::count(),
            'evac_centers'    => EvacuationCenter::where('status', 'operational')->count(),
        ];

        // Doughnut: hazard zone count per hazard type
        $hazardTypes   = HazardType::withCount('hazardZones')->orderByDesc('hazard_zones_count')->get();
        $hazardChart   = [
            'labels' => $hazardTypes->pluck('name'),
            'data'   => $hazardTypes->pluck('hazard_zones_count'),
            'colors' => $hazardTypes->pluck('color'),
        ];

        // Horizontal bar: top 10 barangays by population
        $topBarangays  = Barangay::orderByDesc('population')->limit(10)->get(['name', 'population']);
        $popChart      = [
            'labels' => $topBarangays->pluck('name'),
            'data'   => $topBarangays->pluck('population'),
        ];

        $recentIncidents = IncidentReport::with('hazardType')
            ->latest('incident_date')->limit(5)->get();

        $activeAlerts = Alert::where('is_active', true)
            ->with('barangay:id,name')->latest()->get();

        return view('dashboards.admin', compact(
            'stats', 'hazardChart', 'popChart', 'recentIncidents', 'activeAlerts'
        ));
    }

    // ── Division Chief ────────────────────────────────────────────────────────

    public function division()
    {
        $stats = [
            'population'      => Barangay::sum('population'),
            'at_risk'         => Barangay::sum('at_risk_count'),
            'active_incidents'=> IncidentReport::whereIn('status', ['ongoing', 'monitoring'])->count(),
            'evac_operational'=> EvacuationCenter::where('status', 'operational')->count(),
            'evac_capacity'   => EvacuationCenter::where('status', 'operational')->sum('capacity'),
        ];

        // Grouped bar: vulnerable population per barangay
        $barangays = Barangay::withCount('hazardZones')
            ->orderBy('name')
            ->get(['id', 'name', 'population', 'household_count', 'pwd_count',
                   'senior_count', 'infant_count', 'pregnant_count', 'ip_count',
                   'at_risk_count']);

        $vulnChart = [
            'labels'   => $barangays->pluck('name'),
            'pwd'      => $barangays->pluck('pwd_count'),
            'senior'   => $barangays->pluck('senior_count'),
            'infant'   => $barangays->pluck('infant_count'),
            'pregnant' => $barangays->pluck('pregnant_count'),
            'ip'       => $barangays->pluck('ip_count'),
        ];

        // Horizontal bar: hazard zone count per barangay
        $hazardCountData = Barangay::withCount('hazardZones')
            ->orderByDesc('hazard_zones_count')->limit(15)
            ->get(['id', 'name']);
        $hazardBarChart = [
            'labels' => $hazardCountData->pluck('name'),
            'data'   => $hazardCountData->pluck('hazard_zones_count'),
        ];

        $recentIncidents = IncidentReport::with('hazardType')
            ->latest('incident_date')->limit(5)->get();

        return view('dashboards.division', compact(
            'stats', 'barangays', 'vulnChart', 'hazardBarChart', 'recentIncidents'
        ));
    }

    // ── Barangay Staff ────────────────────────────────────────────────────────

    public function barangay()
    {
        $user     = auth()->user();
        $barangay = $user->barangay;
        $bid      = $user->barangay_id;

        $stats = [
            'households'   => Household::where('barangay_id', $bid)->count(),
            'population'   => $barangay?->population ?? 0,
            'at_risk'      => $barangay?->at_risk_count ?? 0,
            'hazard_zones' => HazardZone::where('barangay_id', $bid)->count(),
            'active_incidents' => IncidentReport::whereIn('status', ['ongoing', 'monitoring'])
                ->whereHas('affectedAreas', fn ($q) => $q->where('barangay_id', $bid))
                ->count(),
        ];

        // Doughnut: population breakdown
        $pwd      = (int) ($barangay?->pwd_count ?? 0);
        $senior   = (int) ($barangay?->senior_count ?? 0);
        $infant   = (int) ($barangay?->infant_count ?? 0);
        $pregnant = (int) ($barangay?->pregnant_count ?? 0);
        $ip       = (int) ($barangay?->ip_count ?? 0);
        $general  = max(0, $stats['population'] - $pwd - $senior - $infant - $pregnant);

        $popChart = [
            'labels' => ['General', 'Senior', 'PWD', 'Infant', 'Pregnant', 'IP'],
            'data'   => [$general, $senior, $pwd, $infant, $pregnant, $ip],
            'colors' => ['#0d6efd', '#6f42c1', '#dc3545', '#fd7e14', '#d63384', '#20c997'],
        ];

        // Bar: hazard zones by type
        $hazardTypes = HazardType::withCount(['hazardZones as zone_count' => fn ($q) => $q->where('barangay_id', $bid)])
            ->orderByDesc('zone_count')->get();
        $hazardChart = [
            'labels' => $hazardTypes->pluck('name'),
            'data'   => $hazardTypes->pluck('zone_count'),
            'colors' => $hazardTypes->pluck('color'),
        ];

        $recentHouseholds = Household::where('barangay_id', $bid)
            ->latest()->limit(5)->get(['id', 'household_head', 'sitio_purok_zone', 'latitude', 'longitude', 'created_at']);

        $evacCenters = EvacuationCenter::where('barangay_id', $bid)
            ->orderBy('name')->get();

        return view('dashboards.barangay', compact(
            'barangay', 'stats', 'popChart', 'hazardChart', 'recentHouseholds', 'evacCenters'
        ));
    }
}
