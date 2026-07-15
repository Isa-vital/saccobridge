<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class CreateTenant extends Command
{
    protected $signature = 'tenants:create
                            {id : Tenant identifier, also used as the subdomain (e.g. demo)}
                            {name : Display name of the SACCO}';

    protected $description = 'Create a new SACCO tenant (database is created, migrated and seeded automatically)';

    public function handle(): int
    {
        $id = strtolower($this->argument('id'));
        $name = $this->argument('name');

        if (Tenant::find($id)) {
            $this->error("Tenant [{$id}] already exists.");

            return self::FAILURE;
        }

        $tenant = Tenant::create(['id' => $id, 'name' => $name]);
        $tenant->domains()->create(['domain' => $id]);

        $this->info("Tenant [{$id}] created.");
        $this->line('Subdomain: ' . $id . '.' . config('tenancy.central_domains')[0]);

        $tenant->run(function () {
            $this->line('Roles seeded: ' . \Spatie\Permission\Models\Role::count());
            $this->line('Permissions seeded: ' . \Spatie\Permission\Models\Permission::count());
        });

        return self::SUCCESS;
    }
}
