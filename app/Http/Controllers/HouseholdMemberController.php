<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Services\SyncService;
use Illuminate\Http\Request;

class HouseholdMemberController extends Controller
{
    public function index(Household $household)
    {
        $this->authorizeScope($household);

        return response()->json([
            'ok'      => true,
            'members' => $household->members()->orderByDesc('age')->get(),
            'total'   => $household->members()->count(),
        ]);
    }

    public function store(Request $request, Household $household)
    {
        $this->authorizeScope($household);

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'age'        => 'required|integer|min:0|max:130',
            'birthday'   => 'nullable|date',
            'sex'        => 'required|in:Male,Female',
            'relation'   => 'nullable|string|max:100',
            'is_pwd'     => 'boolean',
            'is_pregnant'=> 'boolean',
            'is_ip'      => 'boolean',
        ]);

        $member = $household->members()->create([
            'full_name'    => $validated['name'],
            'age'          => $validated['age'],
            'birthday'     => $validated['birthday'] ?? null,
            'gender'       => $validated['sex'],
            'relationship' => $validated['relation'] ?? '',
            'is_pwd'       => $validated['is_pwd'] ?? false,
            'is_pregnant'  => $validated['is_pregnant'] ?? false,
            'is_ip'        => $validated['is_ip'] ?? false,
        ]);

        SyncService::recomputeHousehold($household->id);
        SyncService::handleSync($household->barangay_id);

        return response()->json([
            'ok'     => true,
            'member' => $member,
            'msg'    => 'Member added.',
        ]);
    }

    public function destroy(HouseholdMember $member)
    {
        $household = $member->household;
        $this->authorizeScope($household);

        $member->delete();

        SyncService::recomputeHousehold($household->id);
        SyncService::handleSync($household->barangay_id);

        return response()->json(['ok' => true, 'msg' => 'Member removed.']);
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    private function authorizeScope(Household $household): void
    {
        $user = auth()->user();
        if ($user->isBarangayStaff() && $household->barangay_id !== $user->barangay_id) {
            abort(403);
        }
        if ($user->isDivisionChief()) {
            abort(403);
        }
    }
}
