<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('non_sales_stock_operation_headers', function (Blueprint $table): void {
            $table->unsignedInteger('revision_no')->default(1)->after('operation_number');
        });

        Schema::create('non_sales_stock_operation_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('non_sales_stock_operation_header_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->string('action', 30);
            $table->string('operation_type', 50);
            $table->date('operation_date');
            $table->text('reason');
            $table->text('note')->nullable();
            $table->jsonb('lines');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['non_sales_stock_operation_header_id', 'revision_no'], 'non_sales_operation_revision_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_sales_stock_operation_revisions');
        Schema::table('non_sales_stock_operation_headers', function (Blueprint $table): void {
            $table->dropColumn('revision_no');
        });
    }
};
