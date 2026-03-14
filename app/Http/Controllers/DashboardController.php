<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('active');
    }

    /**
     * Admin dashboard — full system overview.
     */
    public function admin()
    {
        return view('dashboards.admin');
    }

    /**
     * Division chief dashboard — aggregate read-only view.
     */
    public function division()
    {
        return view('dashboards.division');
    }

    /**
     * Barangay staff dashboard — own barangay view.
     */
    public function barangay()
    {
        return view('dashboards.barangay');
    }
}
