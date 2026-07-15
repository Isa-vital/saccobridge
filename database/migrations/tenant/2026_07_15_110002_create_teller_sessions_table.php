<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teller_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // the teller
            $table->decimal('opening_float', 20, 2);
            $table->decimal('closing_declared', 20, 2)->nullable(); // cash counted by teller
            $table->decimal('closing_system', 20, 2)->nullable();   // expected per system
            $table->decimal('variance', 20, 2)->nullable();
            $table->enum('status', ['open', 'closed', 'reconciled'])->default('open')->index();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete(); // supervisor issuing float
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teller_sessions');
    }
};
