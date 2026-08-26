<?php

namespace App\Console\Commands;

use App\Models\Retail\RetailCompany;
use App\Services\Retail\ImportTamagawaAccessCustomers;
use Illuminate\Console\Command;

class ImportTamagawaRetailCustomersCommand extends Command
{
    protected $signature = 'retail:import-tamagawa-customers
        {package : Export-AccessMigrationPackage.ps1で抽出したパッケージディレクトリ}
        {--company=maru : 移行先の小売会社コード}
        {--apply : 実際に小売顧客へ保存する}';

    protected $description = '玉川旧Access DBの個人顧客を小売顧客へ移行する';

    public function handle(ImportTamagawaAccessCustomers $importer): int
    {
        $company = RetailCompany::query()->where('company_key', $this->option('company'))->first();
        if (! $company) {
            $this->error('移行先の小売会社が見つかりません。');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $summary = $importer->import((string) $this->argument('package'), $company, $apply);

        $this->info($apply ? '顧客移行が完了しました。' : '顧客移行プレビューが完了しました。DBは変更していません。');
        $this->table(['項目', '件数'], [
            ['読取', $summary['read']],
            ['移行対象', $summary['imported']],
            ['氏名・カナなし除外', $summary['skipped_without_name']],
            ['不正メールを備考へ移動', $summary['invalid_email_to_note']],
        ]);

        return self::SUCCESS;
    }
}
