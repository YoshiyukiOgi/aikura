<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_companies', function (Blueprint $table): void {
                $table->string('representative_name', 160)->nullable()->after('description');
                $table->string('postal_code', 20)->nullable()->after('representative_name');
                $table->string('address1')->nullable()->after('postal_code');
                $table->string('address2')->nullable()->after('address1');
                $table->string('phone', 30)->nullable()->after('address2');
                $table->string('fax', 30)->nullable()->after('phone');
                $table->string('email', 255)->nullable()->after('fax');
                $table->string('invoice_registration_number', 20)->nullable()->after('email');
            });
    }

    public function down(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_companies', function (Blueprint $table): void {
                $table->dropColumn([
                    'representative_name',
                    'postal_code',
                    'address1',
                    'address2',
                    'phone',
                    'fax',
                    'email',
                    'invoice_registration_number',
                ]);
            });
    }
};
