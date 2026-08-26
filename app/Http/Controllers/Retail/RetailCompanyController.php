<?php

namespace App\Http\Controllers\Retail;

use App\Http\Controllers\Controller;
use App\Models\Retail\RetailCompany;
use App\Models\Retail\RetailPriceSyncSetting;
use App\Models\Retail\RetailSystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RetailCompanyController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $selectedCompanyKey = $request->session()->get('retail.company');
        $selected = RetailCompany::activeByKey(is_string($selectedCompanyKey) ? $selectedCompanyKey : null);
        if (! $selected) {
            $firstCompanyKey = array_key_first(RetailCompany::activeOptions());
            if ($firstCompanyKey !== null) {
                $request->session()->put('retail.company', $firstCompanyKey);
            }
        }

        return redirect()->route('retail.pos');
    }

    public function manage(): View
    {
        return view('retail.companies.manage', [
            'companyRecords' => RetailCompany::allForManagement(),
            'priceSyncSetting' => RetailPriceSyncSetting::query()->firstOrCreate([], ['detection_mode' => 'manual']),
            'systemSetting' => RetailSystemSetting::query()->firstOrCreate([], [
                'system_name' => '小売販売システム',
                'theme' => 'blue',
            ]),
        ]);
    }

    public function updateSystem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'system_name' => ['required', 'string', 'max:160'],
            'theme' => ['required', Rule::in(['blue', 'green', 'brown', 'indigo', 'slate', 'neon', 'disco'])],
            'detection_mode' => ['required', Rule::in(['manual', 'daily', 'interval'])],
            'interval_minutes' => ['nullable', 'integer', 'min:15', 'max:1440'],
        ]);

        RetailSystemSetting::query()->firstOrCreate([], [
            'system_name' => '小売販売システム',
            'theme' => 'blue',
        ])
            ->forceFill([
                'system_name' => $validated['system_name'],
                'theme' => $validated['theme'],
            ])
            ->save();

        RetailPriceSyncSetting::query()->firstOrCreate([], ['detection_mode' => 'manual'])
            ->forceFill([
                'detection_mode' => $validated['detection_mode'],
                'interval_minutes' => $validated['interval_minutes'] ?? 60,
            ])
            ->save();

        return redirect()->route('retail.system.manage')->with('status', 'システム管理設定を保存しました。');
    }

    public function select(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company' => ['required', Rule::in(array_keys(RetailCompany::activeOptions()))],
            'redirect_to' => ['nullable', 'string', 'max:255'],
        ]);

        $request->session()->put('retail.company', $validated['company']);

        $redirectTo = (string) ($validated['redirect_to'] ?? '');
        if (str_starts_with($redirectTo, '/retail/') && $redirectTo !== '/retail/companies') {
            return redirect()->to($redirectTo);
        }

        return redirect()->route('retail.pos');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_key' => ['required', 'alpha_dash', 'max:80', Rule::unique('retail.retail_companies', 'company_key')],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        RetailCompany::query()->create([
            'company_key' => $validated['company_key'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('retail.system.manage')->with('status', '会社を追加しました。');
    }

    public function destroy(Request $request, RetailCompany $company): RedirectResponse
    {
        if (RetailCompany::query()->where('is_active', true)->whereKeyNot($company->id)->doesntExist()) {
            return redirect()->route('retail.system.manage')->withErrors(['company' => '最後の有効会社は削除できません。']);
        }

        $company->forceFill(['is_active' => false])->save();

        if ($request->session()->get('retail.company') === $company->company_key) {
            $request->session()->forget('retail.company');
        }

        return redirect()->route('retail.system.manage')->with('status', '会社を停止しました。');
    }
}
