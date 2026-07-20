<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_families', function (Blueprint $table): void {
            $table->id();
            $table->string('family_code', 80)->unique();
            $table->string('product_type', 50)->index();
            $table->string('name', 200)->index();
            $table->string('name_kana', 200)->nullable();
            $table->string('brand_name', 120)->nullable();
            $table->string('category_name', 120)->nullable();
            $table->foreignId('consumption_tax_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('alcohol_percentage', 5, 2)->nullable();
            $table->boolean('is_alcohol')->default(false);
            $table->string('liquor_tax_category_code', 80)->nullable();
            $table->string('liquor_type_name', 120)->nullable();
            $table->text('ingredients')->nullable();
            $table->decimal('rice_polishing_ratio', 5, 2)->nullable();
            $table->string('production_method', 120)->nullable();
            $table->boolean('is_unpasteurized')->default(false);
            $table->boolean('is_sales_available')->default(true);
            $table->boolean('is_inventory_managed')->default(true);
            $table->text('search_key')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampTz('disabled_at')->nullable();
            $table->timestampsTz();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('product_family_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->string('variant_label', 120)->nullable()->after('display_name');
            $table->index(['product_family_id', 'is_active']);
        });

        $units = DB::table('units')->pluck('symbol', 'id');
        $products = DB::table('products as p')
            ->leftJoin('sake_product_details as sd', 'sd.product_id', '=', 'p.id')
            ->orderBy('p.id')
            ->get([
                'p.*',
                'sd.liquor_tax_category_code', 'sd.liquor_type_name', 'sd.ingredients',
                'sd.rice_polishing_ratio', 'sd.production_method', 'sd.is_unpasteurized',
            ]);
        $families = [];
        $now = now();
        foreach ($products as $product) {
            $style = trim((string) ($product->style_name ?? ''));
            $familyName = trim((string) $product->name);
            if ($style !== '' && ! str_contains($familyName, $style)) {
                $familyName .= ' '.$style;
            }
            $key = json_encode([
                $product->product_type, $familyName, $product->brand_name, $product->category_name,
                $product->consumption_tax_category_id, $product->alcohol_percentage, $product->is_alcohol,
                $product->liquor_tax_category_code, $product->liquor_type_name, $product->ingredients,
                $product->rice_polishing_ratio, $product->production_method, $product->is_unpasteurized,
            ]);
            if (! isset($families[$key])) {
                $familyId = DB::table('product_families')->insertGetId([
                    'family_code' => 'LEGACY-F-'.$product->id,
                    'product_type' => $product->product_type,
                    'name' => mb_substr($familyName, 0, 200),
                    'name_kana' => $product->name_kana,
                    'brand_name' => $product->brand_name,
                    'category_name' => $product->category_name,
                    'consumption_tax_category_id' => $product->consumption_tax_category_id,
                    'alcohol_percentage' => $product->alcohol_percentage,
                    'is_alcohol' => $product->is_alcohol,
                    'liquor_tax_category_code' => $product->liquor_tax_category_code,
                    'liquor_type_name' => $product->liquor_type_name,
                    'ingredients' => $product->ingredients,
                    'rice_polishing_ratio' => $product->rice_polishing_ratio,
                    'production_method' => $product->production_method,
                    'is_unpasteurized' => (bool) ($product->is_unpasteurized ?? false),
                    'is_sales_available' => $product->is_sales_available,
                    'is_inventory_managed' => $product->is_inventory_managed,
                    'search_key' => implode(' ', array_filter([$familyName, $product->name_kana, $product->brand_name, $product->category_name])),
                    'note' => null,
                    'is_active' => $product->is_active,
                    'disabled_at' => $product->is_active ? null : ($product->disabled_at ?? $now),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $families[$key] = $familyId;
            } elseif ($product->is_active) {
                DB::table('product_families')->where('id', $families[$key])->update([
                    'is_active' => true,
                    'disabled_at' => null,
                    'updated_at' => $now,
                ]);
            }
            $capacity = $product->capacity_value === null
                ? null
                : rtrim(rtrim(number_format((float) $product->capacity_value, 4, '.', ''), '0'), '.').($units[$product->capacity_unit_id] ?? '');
            DB::table('products')->where('id', $product->id)->update([
                'product_family_id' => $families[$key],
                'variant_label' => $capacity,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['product_family_id', 'is_active']);
            $table->dropConstrainedForeignId('product_family_id');
            $table->dropColumn('variant_label');
        });
        Schema::dropIfExists('product_families');
    }
};
