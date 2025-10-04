<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\PensionFactController;
use App\Http\Controllers\PensionSimulationController;
use App\Http\Controllers\TestController;

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/api/pension-fact/random', [PensionFactController::class, 'random'])->name('pension-fact.random');

// Pension simulation routes
Route::post('/api/pension-simulation', [PensionSimulationController::class, 'store'])->name('pension.simulation.store');
Route::get('/', [PensionSimulationController::class, 'show'])->name('home');
// Symulacja przyszłej emerytury
Route::get('/symulacja-emerytury', [PensionSimulationController::class, 'index'])->name('pension-simulation.index');
Route::post('/api/pension/simulate', [PensionSimulationController::class, 'simulate'])->name('pension-simulation.simulate');

// Test routes
Route::prefix('test')->group(function () {
    Route::get('/', [TestController::class, 'index'])->name('test.index');
    Route::get('/debug', [TestController::class, 'debug'])->name('test.debug');
    Route::get('/{id}', [TestController::class, 'show'])->name('test.show');
    Route::post('/', [TestController::class, 'store'])->name('test.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
