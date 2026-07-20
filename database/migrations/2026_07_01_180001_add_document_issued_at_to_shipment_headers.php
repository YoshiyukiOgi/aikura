<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_headers', function (Blueprint $table): void {
            $table->timestamp('document_issued_at')->nullable()->after('document_date');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_headers', function (Blueprint $table): void {
            $table->dropColumn('document_issued_at');
        });
    }
};
