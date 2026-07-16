<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->decimal('nominal_value', 20, 2); // par value per share
            $table->unsignedInteger('min_shares')->default(1);
            $table->unsignedInteger('max_shares')->nullable();
            $table->foreignId('gl_equity_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_products');
    }
};
