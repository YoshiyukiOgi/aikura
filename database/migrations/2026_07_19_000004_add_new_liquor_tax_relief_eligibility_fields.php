<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquor_tax_relief_settings', function (Blueprint $table): void {
            $table->decimal('prior_year_total_taxable_quantity_kl', 18, 6)->nullable();
            $table->date('approval_date')->nullable();
            $table->string('approval_reference', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('liquor_tax_relief_settings', function (Blueprint $table): void {
            $table->dropColumn(['prior_year_total_taxable_quantity_kl', 'approval_date', 'approval_reference']);
        });
    }
};
