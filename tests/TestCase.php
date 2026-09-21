<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Docker-injected server variables are what Laravel resolves first.
        // Never allow PHPUnit's process-level overrides to mask a production
        // database configured on the container.
        $environment = (string) ($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '');
        $database = (string) ($_SERVER['DB_DATABASE'] ?? $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '');

        if ($environment !== 'testing' || ! str_contains($database, 'testing')) {
            throw new RuntimeException("Refusing to run tests against non-testing database [{$database}].");
        }

        parent::setUp();

        // The retail connection uses a second PostgreSQL session. RefreshDatabase
        // wraps only the default session, so retail writes would otherwise remain
        // visible to subsequent tests. This runs only after the testing-DB guard
        // above and keeps each test independent without touching A's DB.
        DB::connection('retail')->statement(<<<'SQL'
            TRUNCATE TABLE
                retail_adjustment_events,
                retail_brewery_product_import_selections,
                retail_companies,
                retail_company_settings,
                retail_deliveries,
                retail_delivery_lines,
                retail_inventory_movements,
                retail_inventory_stocks,
                retail_invoice_lines,
                retail_invoices,
                retail_payment_allocations,
                retail_payments,
                retail_price_change_candidates,
                retail_price_histories,
                retail_price_sync_settings,
                retail_purchase_order_lines,
                retail_purchase_orders,
                retail_sale_items,
                retail_sales,
                retail_products,
                retail_suppliers,
                retail_system_settings,
                retail_customers,
                retail_document_sequences
            RESTART IDENTITY CASCADE
            SQL);

        $now = now();
        $companies = collect(config('retail.companies', []))
            ->map(fn (array $company, string $key): array => [
                'company_key' => $key,
                'name' => $company['name'],
                'description' => $company['description'] ?? null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        if ($companies !== []) {
            DB::connection('retail')->table('retail_companies')->insert($companies);
        }

        DB::connection('retail')->table('retail_system_settings')->insert([
            'system_name' => '小売販売システム',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
