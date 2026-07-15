<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
| Served on tenant subdomains (e.g. demo.saccobridge.test). Tenancy is
| initialized by subdomain, switching the DB connection to the tenant's
| own database before any of these routes run.
*/

Route::middleware([
    'web',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', function () {
        return redirect()->route('login');
    });

    // CHANGED: dashboard now renders live stats via controller
    // Route::get('dashboard', function () {
    //     return Inertia::render('Dashboard');
    // })->middleware(['auth', 'verified'])->name('dashboard');
    Route::get('dashboard', \App\Http\Controllers\DashboardController::class)
        ->middleware(['auth', 'verified'])->name('dashboard');

    // Members & KYC (Phase 2)
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('members', [\App\Http\Controllers\MemberController::class, 'index'])
            ->middleware('permission:members.view')->name('members.index');
        Route::get('members/create', [\App\Http\Controllers\MemberController::class, 'create'])
            ->middleware('permission:members.create')->name('members.create');
        Route::post('members', [\App\Http\Controllers\MemberController::class, 'store'])
            ->middleware('permission:members.create')->name('members.store');
        Route::get('members/{member}', [\App\Http\Controllers\MemberController::class, 'show'])
            ->middleware('permission:members.view')->name('members.show');
        Route::get('members/{member}/edit', [\App\Http\Controllers\MemberController::class, 'edit'])
            ->middleware('permission:members.update')->name('members.edit');
        Route::put('members/{member}', [\App\Http\Controllers\MemberController::class, 'update'])
            ->middleware('permission:members.update')->name('members.update');
        Route::post('members/{member}/approve', [\App\Http\Controllers\MemberController::class, 'approve'])
            ->middleware('permission:members.approve')->name('members.approve');
    });

    require __DIR__ . '/settings.php';
    require __DIR__ . '/auth.php';
});
