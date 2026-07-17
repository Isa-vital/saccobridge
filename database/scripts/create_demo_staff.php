<?php

// One-off: create demo staff users for each role
// Run: php artisan tinker database/scripts/create_demo_staff.php

$tenant = App\Models\Tenant::find('demo');

$tenant->run(function () {
    $staff = [
        ['email' => 'admin@demo.test', 'name' => 'Demo Admin', 'role' => 'sacco-admin'],
        ['email' => 'manager@demo.test', 'name' => 'Mary Manager', 'role' => 'manager'],
        ['email' => 'teller@demo.test', 'name' => 'Tom Teller', 'role' => 'teller'],
        ['email' => 'officer@demo.test', 'name' => 'Lucy Loan-Officer', 'role' => 'loan-officer'],
        ['email' => 'accountant@demo.test', 'name' => 'Alice Accountant', 'role' => 'accountant'],
        ['email' => 'auditor@demo.test', 'name' => 'Andrew Auditor', 'role' => 'auditor'],
    ];

    foreach ($staff as $person) {
        $user = App\Models\User::firstOrCreate(
            ['email' => $person['email']],
            ['name' => $person['name'], 'password' => bcrypt('Password@123')]
        );
        $user->syncRoles([$person['role']]);
        echo str_pad($person['email'], 28) . ' → ' . $person['role'] . PHP_EOL;
    }
});
