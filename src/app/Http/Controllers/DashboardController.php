<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Show the dashboard.
     */
    public function index()
    {
        return Inertia::render('Dashboard/Index');
    }
}
