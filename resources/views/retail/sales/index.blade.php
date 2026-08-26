<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>販売履歴 | {{ $retailSystemName ?? '小売販売システム' }}</title>
  <style>
    :root{--bg:{{ $retailTheme['bg'] ?? '#f3f6fa' }};--panel:{{ $retailTheme['card'] ?? '#fff' }};--line:{{ $retailTheme['line'] ?? '#d9e2ee' }};--text:#172033;--muted:#65758c;--blue:{{ $retailTheme['primary'] ?? '#0b6ff6' }};--sidebar:{{ $retailTheme['sidebar'] ?? '#10243b' }};--danger:#b42318;--danger-bg:#fff5f4}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--bg);color:var(--text);font-family:"Noto Sans JP",Meiryo,system-ui,sans-serif;padding-left:230px}.sidebar{position:fixed;inset:0 auto 0 0;width:230px;background:var(--sidebar);color:#eaf1fb;padding:18px 12px}.brand{padding:6px 10px 16px;border-bottom:1px solid rgba(255,255,255,.14)}.brand h1{margin:0;font-size:16px}.brand p{margin:8px 0 0;color:#a8b8cc;font-size:12px;line-height:1.6}.nav{display:grid;gap:4px;margin-top:16px}.nav a{display:flex;align-items:center;justify-content:space-between;min-height:38px;padding:0 12px;border-radius:6px;color:#dbe7f5;text-decoration:none;font-size:12px}.nav a.active{background:var(--blue);color:#fff;font-weight:800}.nav a span:last-child{color:#b8c6d9;font-size:11px}
    .content{padding:18px}.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px 16px;margin-bottom:12px}.topbar h2{margin:0;font-size:17px}.meta{color:var(--muted);font-size:12px;line-height:1.65}.company-form{display:flex;gap:8px;align-items:end;flex-wrap:wrap}.company-form label{min-width:220px}label{display:grid;gap:6px;color:#4d6078;font-size:11px;font-weight:800}input,select,textarea{width:100%;border:1px solid #cbd7e6;border-radius:6px;background:#fff;color:var(--text);font:inherit;padding:9px}.btn{min-height:38px;border:1px solid #c7d3e2;border-radius:6px;background:#fff;color:#1d3450;padding:8px 13px;font:inherit;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}.btn.primary{border-color:var(--blue);background:var(--blue);color:#fff;font-weight:800}.btn.danger{min-width:126px;min-height:44px;border-color:#d92d20;background:#d92d20;color:#fff;font-weight:800}.btn.danger:hover{background:#b42318}@keyframes searchPulse{0%,100%{background:#fff7d6;border-color:#f0b429;box-shadow:0 0 0 0 rgba(240,180,41,.28)}50%{background:#ffe08a;border-color:#d89b00;box-shadow:0 0 0 5px rgba(240,180,41,.12)}}button.search-attention,.btn.primary.search-attention{animation:searchPulse 1.8s ease-in-out infinite;color:#172033!important;font-weight:800}.message{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#e9f8ef;color:#137333;font-size:12px}.error{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#fff1f0;color:var(--danger);font-size:12px}.badge{display:inline-flex;align-items:center;min-height:24px;border-radius:999px;background:#eef1f5;color:#475569;font-size:11px;font-weight:800;padding:0 9px}
    .history-layout{display:grid;grid-template-columns:minmax(380px,42%) minmax(430px,58%);gap:12px;align-items:start}.panel{background:var(--panel);border:1px solid var(--line);border-radius:8px}.panel-title{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:13px 14px;border-bottom:1px solid var(--line)}.panel-title h3{margin:0;font-size:14px}.search-form{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:12px 14px;border-bottom:1px solid var(--line);background:#fbfdff}.search-form .wide{grid-column:1/-1}.search-actions{display:flex;justify-content:flex-end;gap:7px;grid-column:1/-1}.search-form label{min-width:0}.history-list{overflow:auto;max-height:calc(100vh - 405px);min-height:300px}.history-table{width:100%;border-collapse:collapse}.history-table th{position:sticky;top:0;z-index:2;background:#f6f9fd;color:#64748b;padding:9px 10px;border-bottom:1px solid var(--line);text-align:left;font-size:11px;white-space:nowrap}.history-table td{padding:0;border-bottom:1px solid #edf1f6}.sale-select{display:grid;grid-template-columns:minmax(125px,1.15fr) minmax(110px,1fr) minmax(86px,.8fr);gap:8px;align-items:center;width:100%;min-height:62px;padding:9px 10px;border:0;border-left:4px solid transparent;background:#fff;color:var(--text);font:inherit;text-align:left;cursor:pointer}.sale-select:hover{background:#f5f9ff}.sale-select.is-active{border-left-color:var(--blue);background:#edf5ff}.sale-number{font-size:12px;font-weight:800;color:#17436f}.sale-customer{overflow:hidden;color:#475569;font-size:11px;text-overflow:ellipsis;white-space:nowrap}.sale-amount{text-align:right;font-size:12px;font-weight:800}.sale-sub{display:block;margin-top:3px;color:var(--muted);font-size:10px;font-weight:400}.pagination-wrap{padding:10px 14px;border-top:1px solid var(--line);overflow:auto}
    .detail-panel{position:sticky;top:18px;min-height:530px}.sale-detail[hidden]{display:none}.detail-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:16px;border-bottom:1px solid var(--line)}.detail-head h3{margin:0 0 5px;font-size:17px}.detail-body{padding:16px}.detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:14px}.info-box{padding:10px;border:1px solid #e4eaf2;border-radius:6px;background:#fafcff}.info-box span{display:block;color:var(--muted);font-size:10px}.info-box strong{display:block;margin-top:4px;font-size:12px}.detail-table{width:100%;border-collapse:collapse}.detail-table th,.detail-table td{padding:8px;border-bottom:1px solid #edf1f6;text-align:left;font-size:11px}.detail-table th{background:#f8faff;color:#64748b}.detail-table .numeric{text-align:right;white-space:nowrap}.detail-total{display:flex;justify-content:flex-end;gap:22px;padding:13px 8px;font-size:12px}.detail-total strong{font-size:19px;color:var(--blue)}.cancel-box{margin-top:14px;padding:14px;border:1px solid #f0c1bc;border-radius:7px;background:var(--danger-bg)}.cancel-box h4{margin:0 0 5px;color:#8f1d14;font-size:13px}.cancel-form textarea{width:100%;min-height:82px;resize:vertical}.cancel-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:10px}.empty-detail{display:grid;min-height:530px;place-items:center;padding:30px;color:var(--muted);font-size:13px;text-align:center}
    @media(max-width:1180px){.history-layout{grid-template-columns:minmax(340px,40%) minmax(420px,60%)}.detail-grid{grid-template-columns:1fr 1fr}}
    @media(max-width:980px){body{padding-left:0}.sidebar{position:static;width:auto}.history-layout{grid-template-columns:1fr}.history-list{max-height:440px;min-height:320px}.detail-panel{position:static}.topbar{align-items:flex-start;flex-direction:column}}
    @media(max-width:620px){.content{padding:10px}.sale-select{grid-template-columns:1fr auto}.sale-customer{grid-column:1/-1}.detail-grid{grid-template-columns:1fr}.detail-table{display:block;overflow:auto}.cancel-actions{align-items:stretch;flex-direction:column}.btn.danger{width:100%}}
  </style>
</head>
<body>
  <aside class="sidebar">
    <div class="brand"><h1>{{ $retailSystemName ?? '小売販売システム' }}</h1><p>{{ $selectedCompany['name'] }} の業務メニュー</p></div>
    <nav class="nav">
      <a href="{{ route('retail.pos') }}"><span>販売入力</span><span>POS</span></a>
      <a class="active" href="{{ route('retail.sales.index') }}"><span>販売履歴</span><span>履歴</span></a>
      <a href="{{ route('retail.customers.index') }}"><span>小売顧客</span><span>顧客</span></a>
      <a href="{{ route('retail.products.index') }}"><span>外部商品</span><span>商品</span></a>
      <a href="{{ route('retail.products.import') }}"><span>取扱商品選択</span><span>選択</span></a>
      <a href="{{ route('retail.settings') }}"><span>設定</span><span>設定</span></a>
      <a href="{{ route('retail.system.manage') }}"><span>システム管理</span><span>管理</span></a>
    </nav>
  </aside>

  <main class="content">
    <header class="topbar">
      <div>
        <h2>{{ $selectedCompany['name'] }} 販売履歴</h2>
        <div class="meta">左の一覧から伝票を選ぶと、右側に明細と操作を表示します。</div>
      </div>
      <form class="company-form" method="post" action="{{ route('retail.companies.select') }}">
        @csrf
        <input type="hidden" name="redirect_to" value="{{ route('retail.sales.index', absolute: false) }}">
        <label>会社切替
          <select name="company">
            @foreach ($companies as $key => $company)
              <option value="{{ $key }}" @selected($selectedCompanyKey === $key)>{{ $company['name'] }}</option>
            @endforeach
          </select>
        </label>
        <button class="btn" type="submit">切替</button>
        <a class="btn primary" href="{{ route('retail.pos') }}">販売入力へ</a>
      </form>
    </header>

    @if (session('status'))<div class="message">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div class="history-layout">
      <section class="panel">
        <div class="panel-title"><h3>販売伝票を選択</h3><span class="meta">{{ $sales->total() }}件</span></div>
        <form class="search-form" method="get" action="{{ route('retail.sales.index') }}">
          <label class="wide">キーワード
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="伝票番号・顧客・商品名">
          </label>
          <label>販売日（開始）<input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
          <label>販売日（終了）<input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
          <label>伝票状態
            <select name="status">
              <option value="">すべて</option>
              @foreach (['posted' => '登録済', 'revised' => '変更済', 'cancelled' => '取消済', 'credit_note' => '赤伝'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
              @endforeach
            </select>
          </label>
          <label>支払区分
            <select name="sale_type">
              <option value="">すべて</option>
              @foreach (['cash' => '現金', 'credit' => '掛売', 'card' => 'カード', 'qr' => 'QR'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['sale_type'] ?? '') === $value)>{{ $label }}</option>
              @endforeach
            </select>
          </label>
          <div class="search-actions"><a class="btn" href="{{ route('retail.sales.index') }}">クリア</a><button class="btn primary" type="submit">検索</button></div>
        </form>
        <div class="history-list" id="history-list">
          <table class="history-table">
            <thead><tr><th>伝票・日付</th><th>顧客</th><th style="text-align:right">金額・状態</th></tr></thead>
            <tbody>
              @forelse ($sales as $sale)
                <tr>
                  <td colspan="3">
                    <button class="sale-select @if($loop->first) is-active @endif" type="button" data-sale-target="sale-detail-{{ $sale->id }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
                      <span class="sale-number">{{ $sale->sale_no }}<span class="sale-sub">{{ $sale->sale_date->format('Y-m-d') }}</span></span>
                      <span class="sale-customer">{{ $sale->customer?->name ?: '店頭一般客' }}<span class="sale-sub">{{ ['cash' => '現金', 'credit' => '掛売', 'card' => 'カード', 'qr' => 'QR'][$sale->sale_type] ?? $sale->sale_type }}</span></span>
                      <span class="sale-amount">¥{{ number_format((float) $sale->total_amount) }}<span class="sale-sub">{{ $sale->correction_type === 'credit_note' ? '赤伝' : (['posted' => '登録済', 'revised' => '変更済', 'cancelled' => '取消済'][$sale->status] ?? $sale->status) }}</span></span>
                    </button>
                  </td>
                </tr>
              @empty
                <tr><td colspan="3" style="padding:30px;text-align:center" class="meta">販売履歴はありません。</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="pagination-wrap">{{ $sales->links() }}</div>
      </section>

      <section class="panel detail-panel" aria-live="polite">
        @forelse ($sales as $sale)
          @php
            $isClosed = $sale->invoiceLines->isNotEmpty();
            $hasDelivery = $sale->deliveries->where('status', 'issued')->isNotEmpty();
            $canCancel = $sale->status !== 'cancelled' && ! $isClosed && ! $hasDelivery && $sale->correction_type !== 'credit_note';
          @endphp
          <article class="sale-detail" id="sale-detail-{{ $sale->id }}" @if(! $loop->first) hidden @endif>
            <div class="detail-head">
              <div><h3>{{ $sale->sale_no }}</h3><div class="meta">{{ $sale->sale_date->format('Y-m-d') }} / {{ $sale->customer?->name ?: '店頭一般客' }}</div></div>
              <a class="btn" href="{{ route('retail.sales.show', $sale) }}">伝票変更・再発行</a>
            </div>
            <div class="detail-body">
              <div class="detail-grid">
                <div class="info-box"><span>支払区分</span><strong>{{ ['cash' => '現金', 'credit' => '掛売', 'card' => 'カード', 'qr' => 'QR'][$sale->sale_type] ?? $sale->sale_type }}</strong></div>
                <div class="info-box"><span>伝票状態</span><strong>{{ $sale->correction_type === 'credit_note' ? '赤伝' : (['posted' => '登録済', 'revised' => '変更済', 'cancelled' => '取消済'][$sale->status] ?? $sale->status) }}</strong></div>
                <div class="info-box"><span>蔵連携</span><strong>{{ ['not_required' => '対象外', 'ordered' => '発注済', 'order_revised' => '蔵受注変更済', 'order_cancelled' => '蔵受注取消済', 'correction_sent' => '訂正依頼済', 'revision_no_change' => '蔵商品差分なし', 'failed' => '連携失敗'][$sale->brewery_sync_status] ?? $sale->brewery_sync_status }}</strong></div>
              </div>
              <table class="detail-table">
                <thead><tr><th>商品</th><th class="numeric">数量</th><th class="numeric">単価（税抜）</th><th class="numeric">金額（税抜）</th></tr></thead>
                <tbody>
                  @foreach ($sale->items as $item)
                    <tr><td>{{ $item->description }}</td><td class="numeric">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}</td><td class="numeric">¥{{ number_format((float) $item->unit_price) }}</td><td class="numeric">¥{{ number_format((float) $item->line_amount) }}</td></tr>
                  @endforeach
                </tbody>
              </table>
              <div class="detail-total"><span>消費税 ¥{{ number_format((float) $sale->tax_amount) }}</span><span>合計（税込） <strong>¥{{ number_format((float) $sale->total_amount) }}</strong></span></div>

              @if ($canCancel)
                <div class="cancel-box">
                  <h4>この販売伝票を取り消す</h4>
                  <div class="meta">取消後は在庫と蔵側受注にも連携します。理由を入力してから実行してください。</div>
                  <form class="cancel-form" method="post" action="{{ route('retail.sales.cancel', $sale) }}">
                    @csrf
                    <label style="margin-top:10px">取消理由<textarea name="reason" required maxlength="1000" placeholder="取消理由を入力してください"></textarea></label>
                    <div class="cancel-actions"><span class="meta">この操作は販売履歴に記録されます。</span><button class="btn danger" type="submit">伝票を取消</button></div>
                  </form>
                </div>
              @else
                <div class="cancel-box">
                  <h4>取消できません</h4>
                  <div class="meta">@if($sale->status === 'cancelled')この伝票は取消済みです。@elseif($isClosed)締め処理済みのため、必要に応じて赤伝を作成してください。@elseif($hasDelivery)納品書作成済みのため、伝票詳細で処理状況を確認してください。@elseこの伝票は取消対象外です。@endif</div>
                </div>
              @endif
            </div>
          </article>
        @empty
          <div class="empty-detail">左側に表示できる販売伝票がありません。</div>
        @endforelse
      </section>
    </div>
  </main>
  <script>
    (() => {
      const list = document.getElementById('history-list');
      const buttons = [...document.querySelectorAll('[data-sale-target]')];
      const details = [...document.querySelectorAll('.sale-detail')];

      buttons.forEach((button) => {
        button.addEventListener('click', () => {
          const targetId = button.dataset.saleTarget;
          buttons.forEach((item) => {
            const selected = item === button;
            item.classList.toggle('is-active', selected);
            item.setAttribute('aria-pressed', selected ? 'true' : 'false');
          });
          details.forEach((detail) => { detail.hidden = detail.id !== targetId; });
        });
      });

      if (list) {
        const storageKey = 'retail-sales-history-scroll';
        const savedPosition = Number(sessionStorage.getItem(storageKey) || 0);
        if (savedPosition > 0) list.scrollTop = savedPosition;
        list.addEventListener('scroll', () => sessionStorage.setItem(storageKey, String(list.scrollTop)), { passive: true });
      }
      document.querySelectorAll('form.search-form').forEach(form => {
        const button = form.querySelector('button[type="submit"]');
        const markDirty = () => button?.classList.add('search-attention');
        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);
        form.addEventListener('submit', () => button?.classList.remove('search-attention'));
      });
    })();
  </script>
</body>
</html>
