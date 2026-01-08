<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index']);
Route::get('/about', [LandingController::class, 'about'])->name('about');
Route::get('/ads', [LandingController::class, 'ads'])->name('ads');

// Public shared modpack routes
Route::get('/shared/{token}', [\App\Http\Controllers\ModPackController::class, 'showShared'])->name('mod-packs.shared.show');
Route::get('/shared/{token}/download-links', [\App\Http\Controllers\ModPackController::class, 'getSharedDownloadLinks'])->name('mod-packs.shared.download-links');
Route::post('/shared/{token}/bulk-download-links', [\App\Http\Controllers\ModPackController::class, 'getSharedBulkDownloadLinks'])->name('mod-packs.shared.bulk-download-links');
Route::get('/shared/{token}/items/{itemId}/download-link', [\App\Http\Controllers\ModPackController::class, 'getSharedItemDownloadLink'])->name('mod-packs.shared.items.download-link');
Route::get('/shared/{token}/proxy-download', [\App\Http\Controllers\ModPackController::class, 'sharedProxyDownload'])->name('mod-packs.shared.proxy-download');

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [AuthController::class, 'showProfile'])->name('profile');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
    Route::get('/change-password', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('password.change.update');

    Route::resource('mod-packs', \App\Http\Controllers\ModPackController::class)->parameters([
        'mod-packs' => 'id',
    ]);
    Route::get('/mod-packs/{id}/search-mods', [\App\Http\Controllers\ModPackController::class, 'searchMods'])->name('mod-packs.search-mods');
    Route::get('/mod-packs/{id}/mod-files', [\App\Http\Controllers\ModPackController::class, 'getModFiles'])->name('mod-packs.mod-files');
    Route::post('/mod-packs/{id}/items', [\App\Http\Controllers\ModPackController::class, 'storeItem'])->name('mod-packs.items.store');
    Route::put('/mod-packs/{id}/items/{itemId}', [\App\Http\Controllers\ModPackController::class, 'updateItem'])->name('mod-packs.items.update');
    Route::delete('/mod-packs/{id}/items/{itemId}', [\App\Http\Controllers\ModPackController::class, 'destroyItem'])->name('mod-packs.items.destroy');
    Route::get('/mod-packs/{id}/items/preview-all-to-latest', [\App\Http\Controllers\ModPackController::class, 'previewAllItemsToLatest'])->name('mod-packs.items.preview-all-to-latest');
    Route::post('/mod-packs/{id}/items/preview-bulk-to-latest', [\App\Http\Controllers\ModPackController::class, 'previewBulkItemsToLatest'])->name('mod-packs.items.preview-bulk-to-latest');
    Route::post('/mod-packs/{id}/items/update-all-to-latest', [\App\Http\Controllers\ModPackController::class, 'updateAllItemsToLatest'])->name('mod-packs.items.update-all-to-latest');
    Route::post('/mod-packs/{id}/bulk-items/update-to-latest', [\App\Http\Controllers\ModPackController::class, 'updateBulkItemsToLatest'])->name('mod-packs.bulk-items.update-to-latest');
    Route::get('/mod-packs/{id}/download-links', [\App\Http\Controllers\ModPackController::class, 'getDownloadLinks'])->name('mod-packs.download-links');
    Route::post('/mod-packs/{id}/bulk-download-links', [\App\Http\Controllers\ModPackController::class, 'getBulkDownloadLinks'])->name('mod-packs.bulk-download-links');
    Route::get('/mod-packs/{id}/items/{itemId}/download-link', [\App\Http\Controllers\ModPackController::class, 'getItemDownloadLink'])->name('mod-packs.items.download-link');
    Route::get('/mod-packs/{id}/proxy-download', [\App\Http\Controllers\ModPackController::class, 'proxyDownload'])->name('mod-packs.proxy-download');
    Route::post('/mod-packs/{id}/bulk-items/delete', [\App\Http\Controllers\ModPackController::class, 'destroyBulkItems'])->name('mod-packs.bulk-items.destroy');
    Route::post('/mod-packs/{id}/change-version', [\App\Http\Controllers\ModPackController::class, 'changeVersion'])->name('mod-packs.change-version');
    Route::post('/mod-packs/{id}/set-reminder', [\App\Http\Controllers\ModPackController::class, 'setReminder'])->name('mod-packs.set-reminder');
    Route::post('/mod-packs/{id}/cancel-reminder', [\App\Http\Controllers\ModPackController::class, 'cancelReminder'])->name('mod-packs.cancel-reminder');
    Route::post('/mod-packs/{id}/share', [\App\Http\Controllers\ModPackController::class, 'generateShareToken'])->name('mod-packs.share');
    Route::post('/mod-packs/{id}/duplicate', [\App\Http\Controllers\ModPackController::class, 'duplicate'])->name('mod-packs.duplicate');
    Route::post('/mod-packs/{id}/items/reorder', [\App\Http\Controllers\ModPackController::class, 'reorderItems'])->name('mod-packs.items.reorder');
    Route::post('/shared/{token}/add-to-collection', [\App\Http\Controllers\ModPackController::class, 'addToCollection'])->name('mod-packs.shared.add-to-collection');
});
