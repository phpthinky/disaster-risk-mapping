<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\PopulationData;
use App\Models\PopulationDataArchive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PopulationDataController extends Controller
{
    // ── Index — all barangays ─────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = auth()->user();

        // Barangay staff: redirect straight to their own barangay detail
        if ($user->isBarangayStaff()) {
            $barangay = Barangay::find($user->barangay_id);
            if ($barangay) {
                return redirect()->route('population.show', $barangay);
            }
        }

        // Load current population records keyed by barangay_id
        $currentRecords = PopulationData::with('barangay')
            ->get()
            ->keyBy('barangay_id');

        // Most-recent archive per barangay (for "previous" comparison)
        $latestArchive = PopulationDataArchive::selectRaw(
            'barangay_id, total_population, households, archived_at'
        )
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')
                  ->from('population_data_archive')
                  ->groupBy('barangay_id');
            })
            ->get()
            ->keyBy('barangay_id');

        $barangays = Barangay::orderBy('name')->get();

        // Summary totals
        $totals = [
            'population' => $barangays->sum('population'),
            'households' => $barangays->sum('household_count'),
            'barangays'  => $barangays->count(),
            'pwd'        => $barangays->sum('pwd_count'),
        ];

        return view('population.index', compact(
            'barangays', 'currentRecords', 'latestArchive', 'totals'
        ));
    }

    // ── Show — single barangay ────────────────────────────────────────────────

    public function show(Request $request, Barangay $barangay)
    {
        $user = auth()->user();

        // Scope barangay staff to their own barangay
        if ($user->isBarangayStaff() && $user->barangay_id !== $barangay->id) {
            abort(403);
        }

        $current = PopulationData::where('barangay_id', $barangay->id)
            ->latest()
            ->first();

        $archiveQuery = PopulationDataArchive::where('barangay_id', $barangay->id)
            ->orderByDesc('archived_at');

        // Optional filter by year and/or snapshot type
        if ($request->filled('year')) {
            $archiveQuery->whereYear('archived_at', $request->year);
        }
        if ($request->filled('type')) {
            $archiveQuery->where('snapshot_type', $request->type);
        }

        $archives = $archiveQuery->paginate(20)->withQueryString();

        // Distinct years available in archive for this barangay
        $archiveYears = PopulationDataArchive::where('barangay_id', $barangay->id)
            ->selectRaw('YEAR(archived_at) as yr')
            ->distinct()
            ->orderByDesc('yr')
            ->pluck('yr');

        // Chart data: last 10 ANNUAL snapshots (chronological) — these are the meaningful year-over-year points
        $chartData = PopulationDataArchive::where('barangay_id', $barangay->id)
            ->where('snapshot_type', 'annual')
            ->orderByDesc('archived_at')
            ->limit(10)
            ->get(['total_population', 'households', 'archived_at'])
            ->reverse()
            ->values();

        return view('population.show', compact(
            'barangay', 'current', 'archives', 'archiveYears', 'chartData'
        ));
    }

    // ── Manual Annual Snapshot (admin only) ──────────────────────────────────

    public function snapshot(Barangay $barangay)
    {
        $user = Auth::user();
        if (! $user->isAdmin()) {
            abort(403, 'Only admins can take manual snapshots.');
        }

        $record = PopulationData::where('barangay_id', $barangay->id)->latest()->first();

        if (! $record) {
            return back()->with('error', 'No population data found for this barangay.');
        }

        PopulationDataArchive::create([
            'original_id'       => $record->id,
            'barangay_id'       => $barangay->id,
            'total_population'  => $record->total_population ?? 0,
            'households'        => $record->households ?? 0,
            'elderly_count'     => $record->elderly_count ?? 0,
            'children_count'    => $record->children_count ?? 0,
            'pwd_count'         => $record->pwd_count ?? 0,
            'ips_count'         => $record->ips_count ?? 0,
            'solo_parent_count' => $record->solo_parent_count ?? 0,
            'widow_count'       => $record->widow_count ?? 0,
            'data_date'         => now()->toDateString(),
            'archived_by'       => $user->username ?? $user->name,
            'change_type'       => 'ANNUAL',
            'snapshot_type'     => 'annual',
        ]);

        return back()->with('success', 'Annual snapshot saved for ' . $barangay->name . '.');
    }
}
