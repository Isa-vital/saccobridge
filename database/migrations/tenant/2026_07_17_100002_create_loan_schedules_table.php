<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->unsignedInteger('installment_no');
            $table->date('due_date')->index();
            $table->decimal('principal_due', 20, 2)->default(0);
            $table->decimal('interest_due', 20, 2)->default(0);
            $table->decimal('fees_due', 20, 2)->default(0);
            $table->decimal('penalties_due', 20, 2)->default(0);
            $table->decimal('principal_paid', 20, 2)->default(0);
            $table->decimal('interest_paid', 20, 2)->default(0);
            $table->decimal('fees_paid', 20, 2)->default(0);
            $table->decimal('penalties_paid', 20, 2)->default(0);
            $table->decimal('balance_after', 20, 2)->default(0); // principal outstanding after this installment
            $table->boolean('is_settled')->default(false)->index();
            $table->date('settled_at')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'installment_no']);
            $table->index(['loan_id', 'is_settled', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_schedules');
    }
};
