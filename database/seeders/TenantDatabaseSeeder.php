<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder run automatically in each NEW tenant database
 * (wired to the TenantCreated pipeline in TenancyServiceProvider,
 * configured via tenancy.seeder_parameters).
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(ChartOfAccountsSeeder::class);
        $this->call(SavingsProductSeeder::class);
        $this->call(ShareProductSeeder::class);
    }
}
