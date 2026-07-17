<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Monthly savings interest accrual on every tenant (last day of month, 23:00 EAT)
Schedule::command('tenants:run savings:post-interest')
    ->monthlyOn(1, '01:00')
    ->withoutOverlapping();

// Daily loan penalties + UMRA classification/provisioning on every tenant
Schedule::command('tenants:run loans:apply-penalties')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('tenants:run loans:classify')
    ->dailyAt('02:30')
    ->withoutOverlapping();
