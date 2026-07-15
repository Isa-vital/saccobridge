<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('gl_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->decimal('debit', 20, 2)->default(0);
            $table->decimal('credit', 20, 2)->default(0);
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->index(['gl_account_id', 'journal_entry_id']);
        });

        // Exactly one of debit/credit must be non-zero. sqlite (tests) cannot
        // ALTER TABLE ADD CONSTRAINT — there the invariant is enforced by
        // TransactionService validation instead.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT chk_debit_xor_credit CHECK ((debit = 0) <> (credit = 0))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
