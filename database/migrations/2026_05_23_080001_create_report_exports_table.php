<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id();
            $table->string('report_type', 80)->index();
            $table->string('format', 20);
            $table->string('status', 40)->default('generated')->index();
            $table->morphs('exportable');
            $table->string('disk', 80)->default('local');
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->string('checksum_sha256', 64);
            $table->timestampTz('generated_at');
            $table->text('reason')->nullable();
            $table->timestampsTz();

            $table->index(['report_type', 'format']);
            $table->index(['generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
