<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>販売入力 | {{ $retailSystemName ?? '小売販売システム' }}</title>
  <style>
    :root{--bg:{{ $retailTheme['bg'] ?? '#f3f6fa' }};--panel:{{ $retailTheme['card'] ?? '#fff' }};--line:{{ $retailTheme['line'] ?? '#d9e2ee' }};--text:#172033;--muted:#65758c;--blue:{{ $retailTheme['primary'] ?? '#0b6ff6' }};--blue-dark:{{ $retailTheme['primaryDark'] ?? '#075ecf' }};--sidebar:{{ $retailTheme['sidebar'] ?? '#10243b' }};--danger:#b42318}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--bg);color:var(--text);font-family:"Noto Sans JP",Meiryo,system-ui,sans-serif;padding-left:230px}.sidebar{position:fixed;inset:0 auto 0 0;width:230px;background:var(--sidebar);color:#eaf1fb;padding:18px 12px}.brand{padding:6px 10px 16px;border-bottom:1px solid rgba(255,255,255,.14)}.brand h1{margin:0;font-size:16px}.brand p{margin:8px 0 0;color:#a8b8cc;font-size:12px;line-height:1.6}.nav{display:grid;gap:4px;margin-top:16px}.nav a{display:flex;align-items:center;justify-content:space-between;min-height:38px;padding:0 12px;border-radius:6px;color:#dbe7f5;text-decoration:none;font-size:12px}.nav a.active{background:var(--blue);color:#fff;font-weight:800}.nav a span:last-child{color:#b8c6d9;font-size:11px}
    .content{padding:18px}.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px 16px;margin-bottom:12px}.topbar h2{margin:0;font-size:17px}.meta{color:var(--muted);font-size:12px;line-height:1.65}.layout{display:grid;grid-template-columns:360px minmax(0,1fr);gap:12px;align-items:start}.panel{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px}.panel h3{margin:0 0 10px;font-size:15px}label{color:#4d6078;font-size:11px;font-weight:800;display:grid;gap:6px}input,select,textarea{width:100%;min-height:36px;border:1px solid #cbd7e6;border-radius:6px;background:#fff;color:var(--text);font:inherit;padding:8px}textarea{min-height:92px;resize:vertical}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.full{grid-column:1/-1}.btn{border:1px solid #c7d3e2;border-radius:6px;background:#fff;color:#1d3450;padding:8px 12px;font:inherit;font-size:12px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:6px}.btn.primary{border-color:var(--blue);background:var(--blue);color:#fff;font-weight:800}.btn.danger{border-color:#f1b7b1;color:var(--danger)}.btn:disabled{opacity:.45;cursor:not-allowed}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:9px 8px;border-bottom:1px solid #edf1f6;text-align:left;font-size:12px;vertical-align:middle}.table th{background:#f8faff;color:#64748b;font-size:11px}.code{font-family:Consolas,monospace;color:var(--blue-dark);font-size:11px}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}.summary{display:grid;gap:10px;margin-top:14px;padding-top:12px;border-top:1px solid var(--line)}.summary-row{display:flex;justify-content:space-between;align-items:center}.summary-row strong{font-size:24px;color:var(--blue-dark)}.error{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#fff1f0;color:var(--danger);font-size:12px}.notice{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#f0f7ff;color:#174a7c;font-size:12px;line-height:1.7}.company-form{display:flex;gap:8px;align-items:end;flex-wrap:wrap}.company-form label{min-width:220px}.selected-row{background:#eaf3ff}.empty{padding:20px;border:1px dashed #cbd7e6;border-radius:8px;color:var(--muted);font-size:13px;text-align:center}.negative{color:var(--danger);font-weight:800}
    .customer-picker{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:6px}.customer-picker input[readonly]{background:#f8faff}.modal{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;padding:18px;z-index:20}.modal.open{display:flex}.modal-card{width:min(980px,100%);max-height:86vh;overflow:hidden;background:#fff;border-radius:10px;border:1px solid var(--line);box-shadow:0 22px 80px rgba(15,23,42,.26);display:grid;grid-template-rows:auto auto minmax(0,1fr) auto}.modal-card.customer-card{width:min(760px,100%)}.modal-head{padding:14px 16px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;gap:12px}.modal-head h3{margin:0;font-size:16px}.modal-search{padding:12px 16px;display:grid;grid-template-columns:1fr 140px;gap:10px;border-bottom:1px solid var(--line)}.modal-search.single{grid-template-columns:1fr}.modal-body{overflow:auto}.modal-foot{padding:12px 16px;border-top:1px solid var(--line);display:grid;grid-template-columns:160px 1fr auto auto;gap:10px;align-items:end}.modal-foot.customer-foot{grid-template-columns:1fr auto auto}.product-row,.customer-row{cursor:pointer}.product-row:hover,.customer-row:hover{background:#f8fbff}.product-row.selected-row{background:#eaf3ff}.stock-zero{color:#b42318}
    @media(max-width:1080px){body{padding-left:0}.sidebar{position:static;width:auto}.layout{grid-template-columns:1fr}.modal-foot{grid-template-columns:1fr 1fr}}@media(max-width:640px){.content{padding:10px}.topbar{align-items:flex-start;flex-direction:column}.form-grid,.modal-search,.modal-foot,.customer-picker{grid-template-columns:1fr}.full{grid-column:auto}.table{display:block;overflow:auto}}
  </style>
</head>
<body>
  <span hidden>Retail POS Shell Retail Settings Link</span>
  <aside class="sidebar">
    <div class="brand"><h1>{{ $retailSystemName ?? '小売販売システム' }}</h1><p>{{ $selectedCompany['name'] }} の業務メニュー</p></div>
    <nav class="nav">
      <a class="active" href="{{ route('retail.pos') }}"><span>販売入力</span><span>POS</span></a>
      <a href="{{ route('retail.sales.index') }}"><span>販売履歴</span><span>履歴</span></a>
      <a href="{{ route('retail.customers.index') }}"><span>小売顧客</span><span>顧客</span></a>
      <a href="{{ route('retail.products.index') }}"><span>外部商品</span><span>商品</span></a>
      <a href="{{ route('retail.products.import') }}"><span>取扱商品選択</span><span>選択</span></a>
      <a href="{{ route('retail.settings') }}"><span>設定</span><span>設定</span></a>
      <a href="{{ route('retail.system.manage') }}"><span>システム管理</span><span>管理</span></a>
    </nav>
  </aside>

  <main class="content">
    <header class="topbar">
      <div><h2>{{ $selectedCompany['name'] }} 販売入力</h2><div class="meta">左で販売条件、右で販売商品を登録します。</div></div>
      <form class="company-form" method="post" action="{{ route('retail.companies.select') }}">
        @csrf
        <input type="hidden" name="redirect_to" value="{{ route('retail.pos', absolute: false) }}">
        <label>会社切替
          <select name="company">
            @foreach ($companies as $key => $company)
              <option value="{{ $key }}" @selected($selectedCompanyKey === $key)>{{ $company['name'] }}</option>
            @endforeach
          </select>
        </label>
        <button class="btn" type="submit">切替</button>
      </form>
    </header>

    @if ($errors->any())<div class="error">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="notice">
      @if (($companySetting->inventory_sales_policy ?? 'strict_stock') === 'allow_negative_order')
        この会社は「適時発注」方式です。販売画面に在庫数は表示せず、在庫不足でも販売できます。
      @else
        この会社は「在庫確認」方式です。在庫を見ながら販売し、在庫不足の商品は販売できません。
      @endif
      マイナス伝票は数量をマイナスで入力します。伝票確定・発行後、伝票詳細から納品書作成・印刷へ進めます。
    </div>
    @php($unavailableBreweryProducts = $products->whereIn('brewery_source_status', ['inactive', 'deleted']))
    @if ($unavailableBreweryProducts->isNotEmpty())
      <div class="error">
        <strong>蔵側で停止・削除された商品があります。</strong>
        @foreach ($unavailableBreweryProducts as $product)
          <div>{{ $product->product_code }} {{ $product->name }}: {{ $product->brewery_source_status === 'deleted' ? '蔵側削除済みです。店舗在庫がある数量まで販売できます。' : '蔵側販売停止です。店舗在庫がある数量まで販売できます。' }} 蔵への発注は行いません。</div>
        @endforeach
      </div>
    @endif

    <form method="post" action="{{ route('retail.sales.store') }}" id="sale-form">
      @csrf
      <section class="layout">
        <aside class="panel">
          <h3>販売条件</h3>
          <div class="form-grid">
            <label>販売日<input name="sale_date" type="date" value="{{ old('sale_date', now()->toDateString()) }}" required></label>
            <label>支払区分
              <select name="sale_type" required>
                <option value="cash" @selected(old('sale_type') === 'cash')>現金</option>
                <option value="credit" @selected(old('sale_type') === 'credit')>掛売</option>
                <option value="card" @selected(old('sale_type') === 'card')>カード</option>
                <option value="qr" @selected(old('sale_type') === 'qr')>QR</option>
              </select>
            </label>
            @php($selectedCustomer = $customers->firstWhere('id', (int) old('retail_customer_id')))
            <label class="full">小売顧客
              <div class="customer-picker">
                <input type="hidden" name="retail_customer_id" id="retail-customer-id" value="{{ old('retail_customer_id') }}">
                <input id="retail-customer-display" value="{{ $selectedCustomer ? $selectedCustomer->customer_code.' / '.$selectedCustomer->name : '店頭一般客' }}" readonly>
                <button class="btn primary" type="button" id="open-customer-modal">検索</button>
                <button class="btn" type="button" id="clear-customer">一般客</button>
              </div>
            </label>
            <label class="full">メモ<textarea name="note">{{ old('note') }}</textarea></label>
          </div>
          <div class="summary">
            <div class="summary-row"><span>小計（税抜）</span><b id="subtotal">¥0</b></div>
            <div class="summary-row"><span>消費税</span><b id="tax">¥0</b></div>
            <div class="summary-row"><span>合計（税込）</span><strong id="total">¥0</strong></div>
          </div>
          <button class="btn primary" type="submit" style="width:100%;margin-top:14px">伝票確定・発行</button>
        </aside>

        <section class="panel">
          <h3>販売商品</h3>
          <div class="actions">
            <button class="btn primary" type="button" id="add-item">追加</button>
            <button class="btn" type="button" id="edit-item" disabled>変更</button>
            <button class="btn danger" type="button" id="delete-item" disabled>削除</button>
          </div>
          <div id="empty-lines" class="empty">追加ボタンから販売商品を登録してください。</div>
          <table class="table" id="sale-lines" style="display:none">
            <thead><tr><th>コード</th><th>商品名</th><th>区分</th><th>数量</th><th>単価（税抜）</th><th>金額（税抜）</th></tr></thead>
            <tbody></tbody>
          </table>
        </section>
      </section>
    </form>
  </main>

  <div class="modal" id="product-modal" aria-hidden="true">
    <div class="modal-card">
      <div class="modal-head">
        <div><h3 id="modal-title">商品を追加</h3><div class="meta">商品を検索し、数量を入力して登録します。</div></div>
        <button class="btn" type="button" id="close-modal">閉じる</button>
      </div>
      <div class="modal-search">
        <label>商品検索<input id="product-search" placeholder="商品コード・商品名・かな"></label>
        <label>区分
          <select id="product-source">
            <option value="all">すべて</option>
            <option value="brewery">蔵商品</option>
            <option value="external">外部商品</option>
          </select>
        </label>
      </div>
      <div class="modal-body">
        <table class="table">
          <thead><tr><th>コード</th><th>商品名</th><th>区分</th><th>売価（税抜）</th><th>{{ ($companySetting->inventory_sales_policy ?? 'strict_stock') === 'allow_negative_order' ? '停止商品の在庫' : '在庫' }}</th></tr></thead>
          <tbody id="product-list"></tbody>
        </table>
      </div>
      <div class="modal-foot">
        <label>数量<input id="modal-quantity" type="number" step="1" inputmode="numeric" value="1"></label>
        <div class="meta" id="selected-product-help">商品を選択してください。</div>
        <button class="btn" type="button" id="cancel-modal">キャンセル</button>
        <button class="btn primary" type="button" id="apply-product">登録</button>
      </div>
    </div>
  </div>

  <div class="modal" id="customer-modal" aria-hidden="true">
    <div class="modal-card customer-card">
      <div class="modal-head">
        <div><h3>小売顧客を選択</h3><div class="meta">顧客コード・名称・かな・電話番号で検索できます。</div></div>
        <button class="btn" type="button" id="close-customer-modal">閉じる</button>
      </div>
      <div class="modal-search single">
        <label>顧客検索<input id="customer-search" placeholder="顧客コード・名称・かな・電話番号"></label>
      </div>
      <div class="modal-body">
        <table class="table">
          <thead><tr><th>顧客コード</th><th>顧客名</th><th>かな</th><th>電話番号</th></tr></thead>
          <tbody id="customer-list"></tbody>
        </table>
      </div>
      <div class="modal-foot customer-foot">
        <div class="meta">行を押すと顧客を指定します。</div>
        <button class="btn" type="button" id="customer-general">店頭一般客に戻す</button>
        <button class="btn" type="button" id="cancel-customer-modal">キャンセル</button>
      </div>
    </div>
  </div>

  <script>
    const products = @json($productOptions);
    const customers = @json($customerOptions);
    const allowNegativeStock = @json(($companySetting->inventory_sales_policy ?? 'strict_stock') === 'allow_negative_order');
    const yen = value => new Intl.NumberFormat('ja-JP', { style:'currency', currency:'JPY', maximumFractionDigits:0 }).format(value);
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
    const lineTable = document.getElementById('sale-lines');
    const lineBody = lineTable.querySelector('tbody');
    const emptyLines = document.getElementById('empty-lines');
    const modal = document.getElementById('product-modal');
    const productList = document.getElementById('product-list');
    const productSearch = document.getElementById('product-search');
    const productSource = document.getElementById('product-source');
    const modalQuantity = document.getElementById('modal-quantity');
    const selectedHelp = document.getElementById('selected-product-help');
    const customerModal = document.getElementById('customer-modal');
    const customerSearch = document.getElementById('customer-search');
    const customerList = document.getElementById('customer-list');
    const customerIdInput = document.getElementById('retail-customer-id');
    const customerDisplay = document.getElementById('retail-customer-display');
    let lines = [];
    let selectedLineIndex = null;
    let selectedProductId = null;
    let editingLineIndex = null;

    function selectCustomer(customer) {
      customerIdInput.value = customer ? customer.id : '';
      customerDisplay.value = customer ? `${customer.code} / ${customer.name}` : '店頭一般客';
      closeCustomerModal();
    }
    function renderCustomers() {
      const term = customerSearch.value;
      customerList.innerHTML = '';
      const matches = customers.filter(customer => {
        const text = `${customer.code} ${customer.name} ${customer.kana || ''} ${customer.phone || ''}`;
        return matchesSearchText(text, term);
      });
      matches.forEach(customer => {
        const row = document.createElement('tr');
        row.className = 'customer-row';
        row.innerHTML = `<td class="code">${escapeHtml(customer.code)}</td><td>${escapeHtml(customer.name)}</td><td>${escapeHtml(customer.kana || '-')}</td><td>${escapeHtml(customer.phone || '-')}</td>`;
        row.addEventListener('click', () => selectCustomer(customer));
        customerList.appendChild(row);
      });
      if (matches.length === 0) {
        customerList.innerHTML = '<tr><td colspan="4" class="meta">該当する小売顧客はありません。</td></tr>';
      }
    }
    function openCustomerModal() {
      customerSearch.value = '';
      customerModal.classList.add('open');
      customerModal.setAttribute('aria-hidden', 'false');
      renderCustomers();
      customerSearch.focus();
    }
    function closeCustomerModal() {
      customerModal.classList.remove('open');
      customerModal.setAttribute('aria-hidden', 'true');
    }
    const normalizeSearchText = value => String(value ?? '').normalize('NFKC').toLowerCase().replace(/[\s\u3000]+/g, '');
    const normalizeSearchTerms = value => String(value ?? '').normalize('NFKC').toLowerCase().trim().split(/[\s\u3000]+/).map(word => word.replace(/[\s\u3000]+/g, '')).filter(Boolean);
    const matchesSearchText = (haystack, query) => {
      const terms = normalizeSearchTerms(query);
      if (!terms.length) return true;
      const normalizedHaystack = normalizeSearchText(haystack);
      return terms.every(term => normalizedHaystack.includes(term));
    };

    function formatQty(value) {
      return Number(value).toLocaleString('ja-JP', { maximumFractionDigits: 3 });
    }
    function renumberInputs() {
      lineBody.querySelectorAll('tr').forEach((row, index) => {
        row.querySelector('[data-input-product]').name = `items[${index}][retail_product_id]`;
        row.querySelector('[data-input-qty]').name = `items[${index}][quantity]`;
      });
    }
    function renderLines() {
      lineBody.innerHTML = '';
      lines.forEach((line, index) => {
        const amount = Math.round(line.price * line.quantity);
        const row = document.createElement('tr');
        row.dataset.index = index;
        row.className = selectedLineIndex === index ? 'selected-row' : '';
        row.innerHTML = `
          <td class="code">${line.code}</td>
          <td>${line.name}${line.sourceWarning ? `<div class="meta" style="color:#b42318">${line.sourceWarning}</div>` : ''}<input data-input-product type="hidden" value="${line.id}"><input data-input-qty type="hidden" value="${line.quantity}"></td>
          <td>${line.sourceLabel}</td>
          <td class="${line.quantity < 0 ? 'negative' : ''}">${formatQty(line.quantity)}</td>
          <td>${yen(line.price)}</td>
          <td class="${amount < 0 ? 'negative' : ''}">${yen(amount)}</td>
        `;
        row.addEventListener('click', () => selectLine(index));
        lineBody.appendChild(row);
      });
      renumberInputs();
      lineTable.style.display = lines.length ? '' : 'none';
      emptyLines.style.display = lines.length ? 'none' : '';
      document.getElementById('edit-item').disabled = selectedLineIndex === null;
      document.getElementById('delete-item').disabled = selectedLineIndex === null;
      recalc();
    }
    function selectLine(index) {
      selectedLineIndex = index;
      renderLines();
    }
    function recalc() {
      let subtotal = 0;
      let tax = 0;
      lines.forEach(line => {
        const amount = Math.round(line.price * line.quantity);
        subtotal += amount;
        tax += Math.round(amount * line.taxRate);
      });
      document.getElementById('subtotal').textContent = yen(subtotal);
      document.getElementById('tax').textContent = yen(tax);
      document.getElementById('total').textContent = yen(subtotal + tax);
    }
    function renderProducts() {
      const term = productSearch.value;
      const source = productSource.value;
      productList.innerHTML = '';
      products.filter(product => {
        const text = `${product.code} ${product.name} ${product.kana || ''}`;
        return matchesSearchText(text, term) && (source === 'all' || product.source === source);
      }).forEach(product => {
        const row = document.createElement('tr');
        row.className = `product-row ${selectedProductId === product.id ? 'selected-row' : ''}`;
        const sourceUnavailable = ['inactive', 'deleted'].includes(product.brewerySourceStatus);
        const stockCell = `<td class="${product.stock <= 0 ? 'stock-zero' : ''}">${!allowNegativeStock || sourceUnavailable ? `${formatQty(product.stock)} ${product.unit}` : ''}</td>`;
        row.innerHTML = `
          <td class="code">${product.code}</td>
          <td>${product.name}${product.sourceWarning ? `<div class="meta" style="color:#b42318">${product.sourceWarning}</div>` : ''}</td>
          <td>${product.sourceLabel}</td>
          <td>${yen(product.price)}</td>
          ${stockCell}
        `;
        row.addEventListener('click', () => {
          selectedProductId = product.id;
          selectedHelp.textContent = product.sourceWarning || `${product.code} / ${product.name} を選択中`;
          renderProducts();
        });
        productList.appendChild(row);
      });
    }
    function openModal(mode) {
      editingLineIndex = mode === 'edit' ? selectedLineIndex : null;
      const current = editingLineIndex !== null ? lines[editingLineIndex] : null;
      selectedProductId = current ? current.id : null;
      modalQuantity.value = current ? current.quantity : 1;
      document.getElementById('modal-title').textContent = current ? '商品を変更' : '商品を追加';
      selectedHelp.textContent = current ? `${current.code} / ${current.name} を選択中` : '商品を選択してください。';
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
      productSearch.focus();
      renderProducts();
    }
    function closeModal() {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    }
    document.getElementById('add-item').addEventListener('click', () => openModal('add'));
    document.getElementById('open-customer-modal').addEventListener('click', openCustomerModal);
    document.getElementById('clear-customer').addEventListener('click', () => selectCustomer(null));
    document.getElementById('customer-general').addEventListener('click', () => selectCustomer(null));
    document.getElementById('close-customer-modal').addEventListener('click', closeCustomerModal);
    document.getElementById('cancel-customer-modal').addEventListener('click', closeCustomerModal);
    customerSearch.addEventListener('input', renderCustomers);
    document.getElementById('edit-item').addEventListener('click', () => openModal('edit'));
    document.getElementById('delete-item').addEventListener('click', () => {
      if (selectedLineIndex === null) return;
      lines.splice(selectedLineIndex, 1);
      selectedLineIndex = null;
      renderLines();
    });
    document.getElementById('close-modal').addEventListener('click', closeModal);
    document.getElementById('cancel-modal').addEventListener('click', closeModal);
    productSearch.addEventListener('input', renderProducts);
    productSource.addEventListener('change', renderProducts);
    document.getElementById('apply-product').addEventListener('click', () => {
      const product = products.find(item => item.id === selectedProductId);
      const quantity = Number(modalQuantity.value || 0);
      if (!product) {
        selectedHelp.textContent = '商品を選択してください。';
        return;
      }
      if (Math.abs(quantity) < 0.0001) {
        selectedHelp.textContent = '数量は0以外で入力してください。';
        return;
      }
      const sourceUnavailable = ['inactive', 'deleted'].includes(product.brewerySourceStatus);
      if ((!allowNegativeStock || sourceUnavailable) && quantity > product.stock) {
        selectedHelp.textContent = `在庫不足です。在庫: ${formatQty(product.stock)} ${product.unit}`;
        return;
      }
      const line = {...product, quantity};
      if (editingLineIndex !== null) {
        lines[editingLineIndex] = line;
        selectedLineIndex = editingLineIndex;
      } else {
        lines.push(line);
        selectedLineIndex = lines.length - 1;
      }
      closeModal();
      renderLines();
    });
    document.getElementById('sale-form').addEventListener('submit', event => {
      if (lines.length === 0) {
        event.preventDefault();
        alert('販売商品を追加してください。');
      }
    });
    renderLines();
  </script>
</body>
</html>
