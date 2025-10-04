<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminLoginController;
use App\Http\Controllers\AdvancedPensionForecastController;
use App\Http\Controllers\PensionFactController;
use App\Http\Controllers\PensionSimulationController;
use App\Http\Controllers\TestController;

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Przekierowanie standardowych route logowania do admina
Route::get('/login', function () {
    return redirect()->route('admin.login');
})->name('login');

Route::get('/register', function () {
    return redirect()->route('admin.login');
})->name('register');

// Admin login routes (bez middleware)
Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminLoginController::class, 'show'])->name('admin.login');
    Route::post('/login', [AdminLoginController::class, 'login'])->name('admin.login.post');
    Route::post('/logout', [AdminLoginController::class, 'logout'])->name('admin.logout');
});

// Admin routes - wymagają uwierzytelnienia i uprawnień admina
Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () {
    // Przekierowanie z /admin na /admin/dashboard
    Route::get('/', function () {
        return redirect()->route('admin.dashboard');
    });
    
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/export-report', [AdminDashboardController::class, 'exportReport'])->name('admin.export-report');
    Route::get('/simulation/{id}', [AdminDashboardController::class, 'show'])->name('admin.simulation.show');
});

Route::get('/api/pension-fact/random', [PensionFactController::class, 'random'])->name('pension-fact.random');

// Pension simulation routes
Route::post('/api/pension-simulation', [PensionSimulationController::class, 'store'])->name('pension.simulation.store');
Route::get('/', [PensionSimulationController::class, 'show'])->name('home');
// Symulacja przyszłej emerytury
Route::get('/symulacja-emerytury', [PensionSimulationController::class, 'index'])->name('pension-simulation.index');
Route::post('/api/pension/simulate', [PensionSimulationController::class, 'simulate'])->name('pension-simulation.simulate');

// Zaawansowany dashboard prognozowania
Route::get('/dashboard-prognozowania', [AdvancedPensionForecastController::class, 'index'])->name('pension-forecast-dashboard.index');
Route::post('/api/pension/advanced-simulate', [AdvancedPensionForecastController::class, 'simulate'])->name('pension-forecast-dashboard.simulate');
Route::get('/api/pension/pdf-report', [AdvancedPensionForecastController::class, 'generatePdfReport'])->name('pension-forecast-dashboard.generate-pdf');

// Test routes
Route::prefix('test')->group(function () {
    Route::get('/', [TestController::class, 'index'])->name('test.index');
    Route::get('/debug', [TestController::class, 'debug'])->name('test.debug');
    Route::get('/{id}', [TestController::class, 'show'])->name('test.show');
    Route::post('/', [TestController::class, 'store'])->name('test.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
