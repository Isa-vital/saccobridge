<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique(); // SHR-000001
            $table->foreignId('share_account_id')->constrained('share_accounts')->restrictOnDelete();
            $table->enum('type', ['purchase', 'transfer_in', 'transfer_out', 'redemption']);
            $table->unsignedInteger('shares');
            $table->decimal('amount', 20, 2); // shares * nominal_value
            $table->unsignedInteger('shares_after'); // register snapshot
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('counterparty_account_id')->nullable()->constrained('share_accounts')->nullOnDelete(); // transfers
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('value_date')->index();
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->index(['share_account_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_transactions');
    }
};
