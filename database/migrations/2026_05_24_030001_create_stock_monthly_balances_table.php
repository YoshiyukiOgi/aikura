<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_monthly_balances', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 40)->default('draft');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('opening_quantity', 18, 4)->default(0);
            $table->decimal('inbound_quantity', 18, 4)->default(0);
            $table->decimal('outbound_quantity', 18, 4)->default(0);
            $table->decimal('adjustment_quantity', 18, 4)->default(0);
            $table->decimal('closing_quantity', 18, 4)->default(0);
            $table->timestampTz('calculated_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['year', 'month', 'product_id', 'stock_location_id', 'unit_id']);
            $table->index('status');
            $table->index(['period_start', 'period_end']);
            $table->index(['product_id', 'stock_location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_monthly_balances');
    }
};
