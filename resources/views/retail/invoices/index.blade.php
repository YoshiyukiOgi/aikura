<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>請求書</title>
  <style>
    body{font-family:"Noto Sans JP",Meiryo,sans-serif;background:#f3f6fa;color:#172033;margin:0;padding:18px}.panel{background:#fff;border:1px solid #d9e2ee;border-radius:8px;padding:14px;margin-bottom:12px}.form-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}label{display:grid;gap:6px;color:#4d6078;font-size:11px;font-weight:800}.full{grid-column:1/-1}input,select,button{font:inherit;padding:8px;border:1px solid #cbd7e6;border-radius:6px;min-height:38px}button{background:#0b6ff6;color:#fff;border-color:#0b6ff6;font-weight:700}.table{width:100%;border-collapse:collapse}.table th,.table td{border-bottom:1px solid #edf1f6;padding:8px;text-align:left;font-size:13px}.msg{background:#e9f8ef;color:#137333;padding:10px;border-radius:6px;margin-bottom:12px}.meta{color:#65758c;font-size:12px}.actions{display:flex;justify-content:flex-end;margin-top:12px}@media(max-width:720px){.form-grid{grid-template-columns:1fr}.full{grid-column:auto}.table{display:block;overflow:auto}}
  </style>
</head>
<body>
  @if(session('status'))<div class="msg">{{ session('status') }}</div>@endif
  @if($errors->any())<div class="msg" style="background:#fff1f0;color:#b42318">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

  <div class="panel">
    <h1>請求書作成</h1>
    <form method="post" action="{{ route('retail.invoices.store') }}">
      @csrf
      <div class="form-grid">
        <label class="full">顧客
          <select id="invoice-customer" name="retail_customer_id" required>
            <option value="">顧客を選択</option>
            @foreach($customers as $customer)
              <option value="{{ $customer->id }}" data-billing-method="{{ $customerBillingMethods[$customer->id] }}" @selected((string) old('retail_customer_id') === (string) $customer->id)>{{ $customer->customer_code }} / {{ $customer->name }}（{{ ['monthly' => '締め請求', 'per_sale' => '都度請求', 'none' => '請求なし'][$customerBillingMethods[$customer->id]] }}）</option>
            @endforeach
          </select>
        </label>
        <label class="full" id="sale-field">都度請求の対象販売
          <select name="retail_sale_id" id="invoice-sale">
            <option value="">販売伝票を選択</option>
            @foreach($sales as $sale)
              <option value="{{ $sale->id }}" data-customer-id="{{ $sale->retail_customer_id }}" @selected((string) old('retail_sale_id') === (string) $sale->id)>{{ $sale->sale_no }} / {{ $sale->sale_date->format('Y-m-d') }} / ¥{{ number_format((float) $sale->total_amount) }}</option>
            @endforeach
          </select>
          <span class="meta">顧客の請求方式が都度請求の場合に選択します。</span>
        </label>
        <label>請求日<input type="date" name="invoice_date" value="{{ old('invoice_date', now()->toDateString()) }}" required></label>
        <label>締日（締め請求時）<input type="date" name="closing_date" value="{{ old('closing_date', now()->toDateString()) }}" required></label>
        <label>支払期限<input type="date" name="due_date" value="{{ old('due_date') }}"></label>
      </div>
      <div class="actions"><button>請求書作成</button></div>
    </form>
  </div>

  <div class="panel">
    <h2>請求書一覧</h2>
    <table class="table"><thead><tr><th>番号</th><th>請求日</th><th>顧客</th><th>合計</th><th>残高</th><th>状態</th><th></th></tr></thead><tbody>
      @foreach($invoices as $invoice)
        <tr><td>{{ $invoice->invoice_no }}</td><td>{{ $invoice->invoice_date->format('Y-m-d') }}</td><td>{{ $invoice->customer?->name }}</td><td>¥{{ number_format((float)$invoice->total_amount) }}</td><td>¥{{ number_format((float)$invoice->balance_amount) }}</td><td>{{ $invoice->status }}</td><td><a href="{{ route('retail.invoices.show',$invoice) }}">表示</a></td></tr>
      @endforeach
    </tbody></table>
    {{ $invoices->links() }}
  </div>

  <script>
    const customer = document.getElementById('invoice-customer');
    const sale = document.getElementById('invoice-sale');
    function updateSales() {
      const customerId = customer.value;
      const method = customer.selectedOptions[0]?.dataset.billingMethod;
      sale.querySelectorAll('option[data-customer-id]').forEach(option => {
        option.hidden = option.dataset.customerId !== customerId;
      });
      sale.required = method === 'per_sale';
      document.getElementById('sale-field').style.display = method === 'per_sale' ? 'grid' : 'none';
      if (method !== 'per_sale' || sale.selectedOptions[0]?.dataset.customerId !== customerId) sale.value = '';
    }
    customer.addEventListener('change', updateSales);
    updateSales();
  </script>
</body>
</html>
