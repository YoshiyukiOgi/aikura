<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppSettingController extends Controller
{
    public function edit(): View
    {
        $bankAccounts = collect(AppSetting::invoiceBankAccounts())
            ->pad(10, ['text' => '', 'is_visible' => false])
            ->take(10)
            ->values()
            ->all();

        return view('settings.index', [
            'settings' => AppSetting::values($this->defaults()),
            'themes' => $this->themes(),
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:80'],
            'company_postal_code' => ['required', 'string', 'max:10'],
            'company_address' => ['required', 'string', 'max:200'],
            'company_phone' => ['required', 'string', 'max:30'],
            'company_fax' => ['nullable', 'string', 'max:30'],
            'company_registration_number' => ['required', 'string', 'max:30'],
            'system_name' => ['required', 'string', 'max:80'],
            'theme' => ['required', 'string', 'in:blue,green,brown,indigo,slate,neon,disco'],
            'auto_refresh_enabled' => ['nullable', 'string', 'in:1'],
            'auto_refresh_interval_seconds' => ['required', 'integer', 'min:5', 'max:3600'],
            'hide_zero_stock_lots' => ['nullable', 'string', 'in:1'],
            'alcohol_tolerance_lower' => ['required', 'numeric', 'min:0', 'max:10'],
            'alcohol_tolerance_upper' => ['required', 'numeric', 'min:0', 'max:10'],
            'alcohol_out_of_range_approval_required' => ['nullable', 'string', 'in:1'],
            'invoice_bank_accounts' => ['required', 'array', 'size:10'],
            'invoice_bank_accounts.*.text' => ['nullable', 'string', 'max:500'],
            'invoice_bank_accounts.*.is_visible' => ['nullable', 'boolean'],
        ]);

        AppSetting::setValue('company_name', $validated['company_name']);
        AppSetting::setValue('company_postal_code', $validated['company_postal_code']);
        AppSetting::setValue('company_address', $validated['company_address']);
        AppSetting::setValue('company_phone', $validated['company_phone']);
        AppSetting::setValue('company_fax', $validated['company_fax'] ?? null);
        AppSetting::setValue('company_registration_number', $validated['company_registration_number']);
        AppSetting::setValue('system_name', $validated['system_name']);
        AppSetting::setValue('theme', $validated['theme']);
        AppSetting::setValue('auto_refresh_enabled', $request->boolean('auto_refresh_enabled') ? '1' : '0');
        AppSetting::setValue('auto_refresh_interval_seconds', (string) $validated['auto_refresh_interval_seconds']);
        AppSetting::setValue('hide_zero_stock_lots', $request->boolean('hide_zero_stock_lots') ? '1' : '0');
        AppSetting::setValue('alcohol_tolerance_lower', number_format((float) $validated['alcohol_tolerance_lower'], 2, '.', ''));
        AppSetting::setValue('alcohol_tolerance_upper', number_format((float) $validated['alcohol_tolerance_upper'], 2, '.', ''));
        AppSetting::setValue('alcohol_out_of_range_approval_required', $request->boolean('alcohol_out_of_range_approval_required') ? '1' : '0');
        AppSetting::setInvoiceBankAccounts($validated['invoice_bank_accounts']);

        return redirect()
            ->route('settings.index')
            ->with('status', '設定を保存しました。');
    }

    /**
     * @return array<string, string>
     */
    private function defaults(): array
    {
        return [
            'company_name' => '有限会社 有光酒造場',
            'company_postal_code' => '784-0033',
            'company_address' => '安芸市赤野甲38番地1',
            'company_phone' => '0887-33-2117',
            'company_fax' => '0887-33-4477',
            'company_registration_number' => 'T2-4900-0201-2728',
            'system_name' => 'B2B販売管理システム',
            'theme' => 'blue',
            'auto_refresh_enabled' => '1',
            'auto_refresh_interval_seconds' => '30',
            'hide_zero_stock_lots' => '1',
            'alcohol_tolerance_lower' => '0.90',
            'alcohol_tolerance_upper' => '0.90',
            'alcohol_out_of_range_approval_required' => '1',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function themes(): array
    {
        return [
            'blue' => 'ブルー',
            'green' => 'グリーン',
            'brown' => 'ブラウン',
            'indigo' => 'インディゴ',
            'slate' => 'スレート',
            'neon' => 'ネオン',
            'disco' => 'ディスコ',
        ];
    }
}
