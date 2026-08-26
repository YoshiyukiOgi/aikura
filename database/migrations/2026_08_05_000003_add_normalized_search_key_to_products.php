<?php

use App\Support\SearchTextNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->text('search_key_normalized')->nullable()->after('search_key');
        });

        DB::table('products')->orderBy('id')->chunkById(200, function ($products): void {
            foreach ($products as $product) {
                DB::table('products')->where('id', $product->id)->update([
                    'search_key_normalized' => SearchTextNormalizer::productKey(
                        (string) $product->product_code,
                        (string) $product->name,
                        (string) ($product->name_kana ?? ''),
                        (string) $product->display_name,
                    ),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('search_key_normalized');
        });
    }
};
