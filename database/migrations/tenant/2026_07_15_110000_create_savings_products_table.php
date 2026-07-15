<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->decimal('interest_rate', 9, 6)->default(0); // annual %
            $table->enum('interest_basis', ['daily_balance', 'monthly_min_balance'])->default('daily_balance');
            $table->enum('interest_posting', ['monthly', 'quarterly', 'annually'])->default('monthly');
            $table->decimal('min_opening_deposit', 20, 2)->default(0);
            $table->decimal('min_balance', 20, 2)->default(0);
            $table->decimal('withdrawal_fee', 20, 2)->default(0);
            $table->unsignedInteger('max_withdrawals_per_month')->nullable();
            $table->foreignId('gl_liability_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('gl_interest_expense_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_products');
    }
};
