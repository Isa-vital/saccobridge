<?php

// One-off: create demo SACCO admin user (run: php artisan tinker database/scripts/create_demo_admin.php)

$tenant = App\Models\Tenant::find('demo');

$tenant->run(function () {
    $user = App\Models\User::firstOrCreate(
        ['email' => 'admin@demo.test'],
        ['name' => 'Demo Admin', 'password' => bcrypt('Password@123')]
    );
    $user->syncRoles(['sacco-admin']);

    echo 'User: ' . $user->email . PHP_EOL;
    echo 'Roles: ' . $user->roles->pluck('name')->implode(', ') . PHP_EOL;
});
