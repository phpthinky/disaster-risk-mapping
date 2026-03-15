<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Barangay;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index()
    {
        $this->authorizeAdmin();

        $alerts    = Alert::with('barangay')->latest()->paginate(20);
        $barangays = Barangay::orderBy('name')->get(['id', 'name']);

        return view('alerts.index', compact('alerts', 'barangays'));
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'message'     => 'nullable|string',
            'alert_type'  => 'required|in:warning,info,danger',
            'barangay_id' => 'nullable|exists:barangays,id',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        Alert::create($validated);

        return back()->with('success', 'Alert created.');
    }

    public function update(Request $request, Alert $alert)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'message'     => 'nullable|string',
            'alert_type'  => 'required|in:warning,info,danger',
            'barangay_id' => 'nullable|exists:barangays,id',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', false);

        $alert->update($validated);

        return back()->with('success', 'Alert updated.');
    }

    public function destroy(Alert $alert)
    {
        $this->authorizeAdmin();

        $alert->delete();

        return back()->with('success', 'Alert deleted.');
    }

    public function toggle(Alert $alert)
    {
        $this->authorizeAdmin();

        $alert->update(['is_active' => ! $alert->is_active]);

        return back()->with('success', 'Alert ' . ($alert->is_active ? 'activated' : 'deactivated') . '.');
    }

    private function authorizeAdmin(): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Only administrators can manage alerts.');
        }
    }
}
