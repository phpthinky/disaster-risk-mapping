<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\HazardType;
use App\Models\HazardZone;
use App\Models\Household;
use App\Models\IncidentReport;
use App\Models\PopulationData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;

class ReportController extends Controller
{
    // ── Tabular Report Views ───────────────────────────────────────────────

    public function index()
    {
        return view('reports.index');
    }

    public function population(Request $request)
    {
        $query = Barangay::orderBy('name');
        $this->applyBarangayScope($query, 'id');
        $barangays = $query->get();

        return view('reports.population', compact('barangays'));
    }

    public function risk(Request $request)
    {
        $hazardTypes = HazardType::orderBy('name')->get();
        $allBarangays = Barangay::orderBy('name')->get();
        $riskLevels = HazardZone::RISK_LEVELS;

        $query = HazardZone::with(['hazardType', 'barangay'])->orderBy('barangay_id');
        $this->applyBarangayScope($query);

        if ($request->filled('barangay_id')) {
            $query->where('barangay_id', $request->barangay_id);
        }
        if ($request->filled('hazard_type_id')) {
            $query->where('hazard_type_id', $request->hazard_type_id);
        }
        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->risk_level);
        }

        $zones = $query->get()->groupBy(fn($z) => $z->barangay->name ?? 'Unknown');

        return view('reports.risk', compact('zones', 'hazardTypes', 'allBarangays', 'riskLevels'));
    }

    public function households(Request $request)
    {
        $allBarangays = Barangay::orderBy('name')->get();

        $query = Household::with('barangay');
        $this->applyBarangayScope($query);

        if ($request->filled('barangay_id')) {
            $query->where('barangay_id', $request->barangay_id);
        }
        if ($request->filled('ip')) {
            $query->where('ip_non_ip', $request->ip);
        }
        if ($request->input('gps') === '1') {
            $query->whereNotNull('latitude')->whereNotNull('longitude');
        }

        $households = $query->orderBy('barangay_id')->orderBy('household_head')
            ->paginate(30)->withQueryString();

        return view('reports.households', compact('households', 'allBarangays'));
    }

    public function incidents(Request $request)
    {
        $hazardTypes = HazardType::orderBy('name')->get();

        $query = IncidentReport::with(['hazardType', 'affectedAreas'])
            ->when(auth()->user()->isBarangayStaff(), function (Builder $q) {
                $q->whereHas('affectedAreas', fn($a) => $a->where('barangay_id', auth()->user()->barangay_id));
            })
            ->orderByDesc('incident_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('from')) {
            $query->whereDate('incident_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('incident_date', '<=', $request->to);
        }
        if ($request->filled('hazard_type_id')) {
            $query->where('hazard_type_id', $request->hazard_type_id);
        }

        $incidents = $query->paginate(25)->withQueryString();

        return view('reports.incidents', compact('incidents', 'hazardTypes'));
    }

    // ── Excel Exports ──────────────────────────────────────────────────────

    public function exportExcel(string $type, Request $request)
    {
        return match ($type) {
            'population' => $this->excelPopulation(),
            'risk'       => $this->excelRisk($request),
            'households' => $this->excelHouseholds($request),
            'incidents'  => $this->excelIncidents($request),
            default      => abort(404),
        };
    }

    private function excelPopulation()
    {
        $query = Barangay::orderBy('name');
        $this->applyBarangayScope($query, 'id');

        $rows = $query->get()->map(fn($b) => [
            'Barangay'        => $b->name,
            'Total Pop.'      => $b->population,
            'Households'      => $b->household_count,
            'PWD'             => $b->pwd_count,
            'Senior (60+)'    => $b->senior_count,
            'Infant (0–2)'    => $b->infant_count,
            'Pregnant'        => $b->pregnant_count,
            'IP'              => $b->ip_count,
            'At-Risk'         => $b->at_risk_count,
        ]);

        return (new FastExcel($rows))->download('population-summary.xlsx');
    }

    private function excelRisk(Request $request)
    {
        $query = HazardZone::with(['hazardType', 'barangay'])->orderBy('barangay_id');
        $this->applyBarangayScope($query);
        if ($request->filled('barangay_id'))    $query->where('barangay_id', $request->barangay_id);
        if ($request->filled('hazard_type_id')) $query->where('hazard_type_id', $request->hazard_type_id);
        if ($request->filled('risk_level'))     $query->where('risk_level', $request->risk_level);

        $rows = $query->get()->map(fn($z) => [
            'Barangay'            => $z->barangay->name ?? '—',
            'Hazard Type'         => $z->hazardType->name ?? '—',
            'Risk Level'          => $z->risk_level,
            'Area (km²)'          => $z->area_km2,
            'Affected Population' => $z->affected_population,
        ]);

        return (new FastExcel($rows))->download('risk-analysis.xlsx');
    }

    private function excelHouseholds(Request $request)
    {
        $query = Household::with('barangay');
        $this->applyBarangayScope($query);
        if ($request->filled('barangay_id')) $query->where('barangay_id', $request->barangay_id);
        if ($request->filled('ip'))          $query->where('ip_non_ip', $request->ip);
        if ($request->input('gps') === '1')  $query->whereNotNull('latitude')->whereNotNull('longitude');

        $rows = $query->orderBy('barangay_id')->orderBy('household_head')->get()->map(fn($h) => [
            'Barangay'               => $h->barangay->name ?? '—',
            'HH ID'                  => $h->hh_id ?? '—',
            'Household Head'         => $h->household_head,
            'Sex'                    => $h->sex,
            'Age'                    => $h->age,
            'Zone / Sitio'           => $h->sitio_purok_zone ?? '—',
            'IP / Non-IP'            => $h->ip_non_ip ?? '—',
            'Family Members'         => $h->family_members,
            'PWD'                    => $h->pwd_count,
            'Seniors'                => $h->senior_count,
            'Infants (0-2)'          => $h->infant_count,
            'Pregnant'               => $h->pregnant_count,
            'Preparedness Kit'       => $h->preparedness_kit ? 'Yes' : 'No',
            'Latitude'               => $h->latitude,
            'Longitude'              => $h->longitude,
            'Registered'             => $h->created_at?->format('Y-m-d'),
        ]);

        return (new FastExcel($rows))->download('households.xlsx');
    }

    private function excelIncidents(Request $request)
    {
        $query = IncidentReport::with(['hazardType', 'affectedAreas'])
            ->when(auth()->user()->isBarangayStaff(), fn(Builder $q) =>
                $q->whereHas('affectedAreas', fn($a) => $a->where('barangay_id', auth()->user()->barangay_id))
            )
            ->orderByDesc('incident_date');

        if ($request->filled('status'))       $query->where('status', $request->status);
        if ($request->filled('from'))         $query->whereDate('incident_date', '>=', $request->from);
        if ($request->filled('to'))           $query->whereDate('incident_date', '<=', $request->to);
        if ($request->filled('hazard_type_id')) $query->where('hazard_type_id', $request->hazard_type_id);

        $rows = $query->get()->map(fn($i) => [
            'Title'               => $i->title,
            'Hazard Type'         => $i->hazardType->name ?? '—',
            'Status'              => ucfirst($i->status),
            'Date'                => $i->incident_date?->format('Y-m-d'),
            'Affected Barangays'  => $i->affectedAreas->count(),
            'Affected Households' => $i->affectedAreas->sum('affected_households'),
            'Affected Population' => $i->affectedAreas->sum('affected_population'),
        ]);

        return (new FastExcel($rows))->download('incident-summary.xlsx');
    }

    // ── PDF Exports ────────────────────────────────────────────────────────

    public function exportPdf(string $type, Request $request)
    {
        return match ($type) {
            'population' => $this->pdfPopulation(),
            'risk'       => $this->pdfRisk($request),
            'incidents'  => $this->pdfIncidents($request),
            default      => abort(404),
        };
    }

    private function pdfPopulation()
    {
        $query = Barangay::orderBy('name');
        $this->applyBarangayScope($query, 'id');
        $barangays = $query->get();

        return Pdf::loadView('reports.pdf.population', compact('barangays'))
            ->setPaper('a4', 'landscape')
            ->download('population-summary.pdf');
    }

    private function pdfRisk(Request $request)
    {
        $query = HazardZone::with(['hazardType', 'barangay'])->orderBy('barangay_id');
        $this->applyBarangayScope($query);
        if ($request->filled('barangay_id'))    $query->where('barangay_id', $request->barangay_id);
        if ($request->filled('hazard_type_id')) $query->where('hazard_type_id', $request->hazard_type_id);
        if ($request->filled('risk_level'))     $query->where('risk_level', $request->risk_level);

        $zones = $query->get()->groupBy(fn($z) => $z->barangay->name ?? 'Unknown');

        return Pdf::loadView('reports.pdf.risk', compact('zones'))
            ->setPaper('a4', 'landscape')
            ->download('risk-analysis.pdf');
    }

    private function pdfIncidents(Request $request)
    {
        $query = IncidentReport::with(['hazardType', 'affectedAreas'])
            ->when(auth()->user()->isBarangayStaff(), fn(Builder $q) =>
                $q->whereHas('affectedAreas', fn($a) => $a->where('barangay_id', auth()->user()->barangay_id))
            )
            ->orderByDesc('incident_date');

        if ($request->filled('status'))         $query->where('status', $request->status);
        if ($request->filled('from'))           $query->whereDate('incident_date', '>=', $request->from);
        if ($request->filled('to'))             $query->whereDate('incident_date', '<=', $request->to);
        if ($request->filled('hazard_type_id')) $query->where('hazard_type_id', $request->hazard_type_id);

        $incidents = $query->get();

        return Pdf::loadView('reports.pdf.incidents', compact('incidents'))
            ->setPaper('a4', 'landscape')
            ->download('incident-summary.pdf');
    }

    // ── Graphical Report Views ─────────────────────────────────────────────

    public function graphical()
    {
        return view('reports.graphical.index');
    }

    public function graphicalPopulation()
    {
        $barangays     = $this->scopedBarangays();
        $availableYears = $this->populationYears();

        return view('reports.graphical.population', compact('barangays', 'availableYears'));
    }

    public function graphicalVulnerability()
    {
        $barangays     = $this->scopedBarangays();
        $availableYears = $this->populationYears();

        return view('reports.graphical.vulnerability', compact('barangays', 'availableYears'));
    }

    public function graphicalIncidents()
    {
        $hazardTypes    = HazardType::orderBy('name')->get();
        $availableYears = $this->incidentYears();

        return view('reports.graphical.incidents', compact('hazardTypes', 'availableYears'));
    }

    public function graphicalHouseholds()
    {
        $barangays     = $this->scopedBarangays();
        $availableYears = $this->householdYears();

        return view('reports.graphical.households', compact('barangays', 'availableYears'));
    }

    public function graphicalHazards()
    {
        $query = HazardZone::with(['hazardType', 'barangay']);
        if (auth()->user()->isBarangayStaff()) {
            $query->where('barangay_id', auth()->user()->barangay_id);
        }
        $zones = $query->get();

        $riskCounts   = $zones->groupBy('risk_level')->map->count()->sortDesc();
        $hazardCounts = $zones->groupBy(fn($z) => $z->hazardType->name ?? 'Unknown')->map->count()->sortDesc();
        $topBarangays = $zones->groupBy(fn($z) => $z->barangay->name ?? 'Unknown')
            ->map(fn($g) => round($g->sum('area_km2'), 2))
            ->sortDesc()->take(10);

        return view('reports.graphical.hazards', compact('riskCounts', 'hazardCounts', 'topBarangays'));
    }

    // ── Graphical AJAX Data Endpoints ──────────────────────────────────────

    public function graphicalPopulationData(Request $request): JsonResponse
    {
        $years      = $this->parseYears($request);
        $barangayId = $request->input('barangay_id');

        $query = PopulationData::with('barangay')
            ->whereIn(DB::raw('YEAR(data_date)'), $years);

        $this->applyBarangayScopeOrFilter($query, $barangayId);

        $records = $query->orderBy('data_date')->get();

        // Group: [year][barangay] = total_population
        $grouped = [];
        foreach ($records as $r) {
            $yr    = $r->data_date->year;
            $bname = $r->barangay->name ?? 'Unknown';
            $grouped[$yr][$bname] = ($grouped[$yr][$bname] ?? 0) + $r->total_population;
        }

        $labels   = collect($grouped)->flatMap(fn($v) => array_keys($v))->unique()->sort()->values()->all();
        $datasets = [];
        $colors   = ['#4361ee', '#f72585', '#7209b7', '#4cc9f0', '#4ade80'];

        foreach (array_values($years) as $i => $yr) {
            $datasets[] = [
                'label'           => (string) $yr,
                'data'            => array_map(fn($b) => $grouped[$yr][$b] ?? 0, $labels),
                'backgroundColor' => $colors[$i % 5] . '33',
                'borderColor'     => $colors[$i % 5],
                'borderWidth'     => 2,
                'tension'         => 0.3,
                'fill'            => false,
                'pointRadius'     => 4,
            ];
        }

        return response()->json(['labels' => $labels, 'datasets' => $datasets]);
    }

    public function graphicalVulnerabilityData(Request $request): JsonResponse
    {
        $years      = $this->parseYears($request);
        $barangayId = $request->input('barangay_id');

        $query = PopulationData::selectRaw('
            YEAR(data_date)    as yr,
            SUM(pwd_count)     as pwd,
            SUM(elderly_count) as senior,
            SUM(children_count) as children,
            SUM(at_risk_count) as at_risk,
            SUM(ips_count)     as ip
        ')->whereIn(DB::raw('YEAR(data_date)'), $years)
          ->groupByRaw('YEAR(data_date)')
          ->orderByRaw('YEAR(data_date)');

        $this->applyBarangayScopeOrFilter($query, $barangayId);

        $records = $query->get()->keyBy('yr');

        $labels = array_map('strval', $years);

        $makeDs = fn($label, $key, $color) => [
            'label'           => $label,
            'data'            => array_map(fn($y) => (int) ($records[$y]?->$key ?? 0), $years),
            'backgroundColor' => $color . 'bb',
            'borderColor'     => $color,
            'borderWidth'     => 1,
        ];

        return response()->json([
            'labels'   => $labels,
            'datasets' => [
                $makeDs('PWD',      'pwd',      '#4361ee'),
                $makeDs('Seniors',  'senior',   '#f72585'),
                $makeDs('Children', 'children', '#7209b7'),
                $makeDs('At-Risk',  'at_risk',  '#f77f00'),
                $makeDs('IP',       'ip',       '#4cc9f0'),
            ],
        ]);
    }

    public function graphicalIncidentsData(Request $request): JsonResponse
    {
        $mode = $request->input('mode', 'annual');

        $query = IncidentReport::with('hazardType');
        if (auth()->user()->isBarangayStaff()) {
            $query->whereHas('affectedAreas', fn($a) => $a->where('barangay_id', auth()->user()->barangay_id));
        }

        $colors = ['#4361ee', '#f72585', '#7209b7', '#4cc9f0', '#4ade80', '#f77f00', '#e63946'];

        if ($mode === 'monthly') {
            $year = (int) $request->input('year', now()->year);
            $records = $query->whereYear('incident_date', $year)->get();

            $months   = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            $datasets = [];
            $ci       = 0;

            foreach ($records->groupBy(fn($r) => $r->hazardType->name ?? 'Unknown') as $htName => $incidents) {
                $data = array_fill(0, 12, 0);
                foreach ($incidents as $inc) {
                    $data[(int) $inc->incident_date->format('n') - 1]++;
                }
                $datasets[] = [
                    'label'           => $htName,
                    'data'            => $data,
                    'backgroundColor' => $colors[$ci % count($colors)] . 'cc',
                    'borderColor'     => $colors[$ci % count($colors)],
                    'borderWidth'     => 1,
                ];
                $ci++;
            }

            return response()->json(['labels' => $months, 'datasets' => $datasets]);
        }

        // Annual mode
        $years   = $this->parseYears($request);
        $records = $query->whereIn(DB::raw('YEAR(incident_date)'), $years)->get();
        $datasets = [];
        $ci = 0;

        foreach ($records->groupBy(fn($r) => $r->hazardType->name ?? 'Unknown') as $htName => $incidents) {
            $datasets[] = [
                'label'           => $htName,
                'data'            => array_map(fn($y) => $incidents->filter(fn($i) => $i->incident_date->year == $y)->count(), $years),
                'backgroundColor' => $colors[$ci % count($colors)] . 'cc',
                'borderColor'     => $colors[$ci % count($colors)],
                'borderWidth'     => 1,
            ];
            $ci++;
        }

        return response()->json(['labels' => array_map('strval', $years), 'datasets' => $datasets]);
    }

    public function graphicalHouseholdsData(Request $request): JsonResponse
    {
        $mode       = $request->input('mode', 'annual');
        $barangayId = $request->input('barangay_id');

        $query = Household::query();
        $this->applyBarangayScopeOrFilter($query, $barangayId);

        if ($mode === 'monthly') {
            $year    = (int) $request->input('year', now()->year);
            $records = $query->whereYear('created_at', $year)
                ->selectRaw('MONTH(created_at) as mo, COUNT(*) as cnt')
                ->groupBy('mo')->orderBy('mo')->get();

            $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            $data   = array_fill(0, 12, 0);
            foreach ($records as $r) {
                $data[(int) $r->mo - 1] = (int) $r->cnt;
            }

            return response()->json([
                'labels'   => $months,
                'datasets' => [[
                    'label'           => "Registered in $year",
                    'data'            => $data,
                    'backgroundColor' => '#4361eeaa',
                    'borderColor'     => '#4361ee',
                    'borderWidth'     => 1,
                    'borderRadius'    => 4,
                ]],
            ]);
        }

        // Annual mode
        $years   = $this->parseYears($request, range(max(now()->year - 4, 2020), now()->year));
        $records = $query->whereIn(DB::raw('YEAR(created_at)'), $years)
            ->selectRaw('YEAR(created_at) as yr, COUNT(*) as cnt')
            ->groupBy('yr')->orderBy('yr')->get()->keyBy('yr');

        return response()->json([
            'labels'   => array_map('strval', $years),
            'datasets' => [[
                'label'           => 'Households Registered',
                'data'            => array_map(fn($y) => (int) ($records[$y]?->cnt ?? 0), $years),
                'backgroundColor' => '#4361eeaa',
                'borderColor'     => '#4361ee',
                'borderWidth'     => 1,
                'borderRadius'    => 4,
            ]],
        ]);
    }

    // ── Private Helpers ────────────────────────────────────────────────────

    private function applyBarangayScope(Builder $query, string $column = 'barangay_id'): void
    {
        if (auth()->user()->isBarangayStaff()) {
            $query->where($column, auth()->user()->barangay_id);
        }
    }

    private function applyBarangayScopeOrFilter(Builder $query, ?string $barangayId, string $column = 'barangay_id'): void
    {
        if (auth()->user()->isBarangayStaff()) {
            $query->where($column, auth()->user()->barangay_id);
        } elseif ($barangayId && $barangayId !== 'all') {
            $query->where($column, (int) $barangayId);
        }
    }

    private function scopedBarangays()
    {
        if (auth()->user()->isBarangayStaff()) {
            return Barangay::where('id', auth()->user()->barangay_id)->get();
        }
        return Barangay::orderBy('name')->get();
    }

    private function parseYears(Request $request, array $default = []): array
    {
        if (empty($default)) {
            $default = range(now()->year - 4, now()->year);
        }
        $years = $request->input('years', $default);
        return array_slice(array_map('intval', (array) $years), 0, 5);
    }

    private function populationYears()
    {
        return PopulationData::selectRaw('YEAR(data_date) as yr')
            ->distinct()->orderByDesc('yr')->limit(10)->pluck('yr');
    }

    private function incidentYears()
    {
        return IncidentReport::selectRaw('YEAR(incident_date) as yr')
            ->distinct()->orderByDesc('yr')->limit(10)->pluck('yr');
    }

    private function householdYears()
    {
        return Household::selectRaw('YEAR(created_at) as yr')
            ->distinct()->orderByDesc('yr')->limit(10)->pluck('yr');
    }
}
