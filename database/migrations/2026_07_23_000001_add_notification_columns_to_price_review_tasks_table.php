<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_review_tasks', function (Blueprint $table): void {
            $table->foreignId('notified_by_user_id')
                ->nullable()
                ->after('reviewed_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('notified_at')
                ->nullable()
                ->after('notified_by_user_id');

            $table->index(
                ['customer_id', 'product_id', 'status', 'notified_at'],
                'price_review_tasks_selection_notify_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('price_review_tasks', function (Blueprint $table): void {
            $table->dropIndex('price_review_tasks_selection_notify_index');
            $table->dropConstrainedForeignId('notified_by_user_id');
            $table->dropColumn('notified_at');
        });
    }
};
