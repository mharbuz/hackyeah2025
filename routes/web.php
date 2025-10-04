<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\PensionFactController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/api/pension-fact/random', [PensionFactController::class, 'random'])->name('pension-fact.random');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
