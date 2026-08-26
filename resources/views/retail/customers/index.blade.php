<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>小売顧客 | {{ $retailSystemName ?? '小売販売システム' }}</title>
  <style>
    :root{--bg:{{ $retailTheme['bg'] ?? '#f3f6fa' }};--panel:{{ $retailTheme['card'] ?? '#fff' }};--soft:#f8fbff;--line:{{ $retailTheme['line'] ?? '#d9e2ee' }};--text:#172033;--muted:#65758c;--blue:{{ $retailTheme['primary'] ?? '#0b6ff6' }};--blue-dark:{{ $retailTheme['primaryDark'] ?? '#075ecf' }};--red:#b42318;--sidebar:{{ $retailTheme['sidebar'] ?? '#10243b' }}}
    *{box-sizing:border-box}
    body{margin:0;min-height:100vh;background:var(--bg);color:var(--text);font-family:"Noto Sans JP",Meiryo,system-ui,sans-serif;padding-left:230px}
    .sidebar{position:fixed;inset:0 auto 0 0;width:230px;background:var(--sidebar);color:#eaf1fb;padding:18px 12px}
    .brand{padding:6px 10px 16px;border-bottom:1px solid rgba(255,255,255,.14)}
    .brand h1{margin:0;font-size:16px}.brand p{margin:8px 0 0;color:#a8b8cc;font-size:12px;line-height:1.6}
    .nav{display:grid;gap:4px;margin-top:16px}.nav a{display:flex;align-items:center;justify-content:space-between;min-height:38px;padding:0 12px;border-radius:6px;color:#dbe7f5;text-decoration:none;font-size:12px}.nav a.active{background:var(--blue);color:#fff;font-weight:800}.nav a span:last-child{color:#b8c6d9;font-size:11px}
    .content{padding:18px}.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px 16px;margin-bottom:12px}.topbar h2{margin:0;font-size:17px}.meta{color:var(--muted);font-size:12px;line-height:1.65}
    .layout{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:12px;align-items:start}.panel{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px}.panel h3{margin:0 0 8px;font-size:15px}
    .filters{display:grid;grid-template-columns:1fr 160px auto;gap:8px;margin-bottom:12px;align-items:end}.field{display:grid;gap:6px;margin-top:10px}.field label,label{color:#4d6078;font-size:11px;font-weight:800}input,select,textarea{width:100%;min-height:36px;border:1px solid #cbd7e6;border-radius:6px;background:#fff;color:var(--text);font:inherit;padding:8px}textarea{min-height:82px;resize:vertical}
    .btn{border:1px solid #c7d3e2;border-radius:6px;background:#fff;color:#1d3450;padding:8px 12px;font:inherit;font-size:12px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}.btn.primary{border-color:var(--blue);background:var(--blue);color:#fff;font-weight:800}.btn.danger{border-color:#f1b7b1;color:var(--red)}@keyframes searchPulse{0%,100%{background:#fff7d6;border-color:#f0b429;box-shadow:0 0 0 0 rgba(240,180,41,.28)}50%{background:#ffe08a;border-color:#d89b00;box-shadow:0 0 0 5px rgba(240,180,41,.12)}}button.search-attention,.btn.primary.search-attention{animation:searchPulse 1.8s ease-in-out infinite;color:#172033!important;font-weight:800}
    .table{width:100%;border-collapse:collapse}.table th,.table td{padding:9px 8px;border-bottom:1px solid #edf1f6;text-align:left;font-size:12px;vertical-align:top}.table th{background:#f8faff;color:#64748b;font-size:11px}.code{font-family:Consolas,monospace;color:var(--blue-dark);font-size:11px}.badge{display:inline-flex;align-items:center;min-height:24px;border-radius:999px;background:#e8f2ff;color:var(--blue-dark);font-size:11px;font-weight:800;padding:0 9px}.badge.off{background:#eef1f5;color:#64748b}.actions{display:flex;gap:6px;justify-content:flex-end}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.full{grid-column:1/-1}.checks{display:flex;gap:16px;align-items:center;margin-top:10px}.checks label{display:flex;align-items:center;gap:6px;color:#334155}.checks input{width:auto;min-height:auto}.message{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#e9f8ef;color:#137333;font-size:12px}.error{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#fff1f0;color:var(--red);font-size:12px}.pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px;color:var(--muted);font-size:12px}.pagination-controls{display:flex;align-items:center;gap:8px}.page-link,.page-disabled,.page-current{display:inline-flex;align-items:center;justify-content:center;min-height:34px;border:1px solid #c7d3e2;border-radius:6px;padding:0 12px}.page-link{background:#fff;color:#1d3450;text-decoration:none;font-weight:800}.page-link:hover{border-color:var(--blue);color:var(--blue-dark)}.page-disabled{background:#f5f7fa;color:#9aa7b8}.page-current{border-color:#dce7f4;background:var(--soft);color:#43546a;font-weight:800}
    @media(max-width:1080px){body{padding-left:0}.sidebar{position:static;width:auto}.layout{grid-template-columns:1fr}.filters{grid-template-columns:1fr}}
    @media(max-width:640px){.content{padding:10px}.topbar{align-items:flex-start;flex-direction:column}.form-grid{grid-template-columns:1fr}.full{grid-column:auto}.table{display:block;overflow:auto}.pagination{align-items:flex-start;flex-direction:column}.pagination-controls{width:100%;justify-content:space-between}}
  </style>
</head>
<body>
  <aside class="sidebar">
    <div class="brand">
      <h1>{{ $retailSystemName ?? '小売販売システム' }}</h1>
      <p>顧客・納品書・請求書・入金を小売側で管理します。</p>
    </div>
    <nav class="nav">
      <a href="{{ route('retail.pos') }}"><span>販売画面</span><span>POS</span></a>
      <a href="{{ route('retail.sales.index') }}"><span>販売履歴</span><span>履歴</span></a>
      <a class="active" href="{{ route('retail.customers.index') }}"><span>小売顧客</span><span>顧客</span></a>
      <a href="{{ route('retail.products.index') }}"><span>外部商品</span><span>商品</span></a>
      <a href="{{ route('retail.products.import') }}"><span>取扱商品選択</span><span>選択</span></a>
      <a href="{{ route('retail.settings') }}"><span>設定</span><span>設定</span></a>
      <a href="{{ route('retail.system.manage') }}"><span>システム管理</span><span>管理</span></a>
    </nav>
  </aside>

  <main class="content">
    <header class="topbar">
      <div>
        <h2>小売顧客</h2>
        <div class="meta">掛売・納品書・請求書・入金管理の基礎になる顧客マスタです。</div>
      </div>
      <a class="btn" href="{{ route('retail.index') }}">小売メニューへ</a>
    </header>

    @if (session('status'))
      <div class="message">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
      <div class="error">
        @foreach ($errors->all() as $error)
          <div>{{ $error }}</div>
        @endforeach
      </div>
    @endif

    <section class="layout">
      <section class="panel">
        <h3>顧客一覧</h3>
        <form class="filters" method="get" action="{{ route('retail.customers.index') }}">
          <label>検索
            <input name="q" value="{{ $filters['q'] }}" placeholder="顧客コード・名称・電話・住所">
          </label>
          <label>状態
            <select name="active">
              <option value="active" @selected($filters['active'] === 'active')>有効</option>
              <option value="inactive" @selected($filters['active'] === 'inactive')>停止</option>
              <option value="all" @selected($filters['active'] === 'all')>すべて</option>
            </select>
          </label>
          <button class="btn primary" type="submit">検索</button>
        </form>

        <table class="table">
          <thead>
            <tr>
              <th>コード</th>
              <th>顧客名</th>
              <th>所属</th>
              <th>請求条件</th>
              <th>連絡先</th>
              <th>状態</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse ($customers as $customer)
              <tr>
                <td class="code">{{ $customer->customer_code }}</td>
                <td>
                  <strong>{{ $customer->name }}</strong>
                  @if ($customer->billing_name)
                    <div class="meta">請求先: {{ $customer->billing_name }}</div>
                  @endif
                </td>
                <td>{{ $customer->company?->name ?? '全社共通' }}</td>
                <td>
                  @if ($customer->invoice_required)
                    {{ ['monthly' => '締め請求', 'per_sale' => '都度請求', 'none' => '請求なし'][$customer->billing_method] ?? '会社設定に従う' }}
                    @if (($customer->billing_method ?? 'monthly') === 'monthly')
                      <div class="meta">{{ $customer->closing_day ? $customer->closing_day.'日締め' : '締日未設定' }}</div>
                    @endif
                    <div class="meta">支払: {{ $customer->payment_month_offset }}か月後{{ $customer->payment_day ? $customer->payment_day.'日' : '末日' }}</div>
                  @else
                    都度・現金中心
                  @endif
                </td>
                <td>
                  {{ $customer->phone ?: '-' }}
                  @if ($customer->address1)
                    <div class="meta">{{ $customer->address1 }}</div>
                  @endif
                </td>
                <td><span class="badge @class(['off' => ! $customer->is_active])">{{ $customer->is_active ? '有効' : '停止' }}</span></td>
                <td class="actions"><a class="btn" href="{{ route('retail.customers.index', ['edit' => $customer->id, ...request()->only(['q', 'active'])]) }}">編集</a></td>
              </tr>
            @empty
              <tr><td colspan="7" class="meta">該当する小売顧客はありません。</td></tr>
            @endforelse
          </tbody>
        </table>

        @if ($customers->total() > 0)
          <div class="pagination" data-testid="customer-pagination">
            <span>{{ number_format($customers->firstItem()) }}〜{{ number_format($customers->lastItem()) }}件 / 全{{ number_format($customers->total()) }}件</span>
            @if ($customers->hasPages())
              <nav class="pagination-controls" aria-label="顧客一覧ページ">
                @if ($customers->onFirstPage())
                  <span class="page-disabled" aria-disabled="true">前へ</span>
                @else
                  <a class="page-link" href="{{ $customers->previousPageUrl() }}" rel="prev">前へ</a>
                @endif

                <span class="page-current" aria-current="page">{{ number_format($customers->currentPage()) }} / {{ number_format($customers->lastPage()) }}ページ</span>

                @if ($customers->hasMorePages())
                  <a class="page-link" href="{{ $customers->nextPageUrl() }}" rel="next">次へ</a>
                @else
                  <span class="page-disabled" aria-disabled="true">次へ</span>
                @endif
              </nav>
            @endif
          </div>
        @endif
      </section>

      <aside class="panel">
        <h3>{{ $editingCustomer ? '顧客編集' : '新規顧客登録' }}</h3>
        <form method="post" action="{{ $editingCustomer ? route('retail.customers.update', $editingCustomer) : route('retail.customers.store') }}">
          @csrf
          @if ($editingCustomer)
            @method('put')
          @endif

          <div class="form-grid">
            <div class="field">
              <label>顧客コード</label>
              <input name="customer_code" value="{{ old('customer_code', $editingCustomer?->customer_code) }}" required>
            </div>
            <div class="field">
              <label>所属会社</label>
              <select name="retail_company_id">
                <option value="">全社共通</option>
                @foreach ($companies as $company)
                  <option value="{{ $company->id }}" @selected((string) old('retail_company_id', $editingCustomer ? $editingCustomer->retail_company_id : $selectedCompanyId) === (string) $company->id)>{{ $company->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="field">
              <label>顧客名</label>
              <input name="name" value="{{ old('name', $editingCustomer?->name) }}" required>
            </div>
            <div class="field">
              <label>カナ</label>
              <input name="name_kana" value="{{ old('name_kana', $editingCustomer?->name_kana) }}">
            </div>
            <div class="field">
              <label>請求先名</label>
              <input name="billing_name" value="{{ old('billing_name', $editingCustomer?->billing_name) }}">
            </div>
            <div class="field full">
              <label>請求方式</label>
              <select name="billing_method">
                <option value="" @selected(old('billing_method', $editingCustomer?->billing_method) === null)>会社設定に従う</option>
                <option value="monthly" @selected(old('billing_method', $editingCustomer?->billing_method) === 'monthly')>締め請求</option>
                <option value="per_sale" @selected(old('billing_method', $editingCustomer?->billing_method) === 'per_sale')>都度請求</option>
                <option value="none" @selected(old('billing_method', $editingCustomer?->billing_method) === 'none')>請求なし</option>
              </select>
              <div class="meta">未設定の場合だけ、現在選択中の会社の請求設定を使用します。</div>
            </div>
            <div class="field">
              <label>郵便番号</label>
              <input name="postal_code" value="{{ old('postal_code', $editingCustomer?->postal_code) }}">
            </div>
            <div class="field">
              <label>電話番号</label>
              <input name="phone" value="{{ old('phone', $editingCustomer?->phone) }}">
            </div>
            <div class="field">
              <label>FAX番号</label>
              <input name="fax" value="{{ old('fax', $editingCustomer?->fax) }}">
            </div>
            <div class="field full">
              <label>住所1</label>
              <input name="address1" value="{{ old('address1', $editingCustomer?->address1) }}">
            </div>
            <div class="field full">
              <label>住所2</label>
              <input name="address2" value="{{ old('address2', $editingCustomer?->address2) }}">
            </div>
            <div class="field full">
              <label>メール</label>
              <input name="email" value="{{ old('email', $editingCustomer?->email) }}">
            </div>
            <div class="field">
              <label>締日</label>
              <input name="closing_day" type="number" min="1" max="31" value="{{ old('closing_day', $editingCustomer?->closing_day) }}" placeholder="例: 31">
            </div>
            <div class="field">
              <label>支払月</label>
              <select name="payment_month_offset">
                @foreach ([0 => '当月', 1 => '翌月', 2 => '翌々月', 3 => '3か月後'] as $value => $label)
                  <option value="{{ $value }}" @selected((string) old('payment_month_offset', $editingCustomer?->payment_month_offset ?? 1) === (string) $value)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="field">
              <label>支払日</label>
              <input name="payment_day" type="number" min="1" max="31" value="{{ old('payment_day', $editingCustomer?->payment_day) }}" placeholder="空欄なら末日">
            </div>
            <div class="field">
              <label>与信限度額</label>
              <input name="credit_limit" type="number" min="0" step="1" value="{{ old('credit_limit', $editingCustomer?->credit_limit) }}">
            </div>
            <div class="field full">
              <label>備考</label>
              <textarea name="note">{{ old('note', $editingCustomer?->note) }}</textarea>
            </div>
          </div>

          <div class="checks">
            <label><input type="checkbox" name="invoice_required" value="1" @checked(old('invoice_required', $editingCustomer?->invoice_required))> 請求対象（会社設定に従う場合）</label>
            <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingCustomer?->is_active ?? true))> 有効</label>
          </div>

          <div class="actions" style="margin-top:14px">
            @if ($editingCustomer)
              <a class="btn" href="{{ route('retail.customers.index') }}">新規登録へ</a>
            @endif
            <button class="btn primary" type="submit">{{ $editingCustomer ? '更新' : '登録' }}</button>
          </div>
        </form>
      </aside>
    </section>
  </main>
  <script>
    document.querySelectorAll('form.filters').forEach(form => {
      const button = form.querySelector('button[type="submit"]');
      const markDirty = () => button?.classList.add('search-attention');
      form.addEventListener('input', markDirty);
      form.addEventListener('change', markDirty);
      form.addEventListener('submit', () => button?.classList.remove('search-attention'));
    });
  </script>
</body>
</html>

