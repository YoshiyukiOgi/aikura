<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->foreignId('consumption_tax_category_id')->nullable()->after('amount')->constrained('consumption_tax_categories')->restrictOnDelete();
            $table->string('consumption_tax_category_code', 80)->nullable()->after('consumption_tax_category_id');
            $table->string('consumption_tax_category_name', 120)->nullable()->after('consumption_tax_category_code');
            $table->string('consumption_taxability', 40)->nullable()->after('consumption_tax_category_name');
            $table->foreignId('consumption_tax_rate_id')->nullable()->after('consumption_taxability')->constrained('consumption_tax_rates')->restrictOnDelete();
            $table->date('consumption_tax_rate_effective_from')->nullable()->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('consumption_tax_rate_id');
            $table->dropConstrainedForeignId('consumption_tax_category_id');
            $table->dropColumn([
                'consumption_tax_category_code',
                'consumption_tax_category_name',
                'consumption_taxability',
                'consumption_tax_rate_effective_from',
            ]);
        });
    }
};
