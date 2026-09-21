<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_headers', function (Blueprint $table): void {
            $table->unsignedSmallInteger('legacy_access_billing_year')->nullable()->after('legacy_access_document_number');
            $table->unsignedTinyInteger('legacy_access_billing_month')->nullable()->after('legacy_access_billing_year');
            $table->index(['legacy_access_billing_year', 'legacy_access_billing_month'], 'shipment_headers_access_billing_period_index');
        });

        DB::statement(<<<'SQL'
            UPDATE shipment_headers AS shipments
            SET
                legacy_access_billing_year = CAST(source.payload->>'請求年' AS integer),
                legacy_access_billing_month = CAST(source.payload->>'請求月' AS integer)
            FROM (
                SELECT DISTINCT ON (source_key) source_key, payload
                FROM access_migration_staging_rows
                WHERE source_table = '出荷伝票・取引先'
                  AND payload->>'請求年' ~ '^[0-9]{4}$'
                  AND payload->>'請求月' ~ '^(1[0-2]|[1-9])$'
                ORDER BY source_key, batch_id DESC, id DESC
            ) AS source
            WHERE shipments.legacy_access_document_number = source.source_key
        SQL);
    }

    public function down(): void
    {
        Schema::table('shipment_headers', function (Blueprint $table): void {
            $table->dropIndex('shipment_headers_access_billing_period_index');
            $table->dropColumn(['legacy_access_billing_year', 'legacy_access_billing_month']);
        });
    }
};
