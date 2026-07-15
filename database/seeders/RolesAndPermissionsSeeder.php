<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds tenant roles & permissions per docs/08-roles-permissions-matrix.md.
 *
 * Runs inside the TENANT database (invoked by the TenantCreated pipeline
 * via TenantDatabaseSeeder).
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Members & KYC
            'members.view',
            'members.create',
            'members.update',
            'members.approve',
            // Savings
            'savings.view',
            'savings.open',
            'savings.deposit',
            'savings.withdraw',
            'savings.approve',
            // Loans
            'loans.view',
            'loans.create',
            'loans.appraise',
            'loans.approve',
            'loans.disburse',
            'loans.repay',
            // Shares & dividends
            'shares.view',
            'shares.post',
            'dividends.declare',
            // Accounting / GL
            'gl.view',
            'gl.post',
            'gl.manage_coa',
            'gl.close_period',
            // Reports
            'reports.view',
            'reports.export',
            // Administration
            'admin.users',
            'admin.roles',
            'admin.settings',
            'admin.audit',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        // SACCO Admin — full access within the SACCO
        Role::findOrCreate('sacco-admin')->syncPermissions(Permission::all());

        // Manager — approvals, oversight, reporting (maker-checker "checker")
        Role::findOrCreate('manager')->syncPermissions([
            'members.view',
            'members.approve',
            'savings.view',
            'savings.approve',
            'loans.view',
            'loans.appraise',
            'loans.approve',
            'loans.disburse',
            'shares.view',
            'dividends.declare',
            'gl.view',
            'reports.view',
            'reports.export',
            'admin.audit',
        ]);

        // Loan Officer — originates and appraises loans
        Role::findOrCreate('loan-officer')->syncPermissions([
            'members.view',
            'members.create',
            'members.update',
            'savings.view',
            'loans.view',
            'loans.create',
            'loans.appraise',
            'reports.view',
        ]);

        // Teller — front-office cash transactions (maker)
        Role::findOrCreate('teller')->syncPermissions([
            'members.view',
            'savings.view',
            'savings.deposit',
            'savings.withdraw',
            'loans.view',
            'loans.repay',
            'shares.view',
            'shares.post',
        ]);

        // Accountant — GL, period close, financial reports
        Role::findOrCreate('accountant')->syncPermissions([
            'members.view',
            'savings.view',
            'loans.view',
            'shares.view',
            'gl.view',
            'gl.post',
            'gl.manage_coa',
            'gl.close_period',
            'reports.view',
            'reports.export',
        ]);

        // Auditor — read-only everything + audit trail
        Role::findOrCreate('auditor')->syncPermissions([
            'members.view',
            'savings.view',
            'loans.view',
            'shares.view',
            'gl.view',
            'reports.view',
            'reports.export',
            'admin.audit',
        ]);

        // Member — self-service portal (permissions scoped in later phase)
        Role::findOrCreate('member');
    }
}
