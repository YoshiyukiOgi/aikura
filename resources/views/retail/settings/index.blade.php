<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>設定 | {{ $retailSystemName ?? '小売販売システム' }}</title>
  <style>
    :root{--bg:{{ $retailTheme['bg'] ?? '#f3f6fa' }};--panel:{{ $retailTheme['card'] ?? '#fff' }};--line:{{ $retailTheme['line'] ?? '#d9e2ee' }};--text:#172033;--muted:#65758c;--blue:{{ $retailTheme['primary'] ?? '#0b6ff6' }};--sidebar:{{ $retailTheme['sidebar'] ?? '#10243b' }}}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--bg);color:var(--text);font-family:"Noto Sans JP",Meiryo,system-ui,sans-serif;padding-left:230px}.sidebar{position:fixed;inset:0 auto 0 0;width:230px;background:var(--sidebar);color:#eaf1fb;padding:18px 12px}.brand{padding:6px 10px 16px;border-bottom:1px solid rgba(255,255,255,.14)}.brand h1{margin:0;font-size:16px}.brand p{margin:8px 0 0;color:#a8b8cc;font-size:12px;line-height:1.6}.nav{display:grid;gap:4px;margin-top:16px}.nav a{display:flex;align-items:center;justify-content:space-between;min-height:38px;padding:0 12px;border-radius:6px;color:#dbe7f5;text-decoration:none;font-size:12px}.nav a.active{background:var(--blue);color:#fff;font-weight:800}.nav a span:last-child{color:#b8c6d9;font-size:11px}.content{padding:18px}.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px 16px;margin-bottom:12px}.topbar h2{margin:0;font-size:17px}.meta{color:var(--muted);font-size:12px;line-height:1.65}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.card{border:1px solid var(--line);border-radius:8px;background:#f8fbff;padding:12px}.card h3{margin:0 0 8px;font-size:15px}.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.field-grid .full{grid-column:1/-1}label{display:grid;gap:6px;color:#4d6078;font-size:11px;font-weight:800}select,input,textarea{width:100%;min-height:36px;border:1px solid #cbd7e6;border-radius:6px;background:#fff;color:var(--text);font:inherit;padding:8px}.company-form{display:flex;gap:8px;align-items:end;flex-wrap:wrap}.company-form label{min-width:220px}.btn{border:1px solid #c7d3e2;border-radius:6px;background:#fff;color:#1d3450;padding:8px 12px;font:inherit;font-size:12px;cursor:pointer;text-decoration:none}.btn.primary{border-color:var(--blue);background:var(--blue);color:#fff;font-weight:800}.message{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#e9f8ef;color:#137333;font-size:12px}.error{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#fff1f0;color:#b42318;font-size:12px}.savebar{position:sticky;bottom:0;margin-top:12px;background:rgba(243,246,250,.92);backdrop-filter:blur(8px);border:1px solid var(--line);border-radius:8px;padding:12px;display:flex;justify-content:flex-end;gap:8px}@media(max-width:980px){body{padding-left:0}.sidebar{position:static;width:auto}.grid,.field-grid{grid-template-columns:1fr}.field-grid .full{grid-column:auto}}
  </style>
</head>
<body>
  <span hidden>Retail Settings Order Basic Sales Procurement Retail System Manage Link</span>
  <aside class="sidebar">
    <div class="brand"><h1>{{ $retailSystemName ?? '小売販売システム' }}</h1><p>{{ $selectedCompany['name'] }} の設定</p></div>
    <nav class="nav">
      <a href="{{ route('retail.pos') }}"><span>販売入力</span><span>POS</span></a>
      <a href="{{ route('retail.sales.index') }}"><span>販売履歴</span><span>履歴</span></a>
      <a href="{{ route('retail.customers.index') }}"><span>小売顧客</span><span>顧客</span></a>
      <a href="{{ route('retail.products.index') }}"><span>外部商品</span><span>商品</span></a>
      <a href="{{ route('retail.products.import') }}"><span>取扱商品選択</span><span>選択</span></a>
      <a class="active" href="{{ route('retail.settings') }}"><span>設定</span><span>設定</span></a>
      <a href="{{ route('retail.system.manage') }}"><span>システム管理</span><span>管理</span></a>
    </nav>
  </aside>

  <main class="content">
    <header class="topbar">
      <div><h2>{{ $selectedCompany['name'] }} 設定</h2><div class="meta">{{ $selectedCompany['description'] }}</div></div>
      <form class="company-form" method="post" action="{{ route('retail.companies.select') }}">
        @csrf
        <input type="hidden" name="redirect_to" value="{{ route('retail.settings', absolute: false) }}">
        <label>会社切替
          <select name="company">
            @foreach ($companies as $key => $company)
              <option value="{{ $key }}" @selected($selectedCompanyKey === $key)>{{ $company['name'] }}</option>
            @endforeach
          </select>
        </label>
        <button class="btn" type="submit">切替</button>
        <a class="btn primary" href="{{ route('retail.pos') }}">販売へ戻る</a>
      </form>
    </header>

    @if (session('status'))<div class="message">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <form method="post" action="{{ route('retail.settings.update') }}">
      @csrf
      @method('put')
      <section class="grid">
        <article class="card">
          <h3>基本設定</h3>
          <div class="field-grid">
            <label class="full">会社名<input name="company_name" value="{{ old('company_name', $selectedCompany['name']) }}" required></label>
            <label>代表者名<input name="representative_name" value="{{ old('representative_name', $selectedCompany['representative_name']) }}"></label>
            <label>郵便番号<input name="postal_code" value="{{ old('postal_code', $selectedCompany['postal_code']) }}" placeholder="000-0000" inputmode="numeric"></label>
            <label class="full">住所<input name="address1" value="{{ old('address1', $selectedCompany['address1']) }}"></label>
            <label class="full">建物名・住所2<input name="address2" value="{{ old('address2', $selectedCompany['address2']) }}"></label>
            <label>電話番号<input name="phone" value="{{ old('phone', $selectedCompany['phone']) }}" inputmode="tel"></label>
            <label>FAX番号<input name="fax" value="{{ old('fax', $selectedCompany['fax']) }}" inputmode="tel"></label>
            <label class="full">メールアドレス<input type="email" name="email" value="{{ old('email', $selectedCompany['email']) }}"></label>
            <label class="full">適格請求書発行事業者登録番号<input name="invoice_registration_number" value="{{ old('invoice_registration_number', $selectedCompany['invoice_registration_number']) }}" placeholder="T1234567890123"></label>
            <label class="full">説明<textarea name="company_description">{{ old('company_description', $selectedCompany['description']) }}</textarea></label>
          </div>
        </article>

        <article class="card">
          <h3>販売設定</h3>
          <label>販売区分
            <select name="sale_mode">
              <option value="mixed" @selected(old('sale_mode', $companySetting->sale_mode) === 'mixed')>現金・掛売・カード・QRを利用</option>
              <option value="cash_only" @selected(old('sale_mode', $companySetting->sale_mode) === 'cash_only')>現金販売のみ</option>
              <option value="credit_enabled" @selected(old('sale_mode', $companySetting->sale_mode) === 'credit_enabled')>掛売を利用</option>
            </select>
          </label>
          <label>販売時の在庫扱い
            <select name="inventory_sales_policy">
              <option value="strict_stock" @selected(old('inventory_sales_policy', $companySetting->inventory_sales_policy ?? 'strict_stock') === 'strict_stock')>在庫を表示し、在庫不足は販売不可</option>
              <option value="allow_negative_order" @selected(old('inventory_sales_policy', $companySetting->inventory_sales_policy ?? 'strict_stock') === 'allow_negative_order')>在庫を表示せず、不足でも販売して適時発注</option>
            </select>
          </label>
          <label>納品書
            <select name="delivery_note_policy">
              <option value="on_demand" @selected(old('delivery_note_policy', $companySetting->delivery_note_policy) === 'on_demand')>必要時に作成</option>
              <option value="per_sale" @selected(old('delivery_note_policy', $companySetting->delivery_note_policy) === 'per_sale')>販売ごとに作成</option>
            </select>
          </label>
          <label>請求書
            <select name="invoice_policy">
              <option value="monthly_credit" @selected(old('invoice_policy', $companySetting->invoice_policy) === 'monthly_credit')>掛売のみ月締め請求</option>
              <option value="per_invoice" @selected(old('invoice_policy', $companySetting->invoice_policy) === 'per_invoice')>都度請求</option>
            </select>
          </label>
        </article>

        <article class="card">
          <h3>仕入区分</h3>
          <p class="meta">蔵商品は蔵へ自動発注、外部商品は外部仕入先へ発注します。</p>
          <label>蔵商品
            <select name="brewery_procurement_policy">
              <option value="auto_order" @selected(old('brewery_procurement_policy', $companySetting->brewery_procurement_policy) === 'auto_order')>蔵へ自動発注</option>
            </select>
          </label>
          <label>酒蔵側取引先
            <select name="brewery_partner_id">
              <option value="">未設定</option>
              @foreach ($breweryCustomers as $customer)
                <option value="{{ $customer->id }}" @selected((string) old('brewery_partner_id', $brewerySupplier->brewery_partner_id) === (string) $customer->id)>
                  {{ $customer->customer_code }} / {{ $customer->name }}
                </option>
              @endforeach
            </select>
          </label>
          <label>外部商品
            <select name="external_procurement_policy">
              <option value="supplier_order" @selected(old('external_procurement_policy', $companySetting->external_procurement_policy) === 'supplier_order')>外部仕入先へ発注</option>
              <option value="manual" @selected(old('external_procurement_policy', $companySetting->external_procurement_policy) === 'manual')>手動発注</option>
            </select>
          </label>
        </article>

      </section>

      <div class="savebar">
        <a class="btn" href="{{ route('retail.pos') }}">キャンセル</a>
        <button class="btn primary" type="submit">設定全体を保存</button>
      </div>
    </form>
  </main>
</body>
</html>

