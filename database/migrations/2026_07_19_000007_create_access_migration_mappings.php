<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_migration_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('access_migration_batches')->cascadeOnDelete();
            $table->string('source_table');
            $table->string('source_key', 500);
            $table->string('target_table');
            $table->string('target_id', 100);
            $table->string('action', 30);
            $table->char('source_payload_sha256', 64);
            $table->timestampsTz();

            $table->unique(['batch_id', 'source_table', 'source_key'], 'access_migration_mapping_source_unique');
            $table->index(['target_table', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_migration_mappings');
    }
};
