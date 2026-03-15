<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // All roles can view; query is scoped to what they're allowed to see
        $query = Announcement::with('creator')
            ->visibleTo($user)
            ->latest();

        // Admins and division chiefs see all; staff get filtered by visibleTo scope
        $announcements = $query->paginate(20);

        $canWrite = $user->isAdmin() || $user->isDivisionChief();

        return view('announcements.index', compact('announcements', 'canWrite'));
    }

    public function store(Request $request)
    {
        $this->authorizeWrite();

        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'message'           => 'required|string',
            'announcement_type' => 'required|in:emergency,info,maintenance,general',
            'target_audience'   => 'required|in:all,barangay_staff,division_chief,admin',
            'is_active'         => 'boolean',
        ]);

        $validated['is_active']  = $request->boolean('is_active', true);
        $validated['created_by'] = auth()->id();

        Announcement::create($validated);

        return back()->with('success', 'Announcement posted.');
    }

    public function update(Request $request, Announcement $announcement)
    {
        $this->authorizeWrite();

        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'message'           => 'required|string',
            'announcement_type' => 'required|in:emergency,info,maintenance,general',
            'target_audience'   => 'required|in:all,barangay_staff,division_chief,admin',
            'is_active'         => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', false);

        $announcement->update($validated);

        return back()->with('success', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement)
    {
        $this->authorizeWrite();

        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }

    public function toggle(Announcement $announcement)
    {
        $this->authorizeWrite();

        $announcement->update(['is_active' => ! $announcement->is_active]);

        return back()->with('success', 'Announcement ' . ($announcement->is_active ? 'activated' : 'deactivated') . '.');
    }

    private function authorizeWrite(): void
    {
        $user = auth()->user();
        if (! $user->isAdmin() && ! $user->isDivisionChief()) {
            abort(403, 'Only administrators and division chiefs can manage announcements.');
        }
    }
}
