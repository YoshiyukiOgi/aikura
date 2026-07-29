<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * @param array<string, string|null> $defaults
     * @return array<string, string|null>
     */
    public static function values(array $defaults = []): array
    {
        if (! Schema::hasTable('app_settings')) {
            return $defaults;
        }

        $values = self::query()
            ->whereIn('key', array_keys($defaults))
            ->pluck('value', 'key')
            ->all();

        return array_replace($defaults, $values);
    }

    public static function setValue(string $key, ?string $value): void
    {
        self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    /**
     * @return array<int, array{text: string, is_visible: bool}>
     */
    public static function invoiceBankAccounts(): array
    {
        $raw = self::values(['invoice_bank_accounts' => '[]'])['invoice_bank_accounts'] ?? '[]';
        $accounts = json_decode($raw, true);

        if (! is_array($accounts)) {
            return [];
        }

        return collect($accounts)
            ->filter(fn ($account): bool => is_array($account))
            ->take(10)
            ->map(fn (array $account): array => [
                'text' => trim((string) ($account['text'] ?? '')),
                'is_visible' => (bool) ($account['is_visible'] ?? false),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, array{text?: mixed, is_visible?: mixed}> $accounts
     */
    public static function setInvoiceBankAccounts(array $accounts): void
    {
        $normalized = collect($accounts)
            ->take(10)
            ->map(fn (array $account): array => [
                'text' => trim((string) ($account['text'] ?? '')),
                'is_visible' => (bool) ($account['is_visible'] ?? false),
            ])
            ->values()
            ->all();

        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        self::setValue('invoice_bank_accounts', $encoded === false ? '[]' : $encoded);
    }

    /**
     * @return array<int, array{text: string, is_visible: bool}>
     */
    public static function visibleInvoiceBankAccounts(): array
    {
        return collect(self::invoiceBankAccounts())
            ->filter(fn (array $account): bool => $account['is_visible'] && $account['text'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{postal_code: string, address: string, name: string, phone: string, fax: string, registration_number: string}
     */
    public static function companyInformation(): array
    {
        $values = self::values([
            'company_postal_code' => '784-0033',
            'company_address' => '安芸市赤野甲38番地1',
            'company_name' => '有限会社 有光酒造場',
            'company_phone' => '0887-33-2117',
            'company_fax' => '0887-33-4477',
            'company_registration_number' => 'T2-4900-0201-2728',
        ]);

        return [
            'postal_code' => (string) $values['company_postal_code'],
            'address' => (string) $values['company_address'],
            'name' => (string) $values['company_name'],
            'phone' => (string) $values['company_phone'],
            'fax' => (string) $values['company_fax'],
            'registration_number' => (string) $values['company_registration_number'],
        ];
    }
}
