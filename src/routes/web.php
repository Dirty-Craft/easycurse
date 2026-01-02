<?php

use App\Http\Controllers\AuthController;
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

    Route::resource('mod-packs', \App\Http\Controllers\ModPackController::class)->parameters([
        'mod-packs' => 'id',
    ]);
    Route::get('/mod-packs/{id}/search-mods', [\App\Http\Controllers\ModPackController::class, 'searchMods'])->name('mod-packs.search-mods');
    Route::get('/mod-packs/{id}/mod-files', [\App\Http\Controllers\ModPackController::class, 'getModFiles'])->name('mod-packs.mod-files');
    Route::post('/mod-packs/{id}/items', [\App\Http\Controllers\ModPackController::class, 'storeItem'])->name('mod-packs.items.store');
    Route::delete('/mod-packs/{id}/items/{itemId}', [\App\Http\Controllers\ModPackController::class, 'destroyItem'])->name('mod-packs.items.destroy');
    Route::get('/mod-packs/{id}/download-links', [\App\Http\Controllers\ModPackController::class, 'getDownloadLinks'])->name('mod-packs.download-links');
    Route::get('/mod-packs/{id}/items/{itemId}/download-link', [\App\Http\Controllers\ModPackController::class, 'getItemDownloadLink'])->name('mod-packs.items.download-link');
    Route::get('/mod-packs/{id}/proxy-download', [\App\Http\Controllers\ModPackController::class, 'proxyDownload'])->name('mod-packs.proxy-download');
    Route::post('/mod-packs/{id}/change-version', [\App\Http\Controllers\ModPackController::class, 'changeVersion'])->name('mod-packs.change-version');
});
