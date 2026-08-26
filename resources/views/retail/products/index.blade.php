<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>外部商品 | {{ $retailSystemName ?? '小売販売システム' }}</title>
  <style>
    :root{--bg:{{ $retailTheme['bg'] ?? '#f3f6fa' }};--panel:{{ $retailTheme['card'] ?? '#fff' }};--line:{{ $retailTheme['line'] ?? '#d9e2ee' }};--text:#172033;--muted:#65758c;--blue:{{ $retailTheme['primary'] ?? '#0b6ff6' }};--blue-dark:{{ $retailTheme['primaryDark'] ?? '#075ecf' }};--sidebar:{{ $retailTheme['sidebar'] ?? '#10243b' }}}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--bg);color:var(--text);font-family:"Noto Sans JP",Meiryo,system-ui,sans-serif;padding-left:230px}.sidebar{position:fixed;inset:0 auto 0 0;width:230px;background:var(--sidebar);color:#eaf1fb;padding:18px 12px}.brand{padding:6px 10px 16px;border-bottom:1px solid rgba(255,255,255,.14)}.brand h1{margin:0;font-size:16px}.brand p{margin:8px 0 0;color:#a8b8cc;font-size:12px;line-height:1.6}.nav{display:grid;gap:4px;margin-top:16px}.nav a{display:flex;align-items:center;justify-content:space-between;min-height:38px;padding:0 12px;border-radius:6px;color:#dbe7f5;text-decoration:none;font-size:12px}.nav a.active{background:var(--blue);color:#fff;font-weight:800}.nav a span:last-child{color:#b8c6d9;font-size:11px}.content{padding:18px}.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px 16px;margin-bottom:12px}.topbar h2{margin:0;font-size:17px}.meta{color:var(--muted);font-size:12px;line-height:1.65}.layout{display:grid;grid-template-columns:minmax(0,1fr) 430px;gap:12px;align-items:start}.panel{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px}.panel h3{margin:0 0 8px;font-size:15px}.filters{display:grid;grid-template-columns:1fr 150px 150px auto;gap:8px;margin-bottom:12px;align-items:end}label{color:#4d6078;font-size:11px;font-weight:800;display:grid;gap:6px}input,select{width:100%;min-height:36px;border:1px solid #cbd7e6;border-radius:6px;background:#fff;color:var(--text);font:inherit;padding:8px}.btn{border:1px solid #c7d3e2;border-radius:6px;background:#fff;color:#1d3450;padding:8px 12px;font:inherit;font-size:12px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}.btn.primary{border-color:var(--blue);background:var(--blue);color:#fff;font-weight:800}@keyframes searchPulse{0%,100%{background:#fff7d6;border-color:#f0b429;box-shadow:0 0 0 0 rgba(240,180,41,.28)}50%{background:#ffe08a;border-color:#d89b00;box-shadow:0 0 0 5px rgba(240,180,41,.12)}}button.search-attention,.btn.primary.search-attention{animation:searchPulse 1.8s ease-in-out infinite;color:#172033!important;font-weight:800}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:9px 8px;border-bottom:1px solid #edf1f6;text-align:left;font-size:12px;vertical-align:top}.table th{background:#f8faff;color:#64748b;font-size:11px}.code{font-family:Consolas,monospace;color:var(--blue-dark);font-size:11px}.badge{display:inline-flex;align-items:center;min-height:24px;border-radius:999px;background:#e8f2ff;color:var(--blue-dark);font-size:11px;font-weight:800;padding:0 9px}.badge.external{background:#fff4df;color:#b9770e}.badge.off{background:#eef1f5;color:#64748b}.actions{display:flex;gap:6px;justify-content:flex-end}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.full{grid-column:1/-1}.checks{display:flex;gap:16px;align-items:center;margin-top:10px}.checks label{display:flex;align-items:center;gap:6px;color:#334155}.checks input{width:auto;min-height:auto}.message{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#e9f8ef;color:#137333;font-size:12px}.error{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#fff1f0;color:#b42318;font-size:12px}.pagination{display:flex;justify-content:flex-end;margin-top:12px}
    .filters{grid-template-columns:1fr 150px auto}.badge{background:#fff4df;color:#b9770e}
    @media(max-width:1080px){body{padding-left:0}.sidebar{position:static;width:auto}.layout,.filters{grid-template-columns:1fr}}@media(max-width:640px){.content{padding:10px}.topbar{align-items:flex-start;flex-direction:column}.form-grid{grid-template-columns:1fr}.full{grid-column:auto}.table{display:block;overflow:auto}}
  </style>
</head>
<body>
  <aside class="sidebar">
    <div class="brand"><h1>{{ $retailSystemName ?? '小売販売システム' }}</h1><p>税抜価格・発注条件・販売状態を管理します。</p></div>
    <nav class="nav">
      <a href="{{ route('retail.pos') }}"><span>販売入力</span><span>POS</span></a>
      <a href="{{ route('retail.sales.index') }}"><span>販売履歴</span><span>履歴</span></a>
      <a href="{{ route('retail.customers.index') }}"><span>小売顧客</span><span>顧客</span></a>
      <a class="active" href="{{ route('retail.products.index') }}"><span>外部商品</span><span>商品</span></a>
      <a href="{{ route('retail.products.import') }}"><span>取扱商品選択</span><span>選択</span></a>
      <a href="{{ route('retail.settings') }}"><span>設定</span><span>設定</span></a>
      <a href="{{ route('retail.system.manage') }}"><span>システム管理</span><span>管理</span></a>
    </nav>
  </aside>

  <main class="content">
    <header class="topbar">
      <div><h2>外部商品</h2><div class="meta">蔵以外から仕入れる商品の追加・編集を行います。蔵商品は小売側の「取扱商品選択」で追加・管理します。</div></div>
      <a class="btn" href="{{ route('retail.products.import') }}">取扱商品選択へ</a>
    </header>

    @if (session('status'))<div class="message">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <section class="layout">
      <section class="panel">
        <h3>外部商品一覧</h3>
        <form class="filters" method="get" action="{{ route('retail.products.index') }}">
          <label>検索<input name="q" value="{{ $filters['q'] }}" placeholder="商品コード・商品名"></label>
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
          <thead><tr><th>コード</th><th>商品名</th><th>仕入先</th><th>価格（税抜）</th><th>補充</th><th>状態</th><th></th></tr></thead>
          <tbody>
            @forelse ($products as $product)
              <tr>
                <td class="code">{{ $product->product_code }}</td>
                <td><strong>{{ $product->name }}</strong><div class="meta">外部商品</div></td>
                <td>{{ $product->supplier?->name ?: '-' }}</td>
                <td>売価: ¥{{ number_format((float) $product->selling_price) }}<div class="meta">原価: ¥{{ number_format((float) $product->cost_price) }} / 税率 {{ rtrim(rtrim(number_format((float) $product->tax_rate * 100, 2), '0'), '.') }}%</div></td>
                <td>在庫: {{ $product->inventoryStock?->quantity ?? '0.000' }} {{ $product->stock_unit }}<div class="meta">下限: {{ $product->reorder_point ?? '-' }} / 発注: {{ $product->reorder_quantity ?? '-' }}</div></td>
                <td><span class="badge @class(['off' => ! $product->is_active])">{{ $product->is_active ? '有効' : '停止' }}</span></td>
                <td class="actions"><a class="btn" href="{{ route('retail.products.index', ['edit' => $product->id, ...request()->only(['q', 'active'])]) }}">編集</a></td>
              </tr>
            @empty
              <tr><td colspan="7" class="meta">該当する外部商品はありません。</td></tr>
            @endforelse
          </tbody>
        </table>
        <div class="pagination">{{ $products->links() }}</div>
      </section>

      <aside class="panel">
        <h3>{{ $editingProduct ? '商品編集' : '外部商品追加' }}</h3>
        <form method="post" action="{{ $editingProduct ? route('retail.products.update', $editingProduct) : route('retail.products.store') }}">
          @csrf
          @if ($editingProduct) @method('put') @endif
          <div class="form-grid">
            <label>商品コード<input name="product_code" value="{{ old('product_code', $editingProduct?->product_code) }}" required></label>
            <label>仕入区分<input value="外部商品" readonly></label>
            <label class="full">商品名<input name="name" value="{{ old('name', $editingProduct?->name) }}" required></label>
            <label class="full">カナ<input name="name_kana" value="{{ old('name_kana', $editingProduct?->name_kana) }}"></label>
            <label class="full">既存仕入先
              <select name="retail_supplier_id">
                <option value="">新規仕入先を登録する</option>
                @foreach ($suppliers as $supplier)
                  <option value="{{ $supplier->id }}" @selected((string) old('retail_supplier_id', $editingProduct?->retail_supplier_id) === (string) $supplier->id)>{{ $supplier->supplier_code }} / {{ $supplier->name }}</option>
                @endforeach
              </select>
            </label>
            @unless ($editingProduct)
              <label>新規仕入先コード<input name="new_supplier_code" value="{{ old('new_supplier_code') }}" placeholder="例: EXT-001"></label>
              <label>新規仕入先名<input name="new_supplier_name" value="{{ old('new_supplier_name') }}" placeholder="例: 山田食品"></label>
            @endunless
            <label>仕入単価（税抜）<input name="cost_price" type="number" min="0" step="1" value="{{ old('cost_price', $editingProduct?->cost_price ?? 0) }}" required></label>
            <label>小売販売価格（税抜）<input name="selling_price" type="number" min="0" step="1" value="{{ old('selling_price', $editingProduct?->selling_price ?? 0) }}" required></label>
            <label>税率<input name="tax_rate" type="number" min="0" max="1" step="0.0001" value="{{ old('tax_rate', $editingProduct?->tax_rate ?? '0.1000') }}" required></label>
            <label>在庫単位<input name="stock_unit" value="{{ old('stock_unit', $editingProduct?->stock_unit ?? '個') }}" required></label>
            <label>在庫下限<input name="reorder_point" type="number" min="0" step="1" inputmode="numeric" value="{{ old('reorder_point', $editingProduct?->reorder_point) }}"></label>
            <label>発注数量<input name="reorder_quantity" type="number" min="0" step="1" inputmode="numeric" value="{{ old('reorder_quantity', $editingProduct?->reorder_quantity) }}"></label>
            <label class="full">現在在庫<input name="stock_quantity" type="number" min="0" step="1" inputmode="numeric" value="{{ old('stock_quantity', $editingProduct?->inventoryStock?->quantity) }}"></label>
          </div>
          <div class="checks"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingProduct?->is_active ?? true))> 販売対象</label></div>
          <div class="actions" style="margin-top:14px"><button class="btn primary" type="submit">{{ $editingProduct ? '更新' : '外部商品を追加' }}</button></div>
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

