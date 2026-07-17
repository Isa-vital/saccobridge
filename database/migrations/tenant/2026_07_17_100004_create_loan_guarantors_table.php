<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete(); // the guarantor
            $table->foreignId('savings_account_id')->nullable()->constrained('savings_accounts')->nullOnDelete();
            $table->decimal('guaranteed_amount', 20, 2); // amount blocked in their savings
            $table->enum('status', ['pledged', 'released'])->default('pledged')->index();
            $table->timestamps();

            $table->unique(['loan_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_guarantors');
    }
};
