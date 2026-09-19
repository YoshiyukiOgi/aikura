<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table): void {
            $table->foreignId('carried_forward_to_invoice_header_id')
                ->nullable()
                ->after('invoice_header_id')
                ->constrained('invoice_headers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('carried_forward_to_invoice_header_id');
        });
    }
};
