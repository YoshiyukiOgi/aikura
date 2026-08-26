<?php

namespace App\Services\Retail;

use App\Models\Retail\RetailCompany;
use App\Models\Retail\RetailCustomer;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SplFileObject;
use Throwable;

class ImportTamagawaAccessCustomers
{
    /**
     * @return array{read:int, imported:int, skipped_without_name:int, invalid_email_to_note:int}
     */
    public function import(string $packagePath, RetailCompany $company, bool $apply): array
    {
        $manifest = $this->manifest($packagePath);
        $customerFile = $this->tableFile($manifest, $packagePath, '個人顧客マスター');
        $category2 = $this->lookup($this->tableFile($manifest, $packagePath, '個人顧客区分2'), 'ID個人顧客2', '個人顧客区分2');
        $category3 = $this->lookup($this->tableFile($manifest, $packagePath, '個人顧客区分3'), 'ID個人顧客3', '個人顧客区分3');

        $summary = [
            'read' => 0,
            'imported' => 0,
            'skipped_without_name' => 0,
            'invalid_email_to_note' => 0,
        ];
        $records = [];
        $now = now();
        $file = new SplFileObject($customerFile, 'r');
        $connection = DB::connection(config('retail.database.connection', 'retail'));

        if ($apply) {
            $connection->beginTransaction();
        }

        try {
            foreach ($file as $line) {
            if (trim((string) $line) === '') {
                continue;
            }

            $summary['read']++;
            $row = json_decode((string) $line, true, flags: JSON_THROW_ON_ERROR);
            $name = $this->join([$row['姓'] ?? null, $row['名'] ?? null, $row['名2'] ?? null], ' ');
            $nameKana = $this->join([$row['ｶﾀｶﾅ姓'] ?? null, $row['ｶﾀｶﾅ名'] ?? null], ' ');
            if ($name === '' && $nameKana === '') {
                $summary['skipped_without_name']++;
                continue;
            }

            if ($name === '') {
                $name = $nameKana;
            }

            $email = $this->value($row['電子メールアドレス'] ?? null);
            $invalidEmail = $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false;
            if ($invalidEmail) {
                $summary['invalid_email_to_note']++;
            }

            $notes = array_values(array_filter([
                $this->value($row['摘要'] ?? null),
                '顧客区分: '.($this->value($row['顧客区分'] ?? null) ?? '-'),
                '顧客区分2: '.($category2[(string) ($row['顧客区分2'] ?? '')] ?? '-'),
                '顧客区分3: '.($category3[(string) ($row['顧客区分3'] ?? '')] ?? '-'),
                $invalidEmail ? 'メール（旧DB・形式不正）: '.$email : null,
            ], fn (?string $value): bool => $value !== null && $value !== ''));

            $createdAt = $this->value($row['入力日'] ?? null) ?? $now;
            $updatedAt = $this->value($row['更新日'] ?? null) ?? $createdAt;
            $records[] = [
                'retail_company_id' => $company->id,
                'customer_code' => 'TAMAGAWA-C-'.str_pad((string) $row['個人顧客ID'], 4, '0', STR_PAD_LEFT),
                'name' => $name,
                'name_kana' => $nameKana !== '' ? $nameKana : null,
                'billing_name' => null,
                'billing_method' => 'per_sale',
                'postal_code' => $this->value($row['郵便'] ?? null),
                'address1' => $this->join([$row['都道府県'] ?? null, $row['市町村'] ?? null, $row['番地'] ?? null]),
                'address2' => $this->value($row['建物内番地'] ?? null),
                'phone' => $this->value($row['電話'] ?? null),
                'fax' => $this->value($row['FAX'] ?? null),
                'email' => $invalidEmail ? null : $email,
                'closing_day' => null,
                'payment_month_offset' => 0,
                'payment_day' => null,
                'credit_limit' => null,
                'invoice_required' => true,
                'note' => implode("\n", $notes),
                'is_active' => true,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];

            if (count($records) === 500) {
                $this->write($records, $apply);
                $summary['imported'] += count($records);
                $records = [];
            }
            }

            $this->write($records, $apply);
            $summary['imported'] += count($records);

            if ($apply) {
                $connection->commit();
            }
        } catch (Throwable $exception) {
            if ($apply && $connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            throw $exception;
        }

        return $summary;
    }

    private function write(array $records, bool $apply): void
    {
        if (! $apply || $records === []) {
            return;
        }

        DB::connection(config('retail.database.connection', 'retail'))
            ->table((new RetailCustomer)->getTable())
            ->upsert(
                $records,
                ['customer_code'],
                array_values(array_diff(array_keys($records[0]), ['customer_code', 'created_at'])),
            );
    }

    private function manifest(string $packagePath): array
    {
        $manifestPath = rtrim($packagePath, '\\/').DIRECTORY_SEPARATOR.'manifest.json';
        if (! is_file($manifestPath)) {
            throw new RuntimeException("Access抽出manifestが見つかりません: {$manifestPath}");
        }

        return json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
    }

    private function tableFile(array $manifest, string $packagePath, string $tableName): string
    {
        foreach ($manifest['tables'] ?? [] as $table) {
            if (($table['name'] ?? null) === $tableName) {
                return rtrim($packagePath, '\\/').DIRECTORY_SEPARATOR.$table['file'];
            }
        }

        throw new RuntimeException("Accessテーブルが見つかりません: {$tableName}");
    }

    private function lookup(string $filePath, string $idColumn, string $nameColumn): array
    {
        $lookup = [];
        $file = new SplFileObject($filePath, 'r');
        foreach ($file as $line) {
            if (trim((string) $line) === '') {
                continue;
            }
            $row = json_decode((string) $line, true, flags: JSON_THROW_ON_ERROR);
            $lookup[(string) $row[$idColumn]] = (string) $row[$nameColumn];
        }

        return $lookup;
    }

    private function join(array $values, string $separator = ''): string
    {
        return implode($separator, array_values(array_filter(array_map(
            fn ($value): ?string => $this->value($value),
            $values,
        ), fn (?string $value): bool => $value !== null)));
    }

    private function value(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
