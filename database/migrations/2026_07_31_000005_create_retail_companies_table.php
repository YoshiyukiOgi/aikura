<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('retail.database.connection', 'retail'));

        $schema->create('retail_companies', function (Blueprint $table): void {
            $table->id();
            $table->string('company_key', 80)->unique();
            $table->string('name', 160);
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });

        $now = now();
        $companies = collect(config('retail.companies', []))
            ->map(fn (array $company, string $key): array => [
                'company_key' => $key ?: Str::slug((string) $company['name']),
                'name' => $company['name'],
                'description' => $company['description'] ?? null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        if ($companies !== []) {
            DB::connection(config('retail.database.connection', 'retail'))->table('retail_companies')->insert($companies);
        }
    }

    public function down(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))->dropIfExists('retail_companies');
    }
};
