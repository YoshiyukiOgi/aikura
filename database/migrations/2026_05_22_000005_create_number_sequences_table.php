<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->string('prefix', 40)->nullable();
            $table->string('suffix', 40)->nullable();
            $table->unsignedBigInteger('current_number')->default(0);
            $table->unsignedSmallInteger('padding_length')->default(6);
            $table->string('reset_type', 30)->default('none');
            $table->date('last_reset_on')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->index('reset_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};

