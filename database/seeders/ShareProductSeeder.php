<?php

namespace Database\Seeders;

use App\Models\GlAccount;
use App\Models\ShareProduct;
use Illuminate\Database\Seeder;

class ShareProductSeeder extends Seeder
{
    public function run(): void
    {
        ShareProduct::firstOrCreate(['code' => 'ORD'], [
            'name' => 'Ordinary Shares',
            'nominal_value' => 10000,
            'min_shares' => 5,
            'max_shares' => null,
            'gl_equity_account_id' => GlAccount::byCode('3010')->id,
        ]);
    }
}
