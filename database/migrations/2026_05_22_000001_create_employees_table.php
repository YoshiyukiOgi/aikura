<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->string('employee_code', 50)->unique();
            $table->string('name', 120);
            $table->string('name_kana', 120)->nullable();
            $table->string('email', 255)->nullable()->unique();
            $table->string('phone', 50)->nullable();
            $table->string('department', 120)->nullable();
            $table->string('position', 120)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampTz('disabled_at')->nullable();
            $table->timestampsTz();

            $table->index('name');
            $table->index('name_kana');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};

