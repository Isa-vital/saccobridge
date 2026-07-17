<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique(); // LRP-000001
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->decimal('amount', 20, 2);
            $table->decimal('principal_portion', 20, 2)->default(0);
            $table->decimal('interest_portion', 20, 2)->default(0);
            $table->decimal('fees_portion', 20, 2)->default(0);
            $table->decimal('penalties_portion', 20, 2)->default(0);
            $table->enum('source', ['cash', 'savings'])->default('cash');
            $table->foreignId('savings_transaction_id')->nullable()->constrained('savings_transactions')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('teller_session_id')->nullable()->constrained('teller_sessions')->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('value_date')->index();
            $table->timestamps();

            $table->index(['loan_id', 'value_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
    }
};
