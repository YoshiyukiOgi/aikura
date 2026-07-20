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
        return view('settings.index', [
            'settings' => AppSetting::values($this->defaults()),
            'themes' => $this->themes(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:80'],
            'system_name' => ['required', 'string', 'max:80'],
            'theme' => ['required', 'string', 'in:blue,green,brown,indigo,slate,neon,disco'],
            'auto_refresh_enabled' => ['nullable', 'string', 'in:1'],
            'auto_refresh_interval_seconds' => ['required', 'integer', 'min:5', 'max:3600'],
            'hide_zero_stock_lots' => ['nullable', 'string', 'in:1'],
            'alcohol_tolerance_lower' => ['required', 'numeric', 'min:0', 'max:10'],
            'alcohol_tolerance_upper' => ['required', 'numeric', 'min:0', 'max:10'],
            'alcohol_out_of_range_approval_required' => ['nullable', 'string', 'in:1'],
        ]);

        AppSetting::setValue('company_name', $validated['company_name']);
        AppSetting::setValue('system_name', $validated['system_name']);
        AppSetting::setValue('theme', $validated['theme']);
        AppSetting::setValue('auto_refresh_enabled', $request->boolean('auto_refresh_enabled') ? '1' : '0');
        AppSetting::setValue('auto_refresh_interval_seconds', (string) $validated['auto_refresh_interval_seconds']);
        AppSetting::setValue('hide_zero_stock_lots', $request->boolean('hide_zero_stock_lots') ? '1' : '0');
        AppSetting::setValue('alcohol_tolerance_lower', number_format((float) $validated['alcohol_tolerance_lower'], 2, '.', ''));
        AppSetting::setValue('alcohol_tolerance_upper', number_format((float) $validated['alcohol_tolerance_upper'], 2, '.', ''));
        AppSetting::setValue('alcohol_out_of_range_approval_required', $request->boolean('alcohol_out_of_range_approval_required') ? '1' : '0');

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
            'company_name' => '白鶴酒造株式会社',
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
