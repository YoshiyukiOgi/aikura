<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_system_settings', function (Blueprint $table): void {
                $table->string('theme', 30)->default('blue')->after('system_name');
            });
    }

    public function down(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_system_settings', function (Blueprint $table): void {
                $table->dropColumn('theme');
            });
    }
};
