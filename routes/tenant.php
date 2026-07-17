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

    // Savings (Phase 4)
    Route::middleware(['auth', 'verified'])->prefix('savings')->name('savings.')->group(function () {
        Route::get('products', [\App\Http\Controllers\SavingsProductController::class, 'index'])
            ->middleware('permission:savings.view')->name('products.index');
        Route::post('products', [\App\Http\Controllers\SavingsProductController::class, 'store'])
            ->middleware('permission:admin.settings')->name('products.store');
        Route::post('products/{product}/toggle', [\App\Http\Controllers\SavingsProductController::class, 'toggle'])
            ->middleware('permission:admin.settings')->name('products.toggle');

        Route::get('accounts', [\App\Http\Controllers\SavingsAccountController::class, 'index'])
            ->middleware('permission:savings.view')->name('accounts.index');
        Route::post('accounts', [\App\Http\Controllers\SavingsAccountController::class, 'store'])
            ->middleware('permission:savings.open')->name('accounts.store');
        Route::get('accounts/{account}', [\App\Http\Controllers\SavingsAccountController::class, 'show'])
            ->middleware('permission:savings.view')->name('accounts.show');

        Route::get('teller', [\App\Http\Controllers\TellerController::class, 'station'])
            ->middleware('permission:savings.view')->name('teller.station');
        Route::post('teller/deposit', [\App\Http\Controllers\TellerController::class, 'deposit'])
            ->middleware('permission:savings.deposit')->name('teller.deposit');
        Route::post('teller/withdraw', [\App\Http\Controllers\TellerController::class, 'withdraw'])
            ->middleware('permission:savings.withdraw')->name('teller.withdraw');

        Route::get('approvals', [\App\Http\Controllers\TellerController::class, 'approvals'])
            ->middleware('permission:savings.approve')->name('approvals.index');
        Route::post('approvals/{transaction}/approve', [\App\Http\Controllers\TellerController::class, 'approve'])
            ->middleware('permission:savings.approve')->name('approvals.approve');
        Route::post('approvals/{transaction}/reject', [\App\Http\Controllers\TellerController::class, 'reject'])
            ->middleware('permission:savings.approve')->name('approvals.reject');

        Route::get('sessions', [\App\Http\Controllers\TellerSessionController::class, 'index'])
            ->middleware('permission:savings.view')->name('sessions.index');
        Route::post('sessions', [\App\Http\Controllers\TellerSessionController::class, 'open'])
            ->middleware('permission:savings.approve')->name('sessions.open');
        Route::post('sessions/{session}/close', [\App\Http\Controllers\TellerSessionController::class, 'close'])
            ->name('sessions.close'); // ownership checked in controller
        Route::post('sessions/{session}/reconcile', [\App\Http\Controllers\TellerSessionController::class, 'reconcile'])
            ->middleware('permission:savings.approve')->name('sessions.reconcile');
    });

    // Shares & dividends (Phase 5)
    Route::middleware(['auth', 'verified'])->prefix('shares')->name('shares.')->group(function () {
        Route::get('register', [\App\Http\Controllers\ShareController::class, 'register'])
            ->middleware('permission:shares.view')->name('register');
        Route::post('purchase', [\App\Http\Controllers\ShareController::class, 'purchase'])
            ->middleware('permission:shares.post')->name('purchase');
        Route::post('{account}/transfer', [\App\Http\Controllers\ShareController::class, 'transfer'])
            ->middleware('permission:shares.post')->name('transfer');
        Route::post('{account}/redeem', [\App\Http\Controllers\ShareController::class, 'redeem'])
            ->middleware('permission:shares.post')->name('redeem');

        Route::get('products', [\App\Http\Controllers\ShareController::class, 'products'])
            ->middleware('permission:shares.view')->name('products');
        Route::post('products', [\App\Http\Controllers\ShareController::class, 'storeProduct'])
            ->middleware('permission:admin.settings')->name('products.store');

        Route::get('dividends', [\App\Http\Controllers\DividendController::class, 'index'])
            ->middleware('permission:shares.view')->name('dividends.index');
        Route::post('dividends', [\App\Http\Controllers\DividendController::class, 'declare'])
            ->middleware('permission:dividends.declare')->name('dividends.declare');
        Route::post('dividends/{dividend}/approve', [\App\Http\Controllers\DividendController::class, 'approve'])
            ->middleware('permission:dividends.declare')->name('dividends.approve');
        Route::post('dividends/{dividend}/distribute', [\App\Http\Controllers\DividendController::class, 'distribute'])
            ->middleware('permission:dividends.declare')->name('dividends.distribute');
    });

    // Loans (Phase 6)
    Route::middleware(['auth', 'verified'])->prefix('loans')->name('loans.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LoanController::class, 'index'])
            ->middleware('permission:loans.view')->name('index');
        Route::get('create', [\App\Http\Controllers\LoanController::class, 'create'])
            ->middleware('permission:loans.create')->name('create');
        Route::post('/', [\App\Http\Controllers\LoanController::class, 'store'])
            ->middleware('permission:loans.create')->name('store');

        Route::get('products', [\App\Http\Controllers\LoanController::class, 'products'])
            ->middleware('permission:loans.view')->name('products');
        Route::post('products', [\App\Http\Controllers\LoanController::class, 'storeProduct'])
            ->middleware('permission:admin.settings')->name('products.store');

        Route::get('{loan}', [\App\Http\Controllers\LoanController::class, 'show'])
            ->middleware('permission:loans.view')->name('show');
        Route::post('{loan}/approve', [\App\Http\Controllers\LoanController::class, 'approve'])
            ->middleware('permission:loans.approve')->name('approve');
        Route::post('{loan}/reject', [\App\Http\Controllers\LoanController::class, 'reject'])
            ->middleware('permission:loans.approve')->name('reject');
        Route::post('{loan}/disburse', [\App\Http\Controllers\LoanController::class, 'disburse'])
            ->middleware('permission:loans.disburse')->name('disburse');
        Route::post('{loan}/repay', [\App\Http\Controllers\LoanController::class, 'repay'])
            ->middleware('permission:loans.repay')->name('repay');
    });

    // UMRA Reports (Phase 7)
    Route::middleware(['auth', 'verified', 'permission:reports.view'])->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ReportController::class, 'index'])->name('index');
        Route::get('balance-sheet', [\App\Http\Controllers\ReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('income-statement', [\App\Http\Controllers\ReportController::class, 'incomeStatement'])->name('income-statement');
        Route::get('par', [\App\Http\Controllers\ReportController::class, 'par'])->name('par');
        Route::get('savings-summary', [\App\Http\Controllers\ReportController::class, 'savingsSummary'])->name('savings-summary');
        Route::get('{report}/export/{format}', [\App\Http\Controllers\ReportController::class, 'export'])->name('export');
    });

    require __DIR__ . '/settings.php';
    require __DIR__ . '/auth.php';
});
