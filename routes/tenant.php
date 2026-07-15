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

    // Accounting / General Ledger (Phase 3)
    Route::middleware(['auth', 'verified'])->prefix('gl')->name('gl.')->group(function () {
        Route::get('accounts', [\App\Http\Controllers\GlAccountController::class, 'index'])
            ->middleware('permission:gl.view')->name('accounts.index');
        Route::post('accounts', [\App\Http\Controllers\GlAccountController::class, 'store'])
            ->middleware('permission:gl.manage_coa')->name('accounts.store');
        Route::post('accounts/{account}/toggle', [\App\Http\Controllers\GlAccountController::class, 'toggle'])
            ->middleware('permission:gl.manage_coa')->name('accounts.toggle');
        Route::get('accounts/{account}/ledger', [\App\Http\Controllers\GlAccountController::class, 'ledger'])
            ->middleware('permission:gl.view')->name('accounts.ledger');

        Route::get('trial-balance', [\App\Http\Controllers\GlAccountController::class, 'trialBalance'])
            ->middleware('permission:gl.view')->name('trial-balance');

        Route::get('journal', [\App\Http\Controllers\JournalEntryController::class, 'index'])
            ->middleware('permission:gl.view')->name('journal.index');
        Route::get('journal/create', [\App\Http\Controllers\JournalEntryController::class, 'create'])
            ->middleware('permission:gl.post')->name('journal.create');
        Route::post('journal', [\App\Http\Controllers\JournalEntryController::class, 'store'])
            ->middleware('permission:gl.post')->name('journal.store');
        Route::get('journal/{journal}', [\App\Http\Controllers\JournalEntryController::class, 'show'])
            ->middleware('permission:gl.view')->name('journal.show');
        Route::post('journal/{journal}/reverse', [\App\Http\Controllers\JournalEntryController::class, 'reverse'])
            ->middleware('permission:gl.post')->name('journal.reverse');

        Route::get('periods', [\App\Http\Controllers\FinancialPeriodController::class, 'index'])
            ->middleware('permission:gl.view')->name('periods.index');
        Route::post('periods/{period}/close', [\App\Http\Controllers\FinancialPeriodController::class, 'close'])
            ->middleware('permission:gl.close_period')->name('periods.close');
        Route::post('periods/{period}/reopen', [\App\Http\Controllers\FinancialPeriodController::class, 'reopen'])
            ->middleware('permission:gl.close_period')->name('periods.reopen');
    });

    require __DIR__ . '/settings.php';
    require __DIR__ . '/auth.php';
});
