<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connectionName = config('retail.database.connection', 'retail');

        Schema::connection($connectionName)->table('retail_sales', function (Blueprint $table): void {
            $table->foreignId('retail_company_id')
                ->nullable()
                ->after('id')
                ->constrained('retail_companies')
                ->nullOnDelete();
        });

        $connection = DB::connection($connectionName);
        $connection->statement(<<<'SQL'
            UPDATE retail_sales AS sales
            SET retail_company_id = customers.retail_company_id
            FROM retail_customers AS customers
            WHERE sales.retail_customer_id = customers.id
              AND customers.retail_company_id IS NOT NULL
              AND sales.retail_company_id IS NULL
        SQL);

        $defaultCompanyId = $connection->table('retail_companies')
            ->where('company_key', 'maru')
            ->value('id')
            ?? $connection->table('retail_companies')->where('is_active', true)->orderBy('id')->value('id');

        if ($defaultCompanyId !== null) {
            $connection->table('retail_sales')
                ->whereNull('retail_company_id')
                ->update(['retail_company_id' => $defaultCompanyId]);
        }
    }

    public function down(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_sales', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('retail_company_id');
            });
    }
};
