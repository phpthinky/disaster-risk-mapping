<?php

namespace App\Http\Controllers;

use App\Exports\HouseholdExport;
use App\Http\Requests\StoreHouseholdRequest;
use App\Models\Barangay;
use App\Models\Household;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class HouseholdController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Household::with('barangay')->orderByDesc('created_at');

        if ($user->isBarangayStaff()) {
            $query->where('barangay_id', $user->barangay_id);
        } elseif ($request->filled('barangay_id')) {
            $query->where('barangay_id', $request->barangay_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('household_head', 'like', "%{$search}%");
        }

        $households = $query->paginate(25)->withQueryString();

        // Summary stats scoped to same filter
        $summaryQuery = Household::query();
        if ($user->isBarangayStaff()) {
            $summaryQuery->where('barangay_id', $user->barangay_id);
        } elseif ($request->filled('barangay_id')) {
            $summaryQuery->where('barangay_id', $request->barangay_id);
        }

        $summary = [
            'total'       => $summaryQuery->count(),
            'population'  => $summaryQuery->sum('family_members'),
            'pwd'         => $summaryQuery->sum('pwd_count'),
            'seniors'     => $summaryQuery->sum('senior_count'),
            'with_gps'    => $summaryQuery->whereNotNull('latitude')->whereNotNull('longitude')->count(),
        ];

        $barangays = $user->isBarangayStaff()
            ? collect()
            : Barangay::orderBy('name')->get();

        $selectedBarangay = $request->barangay_id;

        return view('households.index', compact('households', 'summary', 'barangays', 'selectedBarangay'));
    }

    public function create()
    {
        $user = auth()->user();
        $barangays = $user->isBarangayStaff()
            ? Barangay::where('id', $user->barangay_id)->get()
            : Barangay::orderBy('name')->get();

        return view('households.create', compact('barangays'));
    }

    public function store(StoreHouseholdRequest $request)
    {
        $user = auth()->user();
        $data = $request->validated();

        // Enforce barangay scope for staff
        if ($user->isBarangayStaff()) {
            $data['barangay_id'] = $user->barangay_id;
        }

        Household::create($data); // observer fires handleSync automatically

        return redirect()->route('households.index')
            ->with('success', 'Household added successfully.');
    }

    public function show(Household $household)
    {
        $this->authorizeScope($household);

        $household->load(['barangay', 'members']);

        return view('households.show', compact('household'));
    }

    public function edit(Household $household)
    {
        $this->authorizeScope($household);

        $user = auth()->user();
        $barangays = $user->isBarangayStaff()
            ? Barangay::where('id', $user->barangay_id)->get()
            : Barangay::orderBy('name')->get();

        return view('households.edit', compact('household', 'barangays'));
    }

    public function update(StoreHouseholdRequest $request, Household $household)
    {
        $this->authorizeScope($household);

        $data = $request->validated();

        if (auth()->user()->isBarangayStaff()) {
            $data['barangay_id'] = auth()->user()->barangay_id;
        }

        $household->update($data); // observer fires handleSync

        return redirect()->route('households.show', $household)
            ->with('success', 'Household updated successfully.');
    }

    public function destroy(Household $household)
    {
        $this->authorizeScope($household);

        $household->delete(); // observer fires handleSync on deleted

        return redirect()->route('households.index')
            ->with('success', 'Household deleted successfully.');
    }

    public function export(Request $request)
    {
        $user = auth()->user();
        $barangayId = $user->isBarangayStaff()
            ? $user->barangay_id
            : ($request->barangay_id ?: null);

        $filename = 'households_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new HouseholdExport($barangayId), $filename);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function authorizeScope(Household $household): void
    {
        $user = auth()->user();
        if ($user->isBarangayStaff() && $household->barangay_id !== $user->barangay_id) {
            abort(403, 'You can only access households in your barangay.');
        }
        if ($user->isDivisionChief()) {
            abort(403, 'Division chief has read-only access via reports.');
        }
    }
}
