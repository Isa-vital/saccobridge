<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Central Routes
|--------------------------------------------------------------------------
| These routes are served ONLY on the central domain (saccobridge.test).
| The SACCO application itself lives in routes/tenant.php and is served
| on tenant subdomains (e.g. demo.saccobridge.test).
*/

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/', function () {
            return Inertia::render('Welcome');
        })->name('home');

        // Platform admin (Super Admin) routes will be registered here in a later phase.
    });
}

// CHANGED: moved dashboard + auth + settings routes to routes/tenant.php
// so they run in tenant (SACCO) context, not on the central domain.
// Route::get('/', function () {
//     return Inertia::render('Welcome');
// })->name('home');
//
// Route::get('dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');
//
// require __DIR__.'/settings.php';
// require __DIR__.'/auth.php';
