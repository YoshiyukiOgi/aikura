<?php

namespace App\Http\Controllers\Retail;

use App\Http\Controllers\Controller;
use App\Models\Retail\RetailCompany;
use App\Models\Retail\RetailCustomer;
use App\Models\Retail\RetailLedgerDocument;
use App\Models\Retail\RetailMonthlyClosing;
use App\Services\Retail\CloseRetailMonthService;
use App\Services\Retail\CreateRetailLedgerDocumentService;
use App\Services\Retail\ImportTamagawaLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RetailLedgerController extends Controller
{
    public function statement(RetailMonthlyClosing $closing): View
    {
        $closing->load(['company', 'balances.customer']);

        return view('retail.ledger.statement', compact('closing'));
    }

    public function index(Request $request): View
    {
        $companies = RetailCompany::query()->orderBy('name')->get();
        $companyId = $request->integer('retail_company_id') ?: $companies->first()?->id;
        $company = $companyId === null ? null : $companies->firstWhere('id', $companyId);

        $documents = $company === null
            ? collect()
            : RetailLedgerDocument::query()
                ->with(['customer', 'lines'])
                ->where('retail_company_id', $company->id)
                ->latest('business_date')
                ->latest('id')
                ->paginate(50)
                ->withQueryString();
        $customers = $company === null
            ? collect()
            : RetailCustomer::query()->where('retail_company_id', $company->id)->orderBy('name')->get();
        $closings = $company === null
            ? collect()
            : RetailMonthlyClosing::query()->withCount('balances')->where('retail_company_id', $company->id)->latest('period')->get();

        return view('retail.ledger.index', compact('companies', 'company', 'documents', 'customers', 'closings'));
    }

    public function store(Request $request, CreateRetailLedgerDocumentService $service): RedirectResponse
    {
        $data = $request->validate([
            'retail_company_id' => ['required', 'integer', Rule::exists('retail_companies', 'id')],
            'retail_customer_id' => ['required', 'integer', Rule::exists('retail_customers', 'id')],
            'document_no' => ['required', 'string', 'max:64'],
            'business_date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_kind' => ['nullable', Rule::in(['sale', 'payment', 'adjustment', 'opening'])],
            'lines.*.source_product_id' => ['nullable', 'integer'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['nullable', 'numeric'],
            'lines.*.unit_price' => ['nullable', 'numeric'],
            'lines.*.amount' => ['required', 'numeric'],
        ]);

        $company = RetailCompany::query()->findOrFail($data['retail_company_id']);
        $customer = RetailCustomer::query()->findOrFail($data['retail_customer_id']);
        $service->handle($company, $customer, Arr::only($data, ['document_no', 'business_date']), $data['lines']);

        return redirect()->route('retail.ledger.index', ['retail_company_id' => $company->id])
            ->with('status', '元帳伝票を登録しました。商品ID -1 の明細は入金として保存され、在庫・酒蔵出荷には連携しません。');
    }

    public function import(Request $request, ImportTamagawaLedgerService $service): RedirectResponse
    {
        $data = $request->validate([
            'retail_company_id' => ['required', 'integer', Rule::exists('retail_companies', 'id')],
            'source_file' => ['required', 'file', 'max:20480'],
        ]);
        $contents = file_get_contents($request->file('source_file')->getRealPath());
        $payload = json_decode($contents ?: '', true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload)) {
            abort(422, '取込ファイルはJSONオブジェクトである必要があります。');
        }
        $payload['source_file_name'] = $request->file('source_file')->getClientOriginalName();

        $company = RetailCompany::query()->findOrFail($data['retail_company_id']);
        $batch = $service->handle($company, $payload, $request->user()?->id);

        return redirect()->route('retail.ledger.index', ['retail_company_id' => $company->id])
            ->with('status', sprintf('玉川Access取込を登録しました。伝票 %d 件、明細 %d 件です。', $batch->document_count, $batch->line_count));
    }

    public function close(Request $request, CloseRetailMonthService $service): RedirectResponse
    {
        $data = $request->validate([
            'retail_company_id' => ['required', 'integer', Rule::exists('retail_companies', 'id')],
            'period' => ['required', 'date_format:Y-m'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $company = RetailCompany::query()->findOrFail($data['retail_company_id']);
        $closing = $service->handle($company, $data['period'].'-01', $request->user()?->id, $data['note'] ?? null);

        return redirect()->route('retail.ledger.index', ['retail_company_id' => $company->id])
            ->with('status', sprintf('%s を月次締めしました。顧客残高 %d 件を固定しました。', $closing->period->format('Y年m月'), $closing->balances->count()));
    }
}
