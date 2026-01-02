<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index']);

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('mod-sets', \App\Http\Controllers\ModSetController::class)->parameters([
        'mod-sets' => 'id',
    ]);
    Route::post('/mod-sets/{id}/items', [\App\Http\Controllers\ModSetController::class, 'storeItem'])->name('mod-sets.items.store');
    Route::delete('/mod-sets/{id}/items/{itemId}', [\App\Http\Controllers\ModSetController::class, 'destroyItem'])->name('mod-sets.items.destroy');
});
