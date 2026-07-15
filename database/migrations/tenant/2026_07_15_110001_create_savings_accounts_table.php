<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_no', 20)->unique();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('savings_product_id')->constrained('savings_products')->restrictOnDelete();
            $table->decimal('balance', 20, 2)->default(0);
            $table->decimal('blocked_amount', 20, 2)->default(0); // loan pledges (Phase 6)
            $table->enum('status', ['active', 'dormant', 'closed'])->default('active')->index();
            $table->date('opened_at');
            $table->date('closed_at')->nullable();
            $table->timestamps();

            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_accounts');
    }
};
