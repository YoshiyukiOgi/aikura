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
                $table->string('primary_color', 7)->default('#0b6ff6')->after('system_name');
                $table->string('sidebar_color', 7)->default('#10243b')->after('primary_color');
            });
    }

    public function down(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_system_settings', function (Blueprint $table): void {
                $table->dropColumn(['primary_color', 'sidebar_color']);
            });
    }
};
