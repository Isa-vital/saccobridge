<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique(); // receipt no, e.g. RCT-000001
            $table->foreignId('savings_account_id')->constrained('savings_accounts')->restrictOnDelete();
            $table->enum('type', [
                'deposit', 'withdrawal', 'transfer_in', 'transfer_out',
                'interest', 'fee', 'dividend_credit', 'loan_disbursement', 'loan_repayment_debit',
            ]);
            $table->decimal('amount', 20, 2); // always positive
            $table->decimal('balance_after', 20, 2); // running balance snapshot
            $table->enum('status', ['completed', 'pending_approval', 'rejected'])->default('completed')->index();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('teller_session_id')->nullable()->constrained('teller_sessions')->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('value_date')->index();
            $table->string('memo')->nullable();
            $table->timestamps();

            // Hot paths: statements (account+date) and running history
            $table->index(['savings_account_id', 'value_date']);
            $table->index(['savings_account_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_transactions');
    }
};
