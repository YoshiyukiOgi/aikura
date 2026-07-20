<?php

namespace Database\Seeders;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientCsvCustomerSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CustomerMasterSeeder::class);

        $path = base_path('data/cliants.csv');
        if (! is_file($path)) {
            $this->command?->error("CSV file not found: {$path}");
            return;
        }

        $producer = TransactionCategory::query()->where('code', 'producer')->firstOrFail();
        $wholesale = TransactionCategory::query()->where('code', 'wholesale')->firstOrFail();
        $retail = TransactionCategory::query()->where('code', 'retail')->firstOrFail();
        $settlement = SettlementReceivableCategory::query()->where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::query()->where('code', 'monthly_end_next_month_end')->firstOrFail();

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->command?->error("Could not open CSV file: {$path}");
            return;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            $this->command?->error('CSV header is empty.');
            return;
        }

        $header = array_map(fn ($value): string => $this->decodeCsvValue((string) $value), $header);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $count = 0;

        DB::transaction(function () use ($handle, $header, $producer, $wholesale, $retail, $settlement, $billingCycle, &$count): void {
            while (($row = fgetcsv($handle)) !== false) {
                $row = array_map(fn ($value): string => $this->decodeCsvValue((string) $value), $row);
                $data = $this->combine($header, $row);
                $legacyId = trim((string) ($data['取引先ID'] ?? ''));
                $name = trim((string) ($data['取引先名'] ?? ''));

                if ($legacyId === '' || $name === '') {
                    continue;
                }

                $transactionCategory = $this->resolveTransactionCategory(
                    (string) ($data['取引区分'] ?? ''),
                    $producer,
                    $wholesale,
                    $retail,
                );

                $note = $this->makeNote($data);
                $postalCode = $this->normalizePostalCode((string) ($data['郵便番号1'] ?? ''));

                Customer::updateOrCreate(
                    ['legacy_code' => $legacyId],
                    [
                        'customer_code' => 'CLIENT-'.$legacyId,
                        'name' => $name,
                        'name_kana' => $this->blankToNull((string) ($data['取引先カナ名'] ?? '')),
                        'short_name' => $this->blankToNull((string) ($data['取引先略称'] ?? '')) ?? mb_substr($name, 0, 120),
                        'billing_name' => $this->blankToNull((string) ($data['請求書メイン'] ?? '')) === 'FALSE'
                            ? $name
                            : ($this->blankToNull((string) ($data['取引先名2'] ?? '')) ?? $name),
                        'postal_code' => $postalCode,
                        'address1' => $this->blankToNull(trim(((string) ($data['都道府県１'] ?? '')).' '.((string) ($data['住所1'] ?? '')))),
                        'address2' => $this->blankToNull(trim(((string) ($data['都道府県2'] ?? '')).' '.((string) ($data['住所2'] ?? '')))),
                        'phone' => $this->blankToNull((string) ($data['電話番号1'] ?? '')),
                        'fax' => $this->blankToNull((string) ($data['ファックス番号1'] ?? '')),
                        'email' => null,
                        'contact_name' => $this->blankToNull((string) ($data['担当者'] ?? '')),
                        'transaction_category_id' => $transactionCategory->id,
                        'settlement_receivable_category_id' => $settlement->id,
                        'billing_cycle_id' => $billingCycle->id,
                        'tax_rounding_method' => 'round',
                        'tax_calculation_unit' => 'line',
                        'amount_rounding_method' => 'round',
                        'invoice_required' => $this->toBool((string) ($data['請求書メイン'] ?? 'TRUE')),
                        'search_key' => trim($legacyId.' '.$name.' '.((string) ($data['取引先カナ名'] ?? '')).' '.((string) ($data['取引先略称'] ?? ''))),
                        'legacy_name' => $name,
                        'note' => $note,
                        'is_active' => true,
                        'disabled_at' => null,
                    ],
                );

                $count++;
            }
        });

        fclose($handle);
        $this->command?->info("Imported {$count} customers from cliants.csv.");
    }

    /**
     * @param array<int, string|null> $header
     * @param array<int, string|null> $row
     * @return array<string, string|null>
     */
    private function combine(array $header, array $row): array
    {
        $data = [];
        foreach ($header as $index => $name) {
            if ($name === null || $name === '') {
                continue;
            }
            $data[$name] = $row[$index] ?? null;
        }

        return $data;
    }

    private function decodeCsvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'SJIS-win,CP932,EUC-JP,JIS');
    }

    private function resolveTransactionCategory(string $value, TransactionCategory $producer, TransactionCategory $wholesale, TransactionCategory $retail): TransactionCategory
    {
        return match (true) {
            str_contains($value, '生産者') => $producer,
            str_contains($value, '小売') => $retail,
            default => $wholesale,
        };
    }

    /**
     * @param array<string, string|null> $data
     */
    private function makeNote(array $data): ?string
    {
        $parts = [];

        foreach ([
            '業種区分',
            '値引',
            '繰越処理',
            'タックシール発行',
            '決算売掛区分',
            '休み',
            '消費税未納取引',
            '酒税未納取引',
            '閉め日',
            '携帯番号1',
            '携帯番号2',
        ] as $key) {
            $value = $this->blankToNull((string) ($data[$key] ?? ''));
            if ($value !== null) {
                $parts[] = "{$key}: {$value}";
            }
        }

        foreach (range(1, 42) as $number) {
            $key = "フィールド{$number}";
            $value = $this->blankToNull((string) ($data[$key] ?? ''));
            if ($value !== null) {
                $parts[] = "{$key}: {$value}";
            }
        }

        $note = implode("\n", $parts);

        return $note === '' ? null : $note;
    }

    private function normalizePostalCode(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value);
        if ($digits === null || $digits === '') {
            return null;
        }

        return strlen($digits) === 7
            ? substr($digits, 0, 3).'-'.substr($digits, 3)
            : $digits;
    }

    private function blankToNull(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function toBool(string $value): bool
    {
        return ! in_array(strtoupper(trim($value)), ['FALSE', '0', 'NO'], true);
    }
}
