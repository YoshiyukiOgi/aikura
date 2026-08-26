<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('retail.database.connection', 'retail');

        Schema::connection($connection)->table('retail_delivery_lines', function (Blueprint $table): void {
            $table->decimal('tax_rate', 7, 4)->nullable()->after('unit_price');
        });

        DB::connection($connection)
            ->table('retail_delivery_lines')
            ->whereNotNull('retail_sale_item_id')
            ->orderBy('id')
            ->chunkById(500, function ($lines) use ($connection): void {
                $rates = DB::connection($connection)
                    ->table('retail_sale_items')
                    ->whereIn('id', $lines->pluck('retail_sale_item_id')->all())
                    ->pluck('tax_rate', 'id');

                foreach ($lines as $line) {
                    $taxRate = $rates->get($line->retail_sale_item_id);
                    if ($taxRate !== null) {
                        DB::connection($connection)
                            ->table('retail_delivery_lines')
                            ->where('id', $line->id)
                            ->update(['tax_rate' => $taxRate]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_delivery_lines', function (Blueprint $table): void {
                $table->dropColumn('tax_rate');
            });
    }
};
