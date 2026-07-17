<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->decimal('interest_rate', 9, 6); // annual %
            $table->enum('interest_method', ['flat', 'reducing_balance']);
            $table->unsignedInteger('min_term_months')->default(1);
            $table->unsignedInteger('max_term_months')->default(60);
            $table->decimal('min_amount', 20, 2)->default(0);
            $table->decimal('max_amount', 20, 2)->nullable();
            $table->unsignedInteger('grace_period_days')->default(0);
            $table->decimal('application_fee', 20, 2)->default(0); // fixed
            $table->decimal('processing_fee_percent', 9, 6)->default(0); // % of principal
            $table->decimal('penalty_rate', 9, 6)->default(0); // % per month on overdue amount
            $table->unsignedInteger('required_guarantors')->default(0);
            $table->decimal('savings_multiple', 9, 2)->nullable(); // loan <= N x member savings
            $table->foreignId('gl_portfolio_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('gl_interest_income_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('gl_fee_income_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('gl_penalty_income_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
