<?php

namespace App\Http\Controllers;

use App\Models\ModPack;
use App\Models\User;
use Inertia\Inertia;

class LandingController extends Controller
{
    public function index()
    {
        $stats = [
            'total_mod_packs' => ModPack::count(),
            'total_users' => User::count(),
            'total_downloads' => ModPack::sum('downloads_count'),
        ];

        return Inertia::render('Index', [
            'stats' => $stats,
        ]);
    }

    public function about()
    {
        return Inertia::render('About');
    }

    public function donate()
    {
        return Inertia::render('Donate', [
            'walletAddress' => env('DONATE_WALLET_ADDRESS'),
        ]);
    }
}
