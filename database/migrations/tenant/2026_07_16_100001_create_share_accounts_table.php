<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('share_product_id')->constrained('share_products')->restrictOnDelete();
            $table->unsignedInteger('shares_count')->default(0);
            $table->decimal('value', 20, 2)->default(0); // shares_count * nominal_value
            $table->enum('status', ['active', 'closed'])->default('active')->index();
            $table->timestamps();

            $table->unique(['member_id', 'share_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_accounts');
    }
};
