<?php

namespace App\Console\Commands;

use Database\Seeders\OperationTestDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SeedOperationTestDemo extends Command
{
    protected $signature = 'aikura:seed-operation-test';

    protected $description = 'Migrate and seed the loginable operation-test demo environment.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('本番環境では運用試験デモデータを投入できません。');

            return self::FAILURE;
        }

        $this->components->info('マイグレーションを確認しています。');
        if (Artisan::call('migrate', ['--force' => true]) !== self::SUCCESS) {
            $this->error(Artisan::output());

            return self::FAILURE;
        }

        $this->components->info('運用試験デモデータを投入しています。');
        if (Artisan::call('db:seed', ['--class' => OperationTestDemoSeeder::class, '--force' => true]) !== self::SUCCESS) {
            $this->error(Artisan::output());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('運用試験デモ環境の準備が完了しました。');
        $this->table(['ユーザー', 'パスワード', '用途'], [
            ['demo.operator@example.test', 'password', '業務操作（管理者権限）'],
            ['demo.approver@example.test', 'password', '承認操作（管理者権限）'],
        ]);
        $this->line('受注画面: /sales-orders （編集可能な DEMO-EDIT-001 を含む）');
        $this->line('請求画面: /billing （請求書作成前の確定出荷 DEMO-BILL-001 を含む）');

        return self::SUCCESS;
    }
}
