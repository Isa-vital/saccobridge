<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dividends', function (Blueprint $table) {
            $table->id();
            $table->string('financial_year', 9); // e.g. 2026
            $table->foreignId('share_product_id')->constrained('share_products')->restrictOnDelete();
            $table->decimal('rate', 9, 6); // % of share value
            $table->decimal('total_declared', 20, 2)->default(0);
            $table->enum('status', ['declared', 'approved', 'distributed', 'cancelled'])->default('declared')->index();
            $table->foreignId('declared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('distributed_at')->nullable();
            $table->timestamps();

            $table->unique(['financial_year', 'share_product_id']);
        });

        Schema::create('dividend_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dividend_id')->constrained('dividends')->cascadeOnDelete();
            $table->foreignId('share_account_id')->constrained('share_accounts')->restrictOnDelete();
            $table->unsignedInteger('shares_held');
            $table->decimal('amount', 20, 2);
            $table->enum('method', ['savings_credit', 'payable'])->default('savings_credit');
            $table->foreignId('savings_transaction_id')->nullable()->constrained('savings_transactions')->nullOnDelete();
            $table->enum('status', ['pending', 'paid'])->default('pending')->index();
            $table->timestamps();

            $table->unique(['dividend_id', 'share_account_id']); // idempotent distribution
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dividend_payouts');
        Schema::dropIfExists('dividends');
    }
};
