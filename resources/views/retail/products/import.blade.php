<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>取扱商品選択 | {{ $retailSystemName ?? '小売販売システム' }}</title>
  <style>
    :root{--bg:{{ $retailTheme['bg'] ?? '#f3f6fa' }};--panel:{{ $retailTheme['card'] ?? '#fff' }};--line:{{ $retailTheme['line'] ?? '#d9e2ee' }};--text:#172033;--muted:#65758c;--blue:{{ $retailTheme['primary'] ?? '#0b6ff6' }};--blue-dark:{{ $retailTheme['primaryDark'] ?? '#075ecf' }};--sidebar:{{ $retailTheme['sidebar'] ?? '#10243b' }}}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--bg);color:var(--text);font-family:"Noto Sans JP",Meiryo,system-ui,sans-serif;padding-left:230px}.sidebar{position:fixed;inset:0 auto 0 0;width:230px;background:var(--sidebar);color:#eaf1fb;padding:18px 12px}.brand{padding:6px 10px 16px;border-bottom:1px solid rgba(255,255,255,.14)}.brand h1{margin:0;font-size:16px}.brand p{margin:8px 0 0;color:#a8b8cc;font-size:12px;line-height:1.6}.nav{display:grid;gap:4px;margin-top:16px}.nav a{display:flex;align-items:center;justify-content:space-between;min-height:38px;padding:0 12px;border-radius:6px;color:#dbe7f5;text-decoration:none;font-size:12px}.nav a.active{background:var(--blue);color:#fff;font-weight:800}.nav a span:last-child{color:#b8c6d9;font-size:11px}
    .content{padding:18px}.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px 16px;margin-bottom:12px}.topbar h2{margin:0;font-size:17px}.meta{color:var(--muted);font-size:12px;line-height:1.65}.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}.tab{border:1px solid var(--line);border-radius:999px;background:#fff;color:#1d3450;padding:9px 14px;font-size:12px;font-weight:800;text-decoration:none}.tab.active{border-color:var(--blue);background:var(--blue);color:#fff}.panel{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px;margin-bottom:12px}.panel h3{margin:0 0 8px;font-size:15px}.filters{display:grid;grid-template-columns:minmax(220px,1fr) 180px 180px auto;gap:8px;margin-bottom:12px;align-items:end}label{color:#4d6078;font-size:11px;font-weight:800;display:grid;gap:6px}input,select{width:100%;min-height:36px;border:1px solid #cbd7e6;border-radius:6px;background:#fff;color:var(--text);font:inherit;padding:8px}input[type=checkbox]{width:18px;min-height:18px;padding:0}.btn{border:1px solid #c7d3e2;border-radius:6px;background:#fff;color:#1d3450;padding:8px 12px;font:inherit;font-size:12px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}.btn.primary{border-color:var(--blue);background:var(--blue);color:#fff;font-weight:800}.btn:disabled{opacity:.5;cursor:not-allowed}@keyframes searchPulse{0%,100%{background:#fff7d6;border-color:#f0b429;box-shadow:0 0 0 0 rgba(240,180,41,.28)}50%{background:#ffe08a;border-color:#d89b00;box-shadow:0 0 0 5px rgba(240,180,41,.12)}}button.search-attention,.btn.primary.search-attention{animation:searchPulse 1.8s ease-in-out infinite;color:#172033!important;font-weight:800}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:9px 8px;border-bottom:1px solid #edf1f6;text-align:left;font-size:12px;vertical-align:top}.table th{background:#f8faff;color:#64748b;font-size:11px}.code{font-family:Consolas,monospace;color:var(--blue-dark);font-size:11px}.badge{display:inline-flex;align-items:center;min-height:24px;border-radius:999px;background:#e8f2ff;color:var(--blue-dark);font-size:11px;font-weight:800;padding:0 9px}.badge.done{background:#e9f8ef;color:#137333}.badge.warn{background:#fff4df;color:#b9770e}.message{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#e9f8ef;color:#137333;font-size:12px}.error{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#fff1f0;color:#b42318;font-size:12px}.pagination{display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-top:12px;color:var(--muted);font-size:12px}.page-btn{min-width:74px}.page-btn.disabled{opacity:.45;pointer-events:none}.price-toolbar,.bulk-toolbar{display:flex;gap:10px;align-items:end;justify-content:space-between;flex-wrap:wrap}.price-actions{display:flex;gap:8px;align-items:end;flex-wrap:wrap}.inline-form{display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap}.check-cell{width:44px;text-align:center}.selection-state{font-size:11px;color:var(--muted)}
    @media(max-width:980px){body{padding-left:0}.sidebar{position:static;width:auto}.filters{grid-template-columns:1fr}.table{display:block;overflow:auto}}@media(max-width:640px){.content{padding:10px}.topbar{align-items:flex-start;flex-direction:column}}
  </style>
</head>
<body>
  <aside class="sidebar">
    <div class="brand">
      <h1>{{ $retailSystemName ?? '小売販売システム' }}</h1>
      <p>蔵商品は蔵へ自動発注、外部商品は外部仕入先へ発注します。</p>
    </div>
    <nav class="nav">
      <a href="{{ route('retail.pos') }}"><span>販売入力</span><span>POS</span></a>
      <a href="{{ route('retail.sales.index') }}"><span>販売履歴</span><span>履歴</span></a>
      <a href="{{ route('retail.customers.index') }}"><span>小売顧客</span><span>顧客</span></a>
      <a href="{{ route('retail.products.index') }}"><span>外部商品</span><span>商品</span></a>
      <a class="active" href="{{ route('retail.products.import') }}"><span>取扱商品選択</span><span>選択</span></a>
      <a href="{{ route('retail.settings') }}"><span>設定</span><span>設定</span></a>
      <a href="{{ route('retail.system.manage') }}"><span>システム管理</span><span>管理</span></a>
    </nav>
  </aside>

  <main class="content">
    <header class="topbar">
      <div>
        <h2>取扱商品選択</h2>
        <div class="meta">小売側で扱う蔵商品を選択して追加します。蔵の卸価格を税抜原価、蔵の小売価格を税抜販売価格として初回追加します。</div>
      </div>
      <a class="btn" href="{{ route('retail.pos') }}">販売へ戻る</a>
    </header>

    @if (session('status'))<div class="message">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div class="tabs">
      <a class="tab {{ $filters['tab'] === 'import' ? 'active' : '' }}" href="{{ route('retail.products.import', ['tab' => 'import', 'q' => $filters['q'], 'category' => $filters['category'], 'status' => $filters['status']]) }}">取扱商品選択</a>
      <a class="tab {{ $filters['tab'] === 'candidates' ? 'active' : '' }}" href="{{ route('retail.products.import', ['tab' => 'candidates']) }}">価格改定候補</a>
      <a class="tab {{ $filters['tab'] === 'history' ? 'active' : '' }}" href="{{ route('retail.products.import', ['tab' => 'history']) }}">価格履歴</a>
    </div>

    @if ($filters['tab'] === 'candidates')
    <section class="panel">
      <div class="price-toolbar">
        <div>
          <h3>蔵商品情報の確認</h3>
          <div class="meta">新規商品、基本情報、価格、販売停止、削除を確認します。価格は候補を確認して反映するまで変更しません。</div>
        </div>
        <div class="price-actions">
          <a class="btn" href="{{ route('retail.settings') }}">検出設定へ</a>
          <form method="post" action="{{ route('retail.products.import.price-changes.detect') }}">
            @csrf
            <button class="btn primary" type="submit">蔵商品情報を今すぐ確認</button>
          </form>
        </div>
      </div>
      <div class="meta">最終確認: {{ $priceSyncSetting->last_detected_at?->format('Y-m-d H:i') ?? '未実行' }}</div>
    </section>

    @if ($sourceAlerts->isNotEmpty())
    <section class="panel">
      <h3>蔵商品からのお知らせ</h3>
      <table class="table">
        <thead><tr><th>商品</th><th>状態</th><th>対応</th></tr></thead>
        <tbody>
          @foreach ($sourceAlerts as $alert)
            <tr>
              <td><strong>{{ $alert->product_code }}</strong><div class="meta">{{ $alert->name }}</div></td>
              <td><span class="badge warn">{{ ['changed' => '基本情報変更あり', 'inactive' => '蔵側販売停止', 'deleted' => '蔵側削除済み'][$alert->brewery_source_status] }}</span></td>
              <td class="meta">{{ $alert->brewery_source_status === 'changed' ? '取扱商品選択タブから再追加して情報を更新してください。' : '店舗在庫がある数量まで警告付きで販売できます。蔵への発注は行いません。' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </section>
    @endif

    <section class="panel">
      <h3>価格改定候補</h3>
      <table class="table">
        <thead><tr><th>商品</th><th>原価（税抜）</th><th>販売価格（税抜）</th><th>検出日時</th><th>反映</th></tr></thead>
        <tbody>
          @forelse ($priceCandidates as $candidate)
            <tr>
              <td><strong>{{ $candidate->product?->product_code }}</strong><div class="meta">{{ $candidate->product?->name }}</div></td>
              <td>現在 ¥{{ number_format((float) $candidate->current_cost_price) }}<div class="meta">蔵卸価格（税抜） ¥{{ number_format((float) $candidate->source_cost_price) }}</div></td>
              <td>現在 ¥{{ number_format((float) $candidate->current_selling_price) }}<div class="meta">蔵小売価格（税抜） ¥{{ number_format((float) $candidate->source_selling_price) }}</div></td>
              <td>{{ $candidate->detected_at?->format('Y-m-d H:i') }}</td>
              <td>
                <form class="inline-form" method="post" action="{{ route('retail.products.import.price-changes.apply', $candidate) }}">
                  @csrf
                  <select name="apply_mode">
                    <option value="cost_and_selling" selected>原価＋販売価格更新</option>
                    <option value="cost_only">原価のみ更新</option>
                  </select>
                  <button class="btn primary" type="submit">反映</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="meta">確認待ちの価格差分はありません。「蔵商品情報を今すぐ確認」を押すと、蔵側価格と小売側価格を比較して候補を作成します。</td></tr>
          @endforelse
        </tbody>
      </table>
    </section>
    @endif

    @if ($filters['tab'] === 'history')
    <section class="panel">
      <h3>価格履歴</h3>
      <table class="table">
        <thead><tr><th>商品</th><th>原価（税抜）</th><th>販売価格（税抜）</th><th>反映方法</th><th>反映日時</th></tr></thead>
        <tbody>
          @forelse ($priceHistories as $history)
            <tr>
              <td><strong>{{ $history->product?->product_code }}</strong><div class="meta">{{ $history->product?->name }}</div></td>
              <td>¥{{ number_format((float) $history->old_cost_price) }} → ¥{{ number_format((float) $history->new_cost_price) }}</td>
              <td>¥{{ number_format((float) $history->old_selling_price) }} → ¥{{ number_format((float) $history->new_selling_price) }}</td>
              <td><span class="badge warn">{{ $history->apply_mode === 'cost_only' ? '原価のみ' : '原価＋販売価格' }}</span></td>
              <td>{{ $history->applied_at?->format('Y-m-d H:i') }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="meta">価格履歴はまだありません。</td></tr>
          @endforelse
        </tbody>
      </table>
    </section>
    @endif

    @if ($filters['tab'] === 'import')
    <section class="panel">
      <form class="filters" method="get" action="{{ route('retail.products.import') }}">
        <input type="hidden" name="tab" value="import">
        <label>検索
          <input name="q" value="{{ $filters['q'] }}" placeholder="商品コード・商品名・銘柄・カテゴリ">
        </label>
        <label>分類
          <select name="category">
            <option value="">すべて</option>
            @foreach ($categoryOptions as $category)
              <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
            @endforeach
          </select>
        </label>
        <label>状態
          <select name="status">
            <option value="all" @selected($filters['status'] === 'all')>すべて</option>
            <option value="unimported" @selected($filters['status'] === 'unimported')>未追加</option>
            <option value="imported" @selected($filters['status'] === 'imported')>追加済</option>
          </select>
        </label>
        <button class="btn primary" type="submit">表示</button>
      </form>

      <form id="bulk-import-form" method="post" action="{{ route('retail.products.import.bulk') }}">
        @csrf
        @foreach ($products as $product)
          <input type="hidden" name="visible_product_ids[]" value="{{ $product->id }}">
        @endforeach
      </form>

      <div class="bulk-toolbar" style="margin-bottom:10px">
        <div>
          <h3>取扱商品選択</h3>
          <div class="meta">追加済みの商品も一覧に残り、何度でも再追加して蔵側の基本情報を更新できます。チェックON/OFFは次回表示時にも保持されます。</div>
        </div>
        <button class="btn primary" form="bulk-import-form" type="submit">チェックした商品を取扱商品に追加</button>
      </div>

      <table class="table">
        <thead>
          <tr><th class="check-cell">選択</th><th>蔵商品コード</th><th>商品名</th><th>分類</th><th>容量・単位</th><th>状態</th><th></th></tr>
        </thead>
        <tbody>
          @forelse ($products as $product)
            @php($imported = isset($importedProductIds[$product->id]))
            <tr>
              <td class="check-cell">
                <input class="import-selection" form="bulk-import-form" type="checkbox" name="selected_product_ids[]" value="{{ $product->id }}" @checked(isset($selectedProductIds[$product->id])) data-product-id="{{ $product->id }}">
              </td>
              <td class="code">{{ $product->product_code }}</td>
              <td><strong>{{ $product->display_name ?: $product->name }}</strong><div class="meta">{{ $product->brand_name }} {{ $product->series_name }} {{ $product->style_name }}</div></td>
              <td>{{ $product->category_name ?: $product->product_type }}</td>
              <td>{{ $product->capacity_value === null ? '-' : number_format((float) $product->capacity_value) }} {{ $product->capacityUnit?->symbol }}<div class="meta">販売単位 {{ $product->salesUnit?->symbol ?: $product->salesUnit?->name ?: '-' }}</div></td>
              <td><span class="badge {{ $imported ? 'done' : '' }}">{{ $imported ? '追加済' : '未追加' }}</span></td>
              <td>
                <form method="post" action="{{ route('retail.products.import.store', $product) }}">
                  @csrf
                  <button class="btn primary" type="submit">{{ $imported ? '再追加' : '追加' }}</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="meta">該当する蔵商品はありません。</td></tr>
          @endforelse
        </tbody>
      </table>

      @if ($products->hasPages())
        <div class="pagination">
          <span>{{ $products->currentPage() }} / {{ $products->lastPage() }} ページ</span>
          @if ($products->onFirstPage())
            <span class="btn page-btn disabled">前へ</span>
          @else
            <a class="btn page-btn" href="{{ $products->previousPageUrl() }}">前へ</a>
          @endif
          @if ($products->hasMorePages())
            <a class="btn page-btn" href="{{ $products->nextPageUrl() }}">次へ</a>
          @else
            <span class="btn page-btn disabled">次へ</span>
          @endif
        </div>
      @endif
    </section>
    @endif
  </main>
  <script>
    const token = @json(csrf_token());
    document.querySelectorAll('form.filters').forEach(form => {
      const button = form.querySelector('button[type="submit"]');
      const markDirty = () => button?.classList.add('search-attention');
      form.addEventListener('input', markDirty);
      form.addEventListener('change', markDirty);
      form.addEventListener('submit', () => button?.classList.remove('search-attention'));
    });
    document.querySelectorAll('.import-selection').forEach((checkbox) => {
      checkbox.addEventListener('change', async () => {
        const body = new FormData();
        body.append('_method', 'put');
        body.append('_token', token);
        body.append('visible_product_ids[]', checkbox.dataset.productId);
        if (checkbox.checked) body.append('selected_product_ids[]', checkbox.dataset.productId);
        try {
          await fetch(@json(route('retail.products.import.selections.update')), {
            method: 'POST',
            body,
            headers: {'X-Requested-With': 'XMLHttpRequest'},
          });
        } catch (error) {
          console.error('Failed to save import selection', error);
        }
      });
    });
  </script>
</body>
</html>

