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
        Schema::table('customers', function (Blueprint $table): void {
            $table->text('search_key_normalized')->nullable()->after('search_key');
        });

        Schema::table('product_families', function (Blueprint $table): void {
            $table->text('search_key_normalized')->nullable()->after('search_key');
        });

        DB::table('customers')->orderBy('id')->chunkById(200, function ($customers): void {
            foreach ($customers as $customer) {
                DB::table('customers')->where('id', $customer->id)->update([
                    'search_key_normalized' => SearchTextNormalizer::normalize((string) ($customer->search_key ?? '')),
                ]);
            }
        });

        DB::table('product_families')->orderBy('id')->chunkById(200, function ($families): void {
            foreach ($families as $family) {
                DB::table('product_families')->where('id', $family->id)->update([
                    'search_key_normalized' => SearchTextNormalizer::normalize((string) ($family->search_key ?? '')),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_families', function (Blueprint $table): void {
            $table->dropColumn('search_key_normalized');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('search_key_normalized');
        });
    }
};
