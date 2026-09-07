<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_liquor_tax_evidences', function (Blueprint $table): void {
            $table->string('document_file_path')->nullable()->after('note');
            $table->string('document_file_name', 255)->nullable()->after('document_file_path');
            $table->unsignedBigInteger('document_file_size')->nullable()->after('document_file_name');
            $table->string('document_mime_type', 160)->nullable()->after('document_file_size');
            $table->char('document_checksum_sha256', 64)->nullable()->after('document_mime_type');
        });

        Schema::table('liquor_tax_monthly_filing_sources', function (Blueprint $table): void {
            $table->unsignedBigInteger('shipment_liquor_tax_evidence_id')->nullable()->after('evidence_reference');
            $table->string('evidence_document_file_name', 255)->nullable()->after('shipment_liquor_tax_evidence_id');
            $table->string('evidence_document_mime_type', 160)->nullable()->after('evidence_document_file_name');
        });
    }

    public function down(): void
    {
        Schema::table('liquor_tax_monthly_filing_sources', function (Blueprint $table): void {
            $table->dropColumn([
                'shipment_liquor_tax_evidence_id',
                'evidence_document_file_name',
                'evidence_document_mime_type',
            ]);
        });

        Schema::table('shipment_liquor_tax_evidences', function (Blueprint $table): void {
            $table->dropColumn([
                'document_file_path',
                'document_file_name',
                'document_file_size',
                'document_mime_type',
                'document_checksum_sha256',
            ]);
        });
    }
};
