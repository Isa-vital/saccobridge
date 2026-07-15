<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_no', 20)->unique();
            $table->enum('type', ['individual', 'group', 'institution'])->default('individual');
            $table->string('first_name', 100); // group/institution store their name here
            $table->string('last_name', 100)->nullable();
            $table->string('nin', 20)->nullable()->unique();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('phone', 20)->index();
            $table->string('email', 150)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('subcounty', 100)->nullable();
            $table->string('village', 100)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->enum('status', ['pending', 'active', 'dormant', 'exited'])
                ->default('pending')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->date('joined_at')->nullable();
            $table->date('exited_at')->nullable();
            $table->timestamps();

            $table->index(['first_name', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
