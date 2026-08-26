<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>発注 | {{ $retailSystemName ?? '小売販売システム' }}</title>
  <style>
    :root{--bg:{{ $retailTheme['bg'] ?? '#f3f6fa' }};--panel:{{ $retailTheme['card'] ?? '#fff' }};--line:{{ $retailTheme['line'] ?? '#d9e2ee' }};--text:#172033;--muted:#65758c;--blue:{{ $retailTheme['primary'] ?? '#0b6ff6' }};--blue-dark:{{ $retailTheme['primaryDark'] ?? '#075ecf' }};--sidebar:{{ $retailTheme['sidebar'] ?? '#10243b' }}}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--bg);color:var(--text);font-family:"Noto Sans JP",Meiryo,system-ui,sans-serif;padding-left:230px}.sidebar{position:fixed;inset:0 auto 0 0;width:230px;background:var(--sidebar);color:#eaf1fb;padding:18px 12px}.brand{padding:6px 10px 16px;border-bottom:1px solid rgba(255,255,255,.14)}.brand h1{margin:0;font-size:16px}.brand p{margin:8px 0 0;color:#a8b8cc;font-size:12px;line-height:1.6}.nav{display:grid;gap:4px;margin-top:16px}.nav a{display:flex;align-items:center;justify-content:space-between;min-height:38px;padding:0 12px;border-radius:6px;color:#dbe7f5;text-decoration:none;font-size:12px}.nav a.active{background:var(--blue);color:#fff;font-weight:800}.nav a span:last-child{color:#b8c6d9;font-size:11px}
    .content{padding:18px}.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px 16px;margin-bottom:12px}.topbar h2{margin:0;font-size:17px}.meta{color:var(--muted);font-size:12px;line-height:1.65}.panel{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px;margin-bottom:12px}.panel h3{margin:0 0 8px;font-size:15px}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:9px 8px;border-bottom:1px solid #edf1f6;text-align:left;font-size:12px;vertical-align:middle}.table th{background:#f8faff;color:#64748b;font-size:11px}.table tr.manual-required{background:#fff8e1}.btn{border:1px solid #c7d3e2;border-radius:6px;background:#fff;color:#1d3450;padding:8px 12px;font:inherit;font-size:12px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}.btn.primary{border-color:var(--blue);background:var(--blue);color:#fff;font-weight:800}.message{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#e9f8ef;color:#137333;font-size:12px}.warning{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#fff8e1;color:#7a4b00;border:1px solid #f6d365;font-size:12px}.error{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#fff1f0;color:#b42318;font-size:12px}.company-form{display:flex;gap:8px;align-items:end;flex-wrap:wrap}.company-form label{min-width:220px;color:#4d6078;font-size:11px;font-weight:800;display:grid;gap:6px}select{width:100%;min-height:36px;border:1px solid #cbd7e6;border-radius:6px;background:#fff;color:var(--text);font:inherit;padding:8px}.actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.pagination{display:flex;justify-content:flex-end;margin-top:12px}
    @media(max-width:980px){body{padding-left:0}.sidebar{position:static;width:auto}}@media(max-width:640px){.content{padding:10px}.topbar{align-items:flex-start;flex-direction:column}.table{display:block;overflow:auto}}
  </style>
</head>
<body>
  <span hidden>Retail Purchase Orders Shell Retail Menu</span>
  <aside class="sidebar">
    <div class="brand"><h1>{{ $retailSystemName ?? '小売販売システム' }}</h1><p>{{ $selectedCompany['name'] }} の業務メニュー</p></div>
    <nav class="nav">
      <a href="{{ route('retail.pos') }}"><span>販売入力</span><span>POS</span></a>
      <a href="{{ route('retail.sales.index') }}"><span>販売履歴</span><span>履歴</span></a>
      <a href="{{ route('retail.customers.index') }}"><span>小売顧客</span><span>顧客</span></a>
      <a href="{{ route('retail.products.index') }}"><span>外部商品</span><span>商品</span></a>
      <a href="{{ route('retail.products.import') }}"><span>取扱商品選択</span><span>選択</span></a>
      <a class="active" href="{{ route('retail.purchase-orders.index') }}"><span>発注</span><span>仕入</span></a>
      <a href="{{ route('retail.settings') }}"><span>設定</span><span>設定</span></a>
      <a href="{{ route('retail.system.manage') }}"><span>システム管理</span><span>管理</span></a>
    </nav>
  </aside>

  <main class="content">
    <header class="topbar">
      <div><h2>{{ $selectedCompany['name'] }} 発注</h2><div class="meta">{{ $selectedCompany['description'] }}。蔵商品は蔵へ自動発注、外部商品は外部仕入先へ発注します。</div></div>
      <form class="company-form" method="post" action="{{ route('retail.companies.select') }}">
        @csrf
        <input type="hidden" name="redirect_to" value="{{ route('retail.purchase-orders.index', absolute: false) }}">
        <label>会社切替
          <select name="company">
            @foreach ($companies as $key => $company)
              <option value="{{ $key }}" @selected($selectedCompanyKey === $key)>{{ $company['name'] }}</option>
            @endforeach
          </select>
        </label>
        <button class="btn" type="submit">切替</button>
        <a class="btn" href="{{ route('retail.pos') }}">販売へ戻る</a>
        <a class="btn" href="{{ route('retail.settings') }}">設定</a>
      </form>
    </header>

    @if (session('status'))<div class="message">{{ session('status') }}</div>@endif
    @if (($manualRequiredCount ?? 0) > 0)
      <div class="warning">蔵側で請求処理済みのため手動対応が必要な取消が {{ $manualRequiredCount }} 件あります。該当行の「蔵取消: manual_required」と理由を確認してください。</div>
    @endif
    @if ($errors->any())<div class="error">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <section class="panel">
      <h3>発注案作成</h3>
      <p class="meta">小売在庫が発注点を下回った商品から発注案を作成します。蔵商品は蔵API送信対象、外部商品は外部仕入先への発注対象です。</p>
      <form method="post" action="{{ route('retail.purchase-orders.suggestions') }}">
        @csrf
        <button class="btn primary" type="submit">在庫不足から発注案を作成</button>
      </form>
    </section>

    <section class="panel">
      <h3>発注一覧</h3>
      <table class="table">
        <thead>
          <tr><th>番号</th><th>仕入先</th><th>経路</th><th>状態</th><th>合計</th><th>操作</th></tr>
        </thead>
        <tbody>
          @forelse ($orders as $order)
            <tr @class(['manual-required' => $order->brewery_cancel_status === 'manual_required'])>
              <td>{{ $order->purchase_order_no }}</td>
              <td>{{ $order->supplier?->name }}</td>
              <td>{{ $order->order_route }}</td>
              <td>
                {{ $order->status }}
                @if($order->cancelled_at)
                  <div class="meta">取消: {{ $order->cancelled_at->format('Y-m-d H:i') }}</div>
                  <div class="meta">{{ $order->cancelled_reason }}</div>
                  @if($order->brewery_cancel_status)
                    <div class="meta">蔵取消: {{ $order->brewery_cancel_status }}</div>
                  @endif
                  @if($order->brewery_cancellation_sales_order_id)
                    <div class="meta">訂正受注ID: {{ $order->brewery_cancellation_sales_order_id }}</div>
                  @endif
                  @if($order->brewery_cancel_error)
                    <div class="meta">{{ $order->brewery_cancel_error }}</div>
                  @endif
                @endif
              </td>
              <td>¥{{ number_format((float) $order->total_amount) }}</td>
              <td>
                @if($order->order_route === 'brewery_api' && $order->status === 'draft')
                  <form class="inline-form" method="post" action="{{ route('retail.purchase-orders.send-brewery', $order) }}">
                    @csrf
                    <button class="btn primary" type="submit">蔵API送信</button>
                  </form>
                  <form class="inline-form" method="post" action="{{ route('retail.purchase-orders.destroy', $order) }}" onsubmit="return confirm('未送信の発注案を削除します。よろしいですか？');">
                    @csrf
                    @method('delete')
                    <button class="btn" type="submit">削除</button>
                  </form>
                @elseif($order->status !== 'cancelled')
                  <form class="inline-form" method="post" action="{{ route('retail.purchase-orders.cancel', $order) }}">
                    @csrf
                    <input name="reason" required maxlength="1000" placeholder="取消理由">
                    <button class="btn" type="submit">取消</button>
                  </form>
                @else
                  <span class="meta">操作なし</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="meta">発注案はまだありません。</td></tr>
          @endforelse
        </tbody>
      </table>
      <div class="pagination">{{ $orders->links() }}</div>
    </section>
  </main>
</body>
</html>

