<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_liquor_tax_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_header_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('tax_treatment', 40)->index();
            $table->string('status', 40)->default('pending')->index();
            $table->string('evidence_reference', 160)->nullable();
            $table->date('evidence_date')->nullable();
            $table->string('destination', 160)->nullable();
            $table->string('customs_office', 160)->nullable();
            $table->string('exporter_type', 40)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::table('sales_return_lines', function (Blueprint $table): void {
            $table->string('liquor_tax_return_treatment', 40)->default('review')->index();
            $table->text('liquor_tax_return_reason')->nullable();
            $table->foreignId('liquor_tax_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('liquor_tax_reviewed_at')->nullable();
        });

        Schema::table('liquor_tax_monthly_filing_sources', function (Blueprint $table): void {
            $table->string('evidence_status', 40)->nullable();
            $table->string('evidence_reference', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('liquor_tax_monthly_filing_sources', function (Blueprint $table): void {
            $table->dropColumn(['evidence_status', 'evidence_reference']);
        });

        Schema::table('sales_return_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('liquor_tax_reviewed_by');
            $table->dropColumn(['liquor_tax_return_treatment', 'liquor_tax_return_reason', 'liquor_tax_reviewed_at']);
        });

        Schema::dropIfExists('shipment_liquor_tax_evidences');
    }
};
