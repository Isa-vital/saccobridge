<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_movements', function (Blueprint $table) {
            $table->id();
            $table->enum('direction', ['vault_to_teller', 'teller_to_vault', 'bank_to_vault', 'vault_to_bank']);
            $table->decimal('amount', 20, 2);
            $table->foreignId('teller_session_id')->nullable()->constrained('teller_sessions')->nullOnDelete();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_movements');
    }
};
