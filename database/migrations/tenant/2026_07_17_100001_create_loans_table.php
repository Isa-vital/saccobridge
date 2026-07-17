<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_no', 20)->unique(); // LN-00001
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('loan_product_id')->constrained('loan_products')->restrictOnDelete();

            $table->decimal('applied_amount', 20, 2);
            $table->unsignedInteger('applied_term_months');
            $table->string('purpose')->nullable();

            $table->decimal('approved_amount', 20, 2)->nullable();
            $table->unsignedInteger('approved_term_months')->nullable();

            $table->decimal('principal_disbursed', 20, 2)->default(0);
            $table->decimal('principal_outstanding', 20, 2)->default(0);
            $table->decimal('interest_outstanding', 20, 2)->default(0);
            $table->decimal('fees_outstanding', 20, 2)->default(0);
            $table->decimal('penalties_outstanding', 20, 2)->default(0);

            $table->enum('status', [
                'draft', 'submitted', 'under_review', 'approved', 'rejected',
                'disbursed', 'active', 'closed', 'written_off',
            ])->default('draft')->index();

            // UMRA classification (FR-LNS-08)
            $table->enum('classification', ['performing', 'watch', 'substandard', 'doubtful', 'loss'])
                ->default('performing')->index();
            $table->unsignedInteger('days_in_arrears')->default(0);
            $table->decimal('provision_amount', 20, 2)->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->date('disbursed_at')->nullable();
            $table->date('first_payment_date')->nullable();
            $table->date('closed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
