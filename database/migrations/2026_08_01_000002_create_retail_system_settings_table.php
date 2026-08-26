<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('retail.database.connection', 'retail'));

        $schema->create('retail_system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('system_name', 160)->default('小売販売システム');
            $table->timestampsTz();
        });

        DB::connection(config('retail.database.connection', 'retail'))->table('retail_system_settings')->insert([
            'system_name' => '小売販売システム',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))->dropIfExists('retail_system_settings');
    }
};
