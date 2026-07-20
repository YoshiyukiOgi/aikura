<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_lots', function (Blueprint $table): void {
            $table->foreignId('unit_id')->nullable()->after('stock_location_id')->constrained('units')->restrictOnDelete();
            $table->decimal('capacity_value', 18, 4)->nullable()->after('unit_id');
            $table->foreignId('capacity_unit_id')->nullable()->after('capacity_value')->constrained('units')->restrictOnDelete();
            $table->decimal('alcohol_percentage', 5, 2)->nullable()->after('capacity_unit_id');
            $table->decimal('sake_meter_value', 6, 2)->nullable()->after('alcohol_percentage');
            $table->decimal('acidity', 6, 2)->nullable()->after('sake_meter_value');
            $table->decimal('amino_acidity', 6, 2)->nullable()->after('acidity');
            $table->date('analysis_date')->nullable()->after('amino_acidity');
            $table->string('analysis_status', 30)->nullable()->after('analysis_date');
            $table->index(['unit_id', 'capacity_value', 'capacity_unit_id'], 'production_lots_package_index');
        });

        DB::statement(<<<'SQL'
            UPDATE production_lots AS pl
            SET unit_id = p.inventory_unit_id,
                capacity_value = p.capacity_value,
                capacity_unit_id = p.capacity_unit_id,
                alcohol_percentage = p.alcohol_percentage,
                analysis_status = CASE WHEN p.alcohol_percentage IS NULL THEN NULL ELSE 'confirmed' END
            FROM products AS p
            WHERE p.id = pl.product_id
        SQL);

        Schema::table('shipment_lot_allocations', function (Blueprint $table): void {
            $table->foreignId('shipment_pick_line_id')->nullable()->after('shipment_line_id')->constrained('shipment_pick_lines')->nullOnDelete();
            $table->decimal('standard_alcohol_percentage', 5, 2)->nullable()->after('quantity');
            $table->decimal('actual_alcohol_percentage', 5, 2)->nullable()->after('standard_alcohol_percentage');
            $table->decimal('allowed_alcohol_min', 5, 2)->nullable()->after('actual_alcohol_percentage');
            $table->decimal('allowed_alcohol_max', 5, 2)->nullable()->after('allowed_alcohol_min');
            $table->string('alcohol_compliance_status', 30)->default('not_applicable')->after('allowed_alcohol_max')->index();
            $table->foreignId('approval_request_id')->nullable()->after('alcohol_compliance_status')->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('liquor_tax_category_id')->nullable()->after('approval_request_id')->constrained('liquor_tax_categories')->restrictOnDelete();
            $table->foreignId('liquor_tax_rule_id')->nullable()->after('liquor_tax_category_id')->constrained('liquor_tax_rules')->restrictOnDelete();
            $table->decimal('liquor_taxable_kl', 18, 6)->nullable()->after('liquor_tax_rule_id');
            $table->decimal('liquor_tax_per_kl', 18, 4)->nullable()->after('liquor_taxable_kl');
            $table->decimal('liquor_tax_estimated_amount', 18, 2)->nullable()->after('liquor_tax_per_kl');
            $table->index(['shipment_pick_line_id', 'status'], 'shipment_lot_allocations_pick_line_index');
        });

        Schema::table('shipment_lines', function (Blueprint $table): void {
            $table->foreignId('shipment_instruction_line_id')->nullable()->after('source_shipment_pick_line_id')->constrained('shipment_instruction_lines')->nullOnDelete();
            $table->index(['shipment_instruction_line_id', 'line_no'], 'shipment_lines_instruction_line_index');
        });
        DB::statement(<<<'SQL'
            UPDATE shipment_lines AS sl
            SET shipment_instruction_line_id = sil.id
            FROM shipment_headers AS sh
            JOIN shipment_instruction_lines AS sil
              ON sil.shipment_instruction_id = sh.source_shipment_instruction_id
            WHERE sh.id = sl.shipment_header_id
              AND sil.line_no = sl.line_no
              AND sil.product_id = sl.product_id
        SQL);

        DB::statement('ALTER TABLE stock_movements ALTER COLUMN product_id DROP NOT NULL');
        DB::statement('ALTER TABLE inventory_count_lines ALTER COLUMN product_id DROP NOT NULL');
        DB::statement('ALTER TABLE stock_lot_monthly_balances ALTER COLUMN product_id DROP NOT NULL');
        DB::statement('ALTER TABLE stock_lot_monthly_balances DROP CONSTRAINT stock_lot_monthly_balance_unique');
        DB::statement('CREATE UNIQUE INDEX stock_lot_monthly_balance_lot_unique ON stock_lot_monthly_balances (year, month, production_lot_id, stock_location_id, unit_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS stock_lot_monthly_balance_lot_unique');
        DB::statement('ALTER TABLE stock_lot_monthly_balances ADD CONSTRAINT stock_lot_monthly_balance_unique UNIQUE (year, month, production_lot_id, product_id, stock_location_id, unit_id)');
        DB::statement('ALTER TABLE stock_lot_monthly_balances ALTER COLUMN product_id SET NOT NULL');
        DB::statement('ALTER TABLE inventory_count_lines ALTER COLUMN product_id SET NOT NULL');
        DB::statement('ALTER TABLE stock_movements ALTER COLUMN product_id SET NOT NULL');

        Schema::table('shipment_lines', function (Blueprint $table): void {
            $table->dropIndex('shipment_lines_instruction_line_index');
            $table->dropConstrainedForeignId('shipment_instruction_line_id');
        });

        Schema::table('shipment_lot_allocations', function (Blueprint $table): void {
            $table->dropIndex('shipment_lot_allocations_pick_line_index');
            $table->dropConstrainedForeignId('shipment_pick_line_id');
            $table->dropConstrainedForeignId('approval_request_id');
            $table->dropConstrainedForeignId('liquor_tax_category_id');
            $table->dropConstrainedForeignId('liquor_tax_rule_id');
            $table->dropColumn([
                'standard_alcohol_percentage', 'actual_alcohol_percentage', 'allowed_alcohol_min',
                'allowed_alcohol_max', 'alcohol_compliance_status', 'liquor_taxable_kl',
                'liquor_tax_per_kl', 'liquor_tax_estimated_amount',
            ]);
        });

        Schema::table('production_lots', function (Blueprint $table): void {
            $table->dropIndex('production_lots_package_index');
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('capacity_unit_id');
            $table->dropColumn([
                'capacity_value', 'alcohol_percentage', 'sake_meter_value', 'acidity',
                'amino_acidity', 'analysis_date', 'analysis_status',
            ]);
        });
    }
};
