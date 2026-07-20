<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_review_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('changed_price_rule_id')->constrained('price_rules')->restrictOnDelete();
            $table->foreignId('affected_price_rule_id')->constrained('price_rules')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('transaction_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('old_reference_price', 18, 4);
            $table->decimal('new_reference_price', 18, 4);
            $table->decimal('current_individual_price', 18, 4);
            $table->string('status', 30)->default('pending');
            $table->text('message')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['changed_price_rule_id', 'affected_price_rule_id'], 'price_review_tasks_rule_pair_unique');
            $table->index(['status', 'created_at']);
            $table->index(['product_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_review_tasks');
    }
};
