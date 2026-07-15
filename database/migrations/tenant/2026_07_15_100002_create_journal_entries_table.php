<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique(); // JE-000001
            $table->date('entry_date')->index();
            $table->foreignId('financial_period_id')->constrained('financial_periods')->restrictOnDelete();
            $table->string('description');
            $table->nullableMorphs('source'); // drill-down to origin (member, savings txn, loan, …)
            $table->enum('status', ['posted', 'reversed'])->default('posted');
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
