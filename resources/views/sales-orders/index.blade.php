<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>受注 | 販売管理</title>
  <style>
    :root{font-family:Inter,"Noto Sans JP",system-ui,sans-serif;color:#172033;background:#f5f7fb;font-size:13px}
    *{box-sizing:border-box}[hidden]{display:none!important}body{margin:0}h1,h2,h3,p{margin:0}
    header{height:52px;display:flex;align-items:center;justify-content:space-between;padding:0 20px;border-bottom:1px solid #dce4ee;background:#fff}
    header h1{font-size:15px;font-weight:800;padding-left:10px;letter-spacing:.02em}main{padding:12px 18px 88px}.layout{display:grid;grid-template-columns:390px minmax(640px,1fr);gap:12px;align-items:start}.detail-column{position:sticky;top:64px;max-height:calc(100vh - 140px);overflow:auto;display:grid;gap:8px;align-items:start}
    .card{border:1px solid #dce4ee;border-radius:6px;background:#fff;overflow:hidden}.head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 14px;border-bottom:1px solid #e7edf4}.head h2{font-size:15px}.muted{color:#64748b;font-size:11px}
    .filters{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:10px 12px;border-bottom:1px solid #e7edf4}.filters .wide{grid-column:1/-1}
    label{display:grid;gap:4px;color:#475569;font-size:10px;font-weight:700}input,select,textarea{width:100%;min-height:30px;border:1px solid #ced8e5;border-radius:4px;padding:5px 8px;background:#fff;color:#172033;font:inherit}textarea{min-height:48px;resize:vertical}
    button{border:1px solid #cad6e6;border-radius:4px;background:#fff;color:#25344a;padding:6px 9px;font:inherit;font-size:11px;cursor:pointer}button:hover{border-color:#8ebcff}button:disabled{opacity:.45;cursor:not-allowed}.primary{border-color:#0b6ff6;background:#0b6ff6;color:#fff;font-weight:800}.secondary{color:#075ecf;border-color:#a9caff}.danger{color:#b42318}@keyframes searchPulse{0%,100%{background:#fff7d6;border-color:#f0b429;box-shadow:0 0 0 0 rgba(240,180,41,.28)}50%{background:#ffe08a;border-color:#d89b00;box-shadow:0 0 0 5px rgba(240,180,41,.12)}}button.search-attention,button.primary.search-attention{animation:searchPulse 1.8s ease-in-out infinite;color:#172033!important;font-weight:800}
    .filter-actions,.actions{display:flex;justify-content:flex-end;gap:7px;align-items:center}.order-actions{justify-content:flex-end}.order-actions .danger{margin-right:auto}.orders{width:100%;border-collapse:collapse;table-layout:fixed}.orders th,.orders td{padding:9px 9px;border-bottom:1px solid #edf1f6;text-align:left;font-size:11px;vertical-align:middle;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.orders th{background:#f8faff;color:#64748b;font-size:10px}.orders th:nth-child(1),.orders td:nth-child(1){width:132px}.orders th:nth-child(3),.orders td:nth-child(3){width:58px}.orders th:nth-child(4),.orders td:nth-child(4){width:60px}.orders tbody tr{cursor:pointer;transition:background .12s ease,box-shadow .12s ease}.orders tbody tr:hover{background:#e7f0ff}.orders tbody tr.selected{background:#d9e9ff;box-shadow:inset 3px 0 0 #0b6ff6}.orders td.order-no{overflow:visible;text-overflow:clip}.order-no{color:#075ecf;font-weight:800;font-family:"Roboto Mono","SFMono-Regular",Consolas,monospace;font-size:10.5px;letter-spacing:-.02em}.status{display:inline-block;max-width:100%;padding:2px 5px;border-radius:3px;color:#075ecf;background:#eaf3ff;font-size:10px;font-weight:800;overflow:hidden;text-overflow:ellipsis;vertical-align:middle}.status.warn{color:#9a6700;background:#fff4dd}.status.success{color:#137333;background:#e7f7ed}.status.danger{color:#b42318;background:#fee4e2}.status.shipment-cancelled{color:#6b2e00;background:#ffe8cc;border:1px solid #ffb36b}
    .pager{display:flex;justify-content:space-between;align-items:center;padding:9px 12px}.empty{padding:22px;color:#64748b;text-align:center}
    .form{display:grid;gap:10px;padding:12px 14px}.form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px}.form-grid.compact{grid-template-columns:minmax(180px,1.4fr) minmax(112px,.7fr) minmax(112px,.7fr) minmax(160px,1.2fr)}.form-grid .two{grid-column:span 2}.form-grid .full{grid-column:1/-1}.form-grid.compact .note-field{grid-column:span 2}
    .lines-wrap{overflow:auto;border-top:1px solid #e7edf4;border-bottom:1px solid #e7edf4}.lines{min-width:680px;width:100%;border-collapse:collapse;table-layout:fixed}.lines th,.lines td{padding:7px 8px;border-bottom:1px solid #edf1f6;text-align:left;vertical-align:top;font-size:11px}.lines th{background:#f8faff;color:#64748b;font-size:10px;white-space:nowrap}.lines th:nth-child(1),.lines td:nth-child(1){width:46%}.lines th:nth-child(2),.lines td:nth-child(2){width:180px}.lines th:nth-child(3),.lines td:nth-child(3){width:168px}.lines input,.lines select{min-height:34px;padding:5px 8px}.line-entry,.line-head-grid{display:grid;grid-template-columns:86px 86px minmax(96px,1fr);gap:6px;align-items:start}.line-head-grid span{display:block}.product-picker,.customer-picker{width:100%;min-height:36px;text-align:left;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.line-product{grid-column:1/-1}.line-field{display:grid}.quantity-with-unit{display:grid;grid-template-columns:minmax(0,1fr);align-items:center}.unit-inline{display:none}.line-readonly{display:block;min-height:34px;padding:7px 8px;border:1px solid #ced8e5;border-radius:4px;background:#fff;color:#172033;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.line-note{width:100%;min-width:0;min-height:76px;resize:vertical}.lines .number{text-align:left;white-space:nowrap}.price-application{margin-top:4px;color:#475569;font-size:10px;line-height:1.5}.price-change-notice{grid-column:1/-1;margin-top:4px;padding:4px 6px;border:1px solid #f6d365;border-radius:4px;background:#fff8e1;color:#7a4b00;font-size:10px;line-height:1.5;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.price-review{margin-top:6px;padding:6px;border:1px solid #f6d365;border-radius:4px;background:#fff8e1;color:#7a4b00;text-align:left}.price-review .actions{justify-content:flex-start;margin-top:5px;gap:4px}.price-review button{padding:4px 6px;font-size:10px}.line-actions{display:flex;justify-content:flex-start;align-items:flex-start;gap:8px;flex-wrap:nowrap;padding-top:26px}.line-actions button{min-width:72px;min-height:36px}
    .notice{min-height:17px;color:#64748b;font-size:11px}.error{color:#b42318}
    .orders tbody tr.correction{background:#fff1f1;box-shadow:inset 3px 0 0 #dc2626}.orders tbody tr.correction:hover,.orders tbody tr.correction.selected{background:#ffe4e6;box-shadow:inset 3px 0 0 #dc2626}.status.correction{color:#b42318;background:#fee4e2;border:1px solid #fda29b}.negative{color:#b42318;font-weight:800}
    .summary{position:fixed;right:0;bottom:0;left:0;display:flex;align-items:center;justify-content:flex-end;gap:28px;min-height:64px;padding:9px 20px;border-top:1px solid #dce4ee;background:rgba(255,255,255,.97);box-shadow:0 -5px 18px rgba(30,48,74,.06)}.summary:not(.is-visible){display:none}.summary .amount{font-size:20px;font-weight:800;color:#075ecf}.summary-label{color:#64748b;font-size:10px}
    .price-panel{position:fixed;z-index:2;right:18px;bottom:76px;width:310px;display:none;gap:9px;padding:14px;border:1px solid #d6e0ed;border-radius:6px;background:#fff;box-shadow:0 16px 40px rgba(20,42,70,.18)}.price-panel.open{display:grid}.price-panel .check-row{display:flex;align-items:center;gap:7px;font-size:12px;color:#1e293b}.price-panel .check-row input{width:auto}
    .modal-backdrop{position:fixed;inset:0;z-index:5;display:none;align-items:center;justify-content:center;background:rgba(15,23,42,.36);padding:18px}.modal-backdrop.open{display:flex}.product-modal{width:min(760px,100%);max-height:min(720px,92vh);display:grid;grid-template-rows:auto auto 1fr auto;background:#fff;border:1px solid #d6e0ed;border-radius:8px;box-shadow:0 24px 70px rgba(15,23,42,.25);overflow:hidden}.product-modal .modal-head{padding:12px 14px;border-bottom:1px solid #e7edf4;display:flex;align-items:center;justify-content:space-between}.product-modal .modal-search{padding:12px 14px;border-bottom:1px solid #e7edf4;display:grid;gap:6px}.product-results{overflow:auto}.product-result{width:100%;display:grid;grid-template-columns:1fr auto;gap:8px;padding:10px 14px;border:0;border-bottom:1px solid #edf1f6;border-radius:0;text-align:left;background:#fff}.product-result:hover,.product-result.active{background:#e7f0ff}.product-result strong{display:block;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.product-result span{color:#64748b;font-size:10px}.modal-foot{padding:10px 14px;border-top:1px solid #e7edf4;display:flex;justify-content:space-between;align-items:center;gap:8px}
    @media(max-width:1050px){.layout{grid-template-columns:1fr}.detail-column{position:static;max-height:none;overflow:visible}.form-grid,.form-grid.compact{grid-template-columns:repeat(2,minmax(0,1fr))}.form-grid.compact .note-field{grid-column:span 1}}
    @media(max-width:640px){main{padding:8px 8px 88px}header{padding:0 10px}.form-grid,.form-grid.compact{grid-template-columns:1fr}.form-grid .two,.form-grid.compact .note-field{grid-column:auto}.filters{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <header>
    <h1>受注</h1>
    <div class="actions"><span class="muted">{{ $user->name }}</span><form method="post" action="{{ route('logout') }}">@csrf<button type="submit">ログアウト</button></form></div>
  </header>
  <main>
    <div class="layout">
      <section class="card">
        <div class="head"><h2>受注一覧</h2></div>
        <form id="filter-form" class="filters">
          <label class="wide">検索<input id="filter-q" placeholder="受注番号・取引先・取引先注文番号"></label>
          <label>取引先<select id="filter-customer"><option value="">すべて</option></select></label>
          <label>ステータス<select id="filter-status"><option value="">すべて</option><option value="received">受注</option><option value="shipment_returned">出取消</option><option value="cancelled">取消</option></select></label>
          <label>受注日（開始）<input id="filter-from" type="date"></label><label>受注日（終了）<input id="filter-to" type="date"></label>
          <div class="filter-actions wide"><button id="reset-filter" type="button">リセット</button><button id="search-orders" class="primary" type="submit">検索</button></div>
        </form>
        <table class="orders"><thead><tr><th>受注番号</th><th>取引先</th><th>受注日</th><th>状態</th></tr></thead><tbody id="orders"></tbody></table>
        <div id="empty-orders" class="empty" hidden>該当する受注はありません。</div>
        <div class="pager"><button id="prev-page" type="button">前へ</button><span id="page-info" class="muted"></span><button id="next-page" type="button">次へ</button></div>
      </section>
      <div class="detail-column">
      <div class="card"><div class="actions" style="padding:8px 12px"><span id="list-message" class="muted" style="margin-right:auto">読み込み中</span><button id="refresh-orders" type="button">最新に更新</button>@if ($canCreate)<button id="new-order-list" class="secondary" type="button">新規作成</button>@endif</div></div>
      <section id="order-detail-card" class="card" hidden>
        <div class="head"><div><h2 id="form-title">新規受注</h2></div></div>
        <form id="order-form" class="form">
          <div class="form-grid compact">
            <label>取引先<input id="customer" type="hidden"><button id="customer-picker" class="customer-picker" type="button">取引先を検索・選択</button></label>
            <label>出荷日<input id="shipment-date" type="date"></label>
            <label>受注日<input id="order-date" type="date" required></label>
            <label>取引先注文番号<input id="customer-order-number" maxlength="255"></label>
            <label class="note-field">伝票備考<textarea id="note" maxlength="5000"></textarea></label>
            <label class="note-field">作業連絡<textarea id="work-note" maxlength="5000"></textarea></label>
          </div>
          <div class="actions"><span class="muted" style="margin-right:auto">明細</span><button id="add-line" type="button">商品を追加</button></div>
          <div class="lines-wrap"><table class="lines"><thead><tr><th><div class="line-head-grid"><span>商品／数量</span><span>単価</span><span>金額</span></div></th><th>備考</th><th></th></tr></thead><tbody id="line-list"></tbody></table></div>
          <p id="form-message" class="notice"></p>
          <div class="actions order-actions"><button id="cancel-order" class="danger" type="button" hidden>受注を取り消す</button><button id="save-changes" class="primary" type="button">変更を保存</button><button id="release-to-shipping" class="secondary" type="submit">出荷指示</button></div>
        </form>
      </section>
      </div>
    </div>
  </main>
  @if ($canChangePrice)
  <form id="price-panel" class="price-panel"><h3>単価を変更</h3><p id="price-target" class="muted"></p><label>単価<input id="manual-price" inputmode="decimal" required></label><label>変更理由<input id="price-reason" required maxlength="1000"></label><label class="check-row"><input id="save-as-customer-price" type="checkbox" checked> 今後もこの取引先個別価格として使う</label><p class="muted">チェックを外すと、この受注だけの一時的な単価変更になります。</p><p id="price-message" class="notice"></p><div class="actions"><button id="reset-price" type="button">既定価格に戻す</button><button id="close-price" type="button">閉じる</button><button class="primary" type="submit">保存</button></div></form>
  @endif
  <div id="product-modal" class="modal-backdrop" hidden>
    <section class="product-modal" role="dialog" aria-modal="true" aria-labelledby="product-modal-title">
      <div class="modal-head"><h3 id="product-modal-title">商品検索</h3><button id="close-product-modal" type="button">閉じる</button></div>
      <div class="modal-search">
        <label>商品コード・商品名・容量で検索<input id="product-search" autocomplete="off" placeholder="例：720 大吟醸 / 101001"></label>
        <p class="muted">候補をクリックすると明細に反映します。</p>
      </div>
      <div id="product-results" class="product-results"></div>
      <div class="modal-foot"><span id="product-result-count" class="muted"></span><button id="clear-product-search" type="button">検索をクリア</button></div>
    </section>
  </div>
  <div id="customer-modal" class="modal-backdrop" hidden>
    <section class="product-modal" role="dialog" aria-modal="true" aria-labelledby="customer-modal-title">
      <div class="modal-head"><h3 id="customer-modal-title">取引先検索</h3><button id="close-customer-modal" type="button">閉じる</button></div>
      <div class="modal-search">
        <label>取引先コード・取引先名で検索<input id="customer-search" autocomplete="off" placeholder="例：山田 / C001"></label>
        <p class="muted">候補をクリックすると受注の取引先に反映します。</p>
      </div>
      <div id="customer-results" class="product-results"></div>
      <div class="modal-foot"><span id="customer-result-count" class="muted"></span><button id="clear-customer-search" type="button">検索をクリア</button></div>
    </section>
  </div>
  <footer class="summary"><div><div class="summary-label">明細合計（税抜）</div><div id="subtotal" class="amount">¥0</div></div><div><div class="summary-label">明細数</div><strong id="line-count">0</strong></div><button id="save-changes-footer" class="primary" type="button">変更を保存</button><button id="release-to-shipping-footer" class="secondary" type="button">出荷指示</button></footer>
  <script>
    const customers = @json($customers);
    const products = @json($products);
    const refreshSettings = @json(\App\Models\AppSetting::values(['auto_refresh_enabled'=>'1','auto_refresh_interval_seconds'=>'30']));
    const autoRefreshEnabled = refreshSettings.auto_refresh_enabled === '1';
    const autoRefreshMs = Math.max(5, Number(refreshSettings.auto_refresh_interval_seconds || 30)) * 1000;
    const permissions = { create:@json($canCreate), update:@json($canUpdate), price:@json($canChangePrice) };
    const labels = {received:'受注', shipment_returned:'出取消', partially_instructed:'出荷指示', instructed:'出荷指示', cancelled:'取消', cancellation_correction:'取消訂正'};
    const statusClass = status => status === 'shipment_returned' ? 'warn' : status === 'cancelled' ? 'danger' : status === 'cancellation_correction' ? 'correction' : status === 'partially_instructed' ? 'success' : status === 'instructed' ? 'success' : '';
    const customerSelect = document.querySelector('#customer'), customerPicker = document.querySelector('#customer-picker'), filterCustomer = document.querySelector('#filter-customer'), lineList = document.querySelector('#line-list'), orderForm = document.querySelector('#order-form');
    const orderDetailCard = document.querySelector('#order-detail-card'), orderSummaryFooter = document.querySelector('.summary');
    const state = { page:1, pagination:null, selected:null, editing:false, dirty:false, saving:false };
    let currentOrders = [];
    let selectedPriceLine = null;
    let productTargetRow = null;
    const showOrderDetail = () => { orderDetailCard.hidden = false; orderSummaryFooter.classList.add('is-visible'); };
    const hideOrderDetail = () => { orderDetailCard.hidden = true; orderSummaryFooter.classList.remove('is-visible'); };
    const listMessage = () => document.querySelector('.detail-column #list-message') || document.querySelector('#list-message');
    const setMessage = (element, message, isError=false) => { element.textContent = message; element.classList.toggle('error', isError); };
    const markDirty = () => { if(!orderDetailCard.hidden) state.dirty = true; };
    const clearDirty = () => { state.dirty = false; };
    const cancelOrderButton=document.querySelector('#cancel-order'), canCancelOrder=@json($canCancel);
    const syncCancelOrder=order=>{ if(!cancelOrderButton)return; cancelOrderButton.hidden=!(canCancelOrder&&order?.status==='received'); state.selected=order?.status==='received'?order:state.selected; };
    const confirmDiscardIfDirty = () => !state.dirty || window.confirm('保存していない変更があります。保存せずに移動しますか？');
    const setSaving = saving => {
      state.saving = saving;
      ['#save-changes','#save-changes-footer','#release-to-shipping','#release-to-shipping-footer'].forEach(selector => {
        const button = document.querySelector(selector);
        if (button) button.disabled = saving;
      });
    };
    const api = async (url, options={}) => {
      const response = await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json',...(options.body?{'Content-Type':'application/json'}:{})},...options});
      const body = await response.json();
      if(!response.ok) throw new Error(body.error?.message || body.message || '処理に失敗しました。');
      return body.data;
    };
    const option = (value,label) => { const el=document.createElement('option'); el.value=value; el.textContent=label; return el; };
    const priceSourceLabels = {customer:'取引先別価格',transaction_category:'取引区分別価格',manual:'手動価格'};
    const priceSourceLabel = source => priceSourceLabels[source] || '基準価格';
    customers.forEach(item => { filterCustomer.appendChild(option(item.id,item.label)); });
    const customerFor = id => customers.find(item => String(item.id) === String(id));
    const setCustomer = (customerId, dirty=true) => {
      const customer = customerFor(customerId);
      customerSelect.value = customer?.id || '';
      customerPicker.textContent = customer?.label || '取引先を検索・選択';
      customerPicker.title = customer?.label || '';
      if (dirty) {
        markDirty();
        [...lineList.children].forEach(row => void notifyPriceReviewForSelection(row));
      }
    };
    const openCustomerModal = () => {
      if (customerPicker.disabled) return;
      document.querySelector('#customer-modal').hidden = false;
      document.querySelector('#customer-modal').classList.add('open');
      document.querySelector('#customer-search').value = '';
      renderCustomerResults();
      setTimeout(()=>document.querySelector('#customer-search').focus(),0);
    };
    const closeCustomerModal = () => {
      document.querySelector('#customer-modal').classList.remove('open');
      document.querySelector('#customer-modal').hidden = true;
    };
    const normalizeSearchText = value => String(value ?? '').normalize('NFKC').toLowerCase().replace(/[\s\u3000]+/g, '');
    const normalizeSearchTerms = value => String(value ?? '').normalize('NFKC').toLowerCase().trim().split(/[\s\u3000]+/).map(word => word.replace(/[\s\u3000]+/g, '')).filter(Boolean);
    const matchesSearchText = (haystack, query) => {
      const terms = normalizeSearchTerms(query);
      if (!terms.length) return true;
      const normalizedHaystack = normalizeSearchText(haystack);
      return terms.every(term => normalizedHaystack.includes(term));
    };
    const renderCustomerResults = () => {
      const term = document.querySelector('#customer-search').value;
      const results = customers
        .filter(customer => matchesSearchText(customer.label, term))
        .slice(0, 80);
      document.querySelector('#customer-result-count').textContent = `${results.length}件表示`;
      document.querySelector('#customer-results').replaceChildren(...results.map(customer => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'product-result';
        button.innerHTML = '<div><strong></strong><span></span></div>';
        button.querySelector('strong').textContent = customer.label;
        button.querySelector('span').textContent = customer.label;
        button.onclick = () => { setCustomer(customer.id); closeCustomerModal(); };
        return button;
      }));
    };
    const yen = value => `¥${Number(value||0).toLocaleString('ja-JP',{maximumFractionDigits:2})}`;
      const priceInputValue = value => {
      if (value === null || value === undefined || value === '') return '';
      const text = String(value);
      return text.includes('.') ? text.replace(/\.?0+$/, '') : text;
    };
    const shortDate = value => value ? value.slice(5,10) : '';
    const longDate = value => { if(!value) return ''; const [year,month,day]=value.split('-').map(Number); return `${year}年${month}月${day}日`; };
    const productFor = id => products.find(item => String(item.id) === String(id));
    const productLabel = id => productFor(id)?.label || '商品を検索・選択';
    const productSearchText = product => normalizeSearchText([product.label, product.unit_name, product.unit_code].filter(Boolean).join(' '));
    const isRetailPurchaseOrderCancellation = order => order?.source_type === 'retail_purchase_order_cancellation';
    const correctionNote = order => [order.correction_notice, order.note].filter(Boolean).join('\n');
    const notifiedPriceReviewSelections = new Set();
    const priceReviewWarningText = task => {
      const parts = [
        task.message || 'この取引先別価格は、基準価格の改定後まだ見直しされていません。',
        `旧基準価格：${yen(task.old_reference_price)}`,
        `新基準価格：${yen(task.new_reference_price)}`,
        `現在の取引先別価格：${yen(task.current_individual_price)}`,
      ];
      return parts.join('\n');
    };
    const notifyPriceReviewForSelection = async row => {
      const customerId = customerSelect.value;
      const productId = row.dataset.productId;
      if (!customerId || !productId) return;
      const key = `${customerId}:${productId}`;
      if (notifiedPriceReviewSelections.has(key)) return;
      notifiedPriceReviewSelections.add(key);
      try {
        const data = await api('/api/v1/price-review-tasks/notify-selection', {
          method:'POST',
          body:JSON.stringify({ customer_id:Number(customerId), product_id:Number(productId) }),
        });
        const task = data.price_review_task;
        if (!task) return;
        row.dataset.reviewTaskId = task.id;
        window.alert(priceReviewWarningText(task));
      } catch(error) {
        notifiedPriceReviewSelections.delete(key);
        setMessage(document.querySelector('#form-message'), error.message, true);
      }
    };
    const setLineProduct = (row, productId, dirty=true) => {
      const current = productFor(productId);
      row.dataset.productId = current?.id || '';
      row.querySelector('[data-product-label]').textContent = current?.label || '商品を検索・選択';
      row.querySelector('[data-product-label]').title = current?.label || '';
      row.dataset.price='';
      row.dataset.source='';
      row.dataset.reason='';
      row.dataset.priceEffectiveFrom='';
      row.dataset.priceChangeNotice='';
      row._sync?.();
      if(dirty) {
        markDirty();
        void notifyPriceReviewForSelection(row);
      }
    };
    const openProductModal = row => {
      productTargetRow = row;
      document.querySelector('#product-modal').hidden = false;
      document.querySelector('#product-modal').classList.add('open');
      document.querySelector('#product-search').value = '';
      renderProductResults();
      setTimeout(()=>document.querySelector('#product-search').focus(),0);
    };
    const closeProductModal = () => {
      document.querySelector('#product-modal').classList.remove('open');
      document.querySelector('#product-modal').hidden = true;
      productTargetRow = null;
    };
    const renderProductResults = () => {
      const term = document.querySelector('#product-search').value;
      const results = products
        .filter(product => matchesSearchText(productSearchText(product), term))
        .slice(0, 80);
      document.querySelector('#product-result-count').textContent = `${results.length}件表示`;
      document.querySelector('#product-results').replaceChildren(...results.map(product => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'product-result';
        button.innerHTML = `<div><strong></strong><span></span></div><span>${product.unit_name || product.unit_code || ''}</span>`;
        button.querySelector('strong').textContent = product.label;
        button.querySelector('span').textContent = product.label;
        button.onclick = () => { if(productTargetRow)setLineProduct(productTargetRow, product.id); closeProductModal(); };
        return button;
      }));
    };
    const updateSummary = () => {
      const rows = [...lineList.children];
      const total = rows.reduce((sum,row) => sum + (Number(row.dataset.price||0) * Number(row.querySelector('[data-quantity]').value||0)), 0);
      document.querySelector('#subtotal').textContent = yen(total);
      document.querySelector('#subtotal').classList.toggle('negative', total < 0);
      document.querySelector('#line-count').textContent = String(rows.length);
    };
    const addLine = (line={}) => {
      const row = document.createElement('tr');
      row.dataset.lineId = line.id || '';
      row.dataset.price = line.unit_price || '';
      row.dataset.source = line.price_source || '';
      row.dataset.reason = line.price_reason || '';
      row.dataset.priceEffectiveFrom = line.price_effective_from || '';
      row.dataset.priceChangeNotice = line.price_change_notice || '';
      row.dataset.reviewTaskId = line.price_review_task?.id || '';
      const instructedQuantity = Math.max(0, Math.round(Number(line.quantity||0) - Number(line.remaining_quantity ?? line.quantity ?? 0)));
      const hasInstruction = instructedQuantity > 0;
      const isNegativeCorrection = Number(line.quantity || 0) < 0;
      row.dataset.productId = line.product_id || '';
      const detailCell = document.createElement('td');
      const detailLine = document.createElement('div');
      detailLine.className = 'line-entry';
      const product = document.createElement('button');
      product.type = 'button';
      product.className = 'product-picker line-product';
      product.dataset.productLabel = '';
      product.textContent = productLabel(row.dataset.productId);
      product.title = product.textContent;
      product.disabled = hasInstruction;
      product.addEventListener('click',()=>openProductModal(row));
      const quantityField = document.createElement('label');
      quantityField.className = 'line-field';
      const quantityWrap = document.createElement('div');
      quantityWrap.className = 'quantity-with-unit';
      const quantity = document.createElement('input');
      quantity.type = 'number'; quantity.min = isNegativeCorrection ? '' : String(Math.max(1,instructedQuantity)); quantity.step = '1'; quantity.required = true; quantity.value = String(isNegativeCorrection ? Math.round(Number(line.quantity)) : Math.max(1, Math.round(Number(line.quantity||'1')))); quantity.dataset.quantity = '';
      const unitLabel = document.createElement('span');
      unitLabel.className = 'unit-inline';
      const unit = document.createElement('input'); unit.readOnly = true; unit.hidden = true;
      quantityWrap.append(quantity, unit);
      quantityField.appendChild(quantityWrap);
      const priceField = document.createElement('label');
      priceField.className = 'line-field';
      const price = document.createElement('span');
      price.className = 'line-readonly';
      const priceApplication = document.createElement('span');
      priceApplication.className = 'price-application';
      const priceChangeNotice = document.createElement('span');
      priceChangeNotice.className = 'price-change-notice';
      priceField.append(price, priceApplication);
      const amountField = document.createElement('label');
      amountField.className = 'line-field';
      const amount = document.createElement('span');
      amount.className = 'line-readonly';
      amountField.appendChild(amount);
      detailLine.append(product, quantityField, priceField, amountField, priceChangeNotice);
      detailCell.appendChild(detailLine);
      const noteCell = document.createElement('td');
      const note = document.createElement('textarea');
      note.className = 'line-note';
      note.maxLength = 1000;
      note.placeholder = '備考';
      note.value = line.note || '';
      note.dataset.lineNote = '';
      note.addEventListener('input', markDirty);
      noteCell.appendChild(note);
      if (line.price_review_task && permissions.price && state.editing && line.id) {
        const review = document.createElement('div');
        review.className = 'price-review';
        const reviewText = document.createElement('div');
        reviewText.textContent = line.price_review_task.message || '価格改定後、取引先個別価格の見直しが必要です。';
        const reviewActions = document.createElement('div');
        reviewActions.className = 'actions';
        const keep = document.createElement('button');
        keep.type = 'button';
        keep.textContent = '個別価格のまま';
        keep.addEventListener('click', async () => {
          try {
            await api(`/api/v1/price-review-tasks/${line.price_review_task.id}/keep`, { method:'POST', body:JSON.stringify({ reason:'受注画面で個別価格を継続' }) });
            await selectOrder(state.selected.id);
          } catch(error) { setMessage(document.querySelector('#form-message'), error.message, true); }
        });
        const defaultPrice = document.createElement('button');
        defaultPrice.type = 'button';
        defaultPrice.textContent = '既定価格に戻す';
        defaultPrice.addEventListener('click', async () => {
          try {
            await api(`/api/v1/sales-orders/${state.selected.id}/lines/${line.id}/reprice`, { method:'POST', body:JSON.stringify({ reason:'価格見直し警告から既定価格へ戻す' }) });
            await api(`/api/v1/price-review-tasks/${line.price_review_task.id}/updated`, { method:'POST', body:JSON.stringify({ reason:'既定価格へ戻した' }) });
            await selectOrder(state.selected.id);
          } catch(error) { setMessage(document.querySelector('#form-message'), error.message, true); }
        });
        const newPrice = document.createElement('button');
        newPrice.type = 'button';
        newPrice.textContent = '新個別価格';
        newPrice.addEventListener('click', () => openPriceEditor(row));
        reviewActions.append(keep, defaultPrice, newPrice);
        review.append(reviewText, reviewActions);
        detailCell.appendChild(review);
      }
      const actionCell = document.createElement('td'); actionCell.className = 'line-actions';
      const remove = document.createElement('button'); remove.type='button'; remove.className='danger'; remove.textContent='削除'; remove.addEventListener('click',()=>{row.remove();updateSummary();markDirty();}); actionCell.appendChild(remove);
      if (permissions.price && state.editing && line.id) {
        const priceButton = document.createElement('button'); priceButton.type='button'; priceButton.textContent='単価'; priceButton.addEventListener('click',()=>openPriceEditor(row)); actionCell.prepend(priceButton);
      }
      const sync = () => {
        const current = productFor(row.dataset.productId);
        unit.value = current?.unit_name || current?.unit_code || '';
        unit.dataset.unitId = current?.unit_id || '';
        unitLabel.textContent = current?.unit_name || current?.unit_code || '';
        const unitPrice = Number(row.dataset.price||0);
        quantity.classList.toggle('negative', Number(quantity.value || 0) < 0);
        price.textContent = unitPrice ? yen(unitPrice) : '登録時に適用';
        const effectiveFrom = row.dataset.priceEffectiveFrom;
        priceApplication.textContent = unitPrice
          ? [priceSourceLabel(row.dataset.source), effectiveFrom ? `適用開始 ${effectiveFrom.replaceAll('-', '/')}` : null].filter(Boolean).join(' / ')
          : '';
        priceChangeNotice.textContent = row.dataset.priceChangeNotice;
        priceApplication.textContent = '';
        priceApplication.hidden = true;
        priceChangeNotice.hidden = !row.dataset.priceChangeNotice;
        amount.textContent = unitPrice ? yen(unitPrice * Number(quantity.value||0)) : '—';
        amount.classList.toggle('negative', unitPrice * Number(quantity.value||0) < 0);
        updateSummary();
      };
      row._sync = sync;
      quantity.addEventListener('input',()=>{sync();markDirty();});
      sync();
      row.append(detailCell,noteCell,actionCell);
      lineList.appendChild(row);
    };
    const setFormMode = (order=null) => {
      showOrderDetail();
      state.selected = order; state.editing = Boolean(order);
      orderForm.reset(); lineList.replaceChildren(); document.querySelector('#form-message').textContent = '';
      document.querySelector('#form-title').textContent = order ? order.order_number : '新規受注';
      const releaseButton = document.querySelector('#release-to-shipping');
      releaseButton.hidden = false;
      releaseButton.disabled = false;
      releaseButton.textContent = '出荷指示';
      document.querySelector('#release-to-shipping-footer').hidden = false;
      document.querySelector('#release-to-shipping-footer').disabled = false;
      document.querySelector('#save-changes').hidden = false;
      document.querySelector('#save-changes-footer').hidden = false;
      customerPicker.disabled = Boolean(order);
      document.querySelector('#order-date').disabled = Boolean(order);
      if(order){
        setCustomer(order.customer_id, false);
        document.querySelector('#order-date').value = order.order_date;
        document.querySelector('#shipment-date').value = order.requested_shipment_date || order.requested_delivery_date || '';
        document.querySelector('#customer-order-number').value = order.customer_order_number || '';
        document.querySelector('#note').value = correctionNote(order);
        document.querySelector('#work-note').value = order.work_note || '';
        order.lines.forEach(addLine);
        if(isRetailPurchaseOrderCancellation(order)){
          releaseButton.hidden = true;
          document.querySelector('#release-to-shipping-footer').hidden = true;
          document.querySelector('#save-changes').hidden = true;
          document.querySelector('#save-changes-footer').hidden = true;
          document.querySelector('#form-message').textContent = '小売発注取消の全量マイナス訂正受注です。蔵側では出荷指示・変更せず、取消調整用の伝票として確認してください。';
        }
      } else {
        setCustomer('', false);
        const today = new Date().toISOString().slice(0,10);
        document.querySelector('#order-date').value = today;
        document.querySelector('#shipment-date').value = today;
        document.querySelector('#work-note').value = '';
        addLine();
      }
      updateSummary();
      syncCancelOrder(order);
      clearDirty();
    };
    const readFilters = () => {
      const q = new URLSearchParams({per_page:'10',page:String(state.page)});
      const values = {q:document.querySelector('#filter-q').value,customer_id:filterCustomer.value,status:document.querySelector('#filter-status').value,order_date_from:document.querySelector('#filter-from').value,order_date_to:document.querySelector('#filter-to').value};
      Object.entries(values).forEach(([key,value]) => { if(value) q.set(key,value); });
      return q;
    };
    const renderOrders = orders => {
      const tbody = document.querySelector('#orders');
      tbody.replaceChildren(...orders.map(order => {
        const row = document.createElement('tr');
        row.dataset.orderId = order.id;
        const displayStatus = order.display_status || order.status;
        row.classList.toggle('correction', displayStatus === 'cancellation_correction');
        if(state.selected?.id === order.id) row.classList.add('selected');
        row.addEventListener('click',()=>selectOrder(order.id));
        [order.order_number,order.customer_name||'',shortDate(order.order_date),labels[displayStatus]||displayStatus].forEach((value,index)=>{
          const cell=document.createElement('td'); cell.title=index===2?longDate(order.order_date):value;
          if(index===0) cell.className='order-no';
          if(index===3){ const badge=document.createElement('span'); badge.className='status '+statusClass(displayStatus); badge.textContent=value; badge.title=value; cell.appendChild(badge); }
          else cell.textContent=value;
          row.appendChild(cell);
        });
        return row;
      }));
      document.querySelector('#empty-orders').hidden = orders.length > 0;
    };
    const loadOrders = async () => {
      setOrderSearchDirty(false);
      const message = listMessage();
      setMessage(message,'読み込み中');
      try{
        const data = await api(`/api/v1/sales-orders?${readFilters()}`);
        state.pagination = data.pagination;
        currentOrders = data.sales_orders;
        renderOrders(currentOrders);
        message.textContent = `${data.pagination.total}件`;
        document.querySelector('#page-info').textContent = `${data.pagination.current_page} / ${data.pagination.last_page}`;
        document.querySelector('#prev-page').disabled = data.pagination.current_page <= 1;
        document.querySelector('#next-page').disabled = data.pagination.current_page >= data.pagination.last_page;
      }catch(error){ setMessage(message,error.message,true); }
    };
    const selectOrder = async id => {
      if(!confirmDiscardIfDirty()) return;
      try{
        const data = await api(`/api/v1/sales-orders/${id}`);
        if(!permissions.update || data.sales_order.status !== 'received'){
          state.selected = data.sales_order;
          state.editing = false;
          renderOrders(currentOrders);
          hideOrderDetail();
          setMessage(listMessage(),'この受注は編集権限がないか、出荷処理済みのため閲覧のみです。',true);
          return;
        }
        setFormMode(data.sales_order);
        await loadOrders();
      }catch(error){ hideOrderDetail(); setMessage(listMessage(),error.message,true); }
    };
    const validateCurrentLines = () => {
      if (!state.editing || !state.selected) return true;
      const validLineIds = new Set((state.selected.lines || []).map(line => String(line.id)));
      return [...lineList.children].every(row => !row.dataset.lineId || validLineIds.has(String(row.dataset.lineId)));
    };
    const reloadSelectedOrder = async () => {
      if (!state.selected?.id) return;
      const data = await api(`/api/v1/sales-orders/${state.selected.id}`);
      setFormMode(data.sales_order);
      await loadOrders();
    };
    const payload = (autoRelease=false, deferAfterSave=true) => {
      if (!validateCurrentLines()) {
        throw new Error('明細情報が更新されました。画面を再読み込みしました。もう一度保存してください。');
      }
      return {
        customer_id:Number(customerSelect.value),
        order_date:document.querySelector('#order-date').value,
        requested_shipment_date:document.querySelector('#shipment-date').value||null,
        requested_delivery_date:null,
        customer_order_number:document.querySelector('#customer-order-number').value||null,
        note:document.querySelector('#note').value||null,
        work_note:document.querySelector('#work-note').value||null,
        auto_release_to_shipping:autoRelease?1:0,
        awaiting_shipment_instruction:autoRelease?0:(deferAfterSave?1:0),
        lines:[...lineList.children].map(row=>({...(row.dataset.lineId?{id:Number(row.dataset.lineId)}:{}),product_id:Number(row.dataset.productId),quantity:row.querySelector('[data-quantity]').value,unit_id:Number(row.querySelector('input[readonly]').dataset.unitId),note:row.querySelector('[data-line-note]').value||null}))
      };
    };
    const saveOrder = async (autoRelease=false, deferAfterSave=true) => {
      if(state.saving) return;
      if(!orderForm.reportValidity()) return;
      const message = document.querySelector('#form-message');
      if (!customerSelect.value) {
        setMessage(message,'取引先を選択してください。',true);
        return;
      }
      if ([...lineList.children].some(row => !row.dataset.productId)) {
        setMessage(message,'商品を選択してください。',true);
        return;
      }
      if ([...lineList.children].some(row => !row.querySelector('input[readonly]').dataset.unitId)) {
        setMessage(message,'商品の単位が取得できません。商品を選び直してください。',true);
        return;
      }
      setMessage(message,'保存中');
      setSaving(true);
      try{
        const savingOrderId = state.selected?.id || null;
        let data;
        if (autoRelease && state.editing) {
          await api(`/api/v1/sales-orders/${state.selected.id}`, {
            method: 'PUT',
            body: JSON.stringify(payload(false, false)),
          });
          data = await api(`/api/v1/sales-orders/${state.selected.id}/release-to-shipping`, {
            method: 'POST',
            body: JSON.stringify({}),
          });
        } else {
          data = await api(state.editing?`/api/v1/sales-orders/${state.selected.id}`:'/api/v1/sales-orders',{method:state.editing?'PUT':'POST',body:JSON.stringify(payload(autoRelease,deferAfterSave))});
        }
        if (savingOrderId && data.sales_order?.id !== savingOrderId) {
          throw new Error('表示中の受注情報が更新されました。もう一度保存してください。');
        }
        if (autoRelease) {
          clearDirty();
          state.selected = null;
          state.editing = false;
          hideOrderDetail();
          setMessage(listMessage(),'出荷指示を作成しました。出荷作業一覧に追加されています。');
        } else {
          setMessage(message, state.editing ? '変更を保存しました。' : '受注を登録しました。');
          setFormMode(data.sales_order);
        }
        await loadOrders();
      }catch(error){
        if (String(error.message || '').includes('does not belong to sales order')) {
          setMessage(message,'明細情報が更新されました。画面を再読み込みしました。もう一度保存してください。',true);
          await reloadSelectedOrder();
        } else if (String(error.message || '').includes('明細情報が更新されました')) {
          setMessage(message,error.message,true);
          await reloadSelectedOrder();
        } else {
          setMessage(message,error.message,true);
        }
      } finally {
        setSaving(false);
      }
    };
    let filterTimer = null;
    const applyFilters = (delay = 0) => {
      clearTimeout(filterTimer);
      filterTimer = setTimeout(() => {
        if(!confirmDiscardIfDirty()) return;
        state.page = 1;
        loadOrders();
      }, delay);
    };
    const setOrderSearchDirty=dirty=>{state.searchDirty=dirty;document.querySelector('#search-orders')?.classList.toggle('search-attention',dirty)};
    document.querySelector('#filter-form').addEventListener('submit',event=>{event.preventDefault();applyFilters();});
    document.querySelector('#filter-q').addEventListener('input',()=>setOrderSearchDirty(true));
    document.querySelector('#filter-customer').addEventListener('change',()=>setOrderSearchDirty(true));
    document.querySelector('#filter-status').addEventListener('change',()=>setOrderSearchDirty(true));
    document.querySelector('#filter-from').addEventListener('change',()=>setOrderSearchDirty(true));
    document.querySelector('#filter-to').addEventListener('change',()=>setOrderSearchDirty(true));
    document.querySelector('#reset-filter').addEventListener('click',()=>{if(!confirmDiscardIfDirty())return;document.querySelector('#filter-form').reset();state.page=1;loadOrders();});
    document.querySelector('#refresh-orders')?.addEventListener('click',()=>{ if(confirmDiscardIfDirty()) loadOrders(); });
    document.querySelector('#prev-page').addEventListener('click',()=>{if(!confirmDiscardIfDirty())return;state.page--;loadOrders();});
    document.querySelector('#next-page').addEventListener('click',()=>{if(!confirmDiscardIfDirty())return;state.page++;loadOrders();});
    document.querySelector('#add-line').addEventListener('click',()=>{addLine();markDirty();});
    document.querySelector('#new-order-list')?.addEventListener('click',()=>{if(confirmDiscardIfDirty())setFormMode();});
    orderForm.addEventListener('submit',event=>{event.preventDefault();saveOrder(event.submitter?.id==='release-to-shipping', false);});
    document.querySelector('#save-changes')?.addEventListener('click',()=>saveOrder(false,false));
    document.querySelector('#save-changes-footer')?.addEventListener('click',()=>saveOrder(false,false));
    document.querySelector('#release-to-shipping-footer')?.addEventListener('click',()=>saveOrder(true,false));
    document.querySelector('#product-search')?.addEventListener('input',renderProductResults);
    document.querySelector('#clear-product-search')?.addEventListener('click',()=>{document.querySelector('#product-search').value='';renderProductResults();document.querySelector('#product-search').focus();});
    document.querySelector('#close-product-modal')?.addEventListener('click',closeProductModal);
    document.querySelector('#product-modal')?.addEventListener('click',event=>{if(event.target.id==='product-modal')closeProductModal();});
    customerPicker.addEventListener('click',openCustomerModal);
    document.querySelector('#customer-search')?.addEventListener('input',renderCustomerResults);
    document.querySelector('#clear-customer-search')?.addEventListener('click',()=>{document.querySelector('#customer-search').value='';renderCustomerResults();document.querySelector('#customer-search').focus();});
    document.querySelector('#close-customer-modal')?.addEventListener('click',closeCustomerModal);
    document.querySelector('#customer-modal')?.addEventListener('click',event=>{if(event.target.id==='customer-modal')closeCustomerModal();});
    window.addEventListener('keydown',event=>{if(event.key==='Escape'){if(productTargetRow)closeProductModal();closeCustomerModal();}});
    const panel=document.querySelector('#price-panel'), priceMessage=document.querySelector('#price-message');
    const openPriceEditor = row => {
      selectedPriceLine=row;
      document.querySelector('#price-target').textContent=row.querySelector('[data-product-label]').textContent;
      document.querySelector('#manual-price').value=priceInputValue(row.dataset.price);
      document.querySelector('#price-reason').value='';
      const saveAsCustomerPrice=document.querySelector('#save-as-customer-price'); if(saveAsCustomerPrice) saveAsCustomerPrice.checked=true;
      setMessage(priceMessage,'');
      panel?.classList.add('open');
    };
    document.querySelector('#close-price')?.addEventListener('click',()=>panel.classList.remove('open'));
    document.querySelector('#reset-price')?.addEventListener('click',async()=>{
      setMessage(priceMessage,'既定価格に戻しています');
      try{ await api(`/api/v1/sales-orders/${state.selected.id}/lines/${selectedPriceLine.dataset.lineId}/reprice`,{method:'POST',body:JSON.stringify({reason:'画面から既定価格へ戻す'})}); panel.classList.remove('open'); clearDirty(); await selectOrder(state.selected.id); }
      catch(error){ setMessage(priceMessage,error.message,true); }
    });
    panel?.addEventListener('submit',async event=>{
      event.preventDefault(); setMessage(priceMessage,'保存中');
      try{ await api(`/api/v1/sales-orders/${state.selected.id}/lines/${selectedPriceLine.dataset.lineId}/price`,{method:'PATCH',body:JSON.stringify({unit_price:document.querySelector('#manual-price').value,reason:document.querySelector('#price-reason').value,save_as_customer_price:document.querySelector('#save-as-customer-price')?.checked ?? false})}); if(selectedPriceLine.dataset.reviewTaskId){await api(`/api/v1/price-review-tasks/${selectedPriceLine.dataset.reviewTaskId}/updated`,{method:'POST',body:JSON.stringify({reason:'受注画面で新しい取引先個別価格を設定'})});} panel.classList.remove('open'); clearDirty(); await selectOrder(state.selected.id); }
      catch(error){ setMessage(priceMessage,error.message,true); }
    });
    document.querySelector('#orders')?.addEventListener('click',async event=>{
      const row=event.target.closest('tr[data-order-id]'); if(!row||!canCancelOrder)return;
      try{ const data=await api(`/api/v1/sales-orders/${row.dataset.orderId}`); syncCancelOrder(data.sales_order); }catch(_){ syncCancelOrder(null); }
    });
    document.querySelector('#new-order-list')?.addEventListener('click',()=>syncCancelOrder(null));
    cancelOrderButton?.addEventListener('click',async()=>{
      if(!state.selected||!canCancelOrder)return;
      if(!confirmDiscardIfDirty())return;
      const reason=window.prompt('受注を取り消す理由を入力してください。');
      if(reason===null)return;
      if(!reason.trim()){ setMessage(document.querySelector('#form-message'),'取消理由を入力してください。',true); return; }
      try{ await api(`/api/v1/sales-orders/${state.selected.id}/cancel`,{method:'POST',body:JSON.stringify({reason:reason.trim()})}); syncCancelOrder(null); hideOrderDetail(); setMessage(listMessage(),'受注を取り消しました。'); await loadOrders(); }
      catch(error){ setMessage(document.querySelector('#form-message'),error.message,true); }
    });
    hideOrderDetail();
    orderForm.addEventListener('input', markDirty);
    orderForm.addEventListener('change', markDirty);
    window.addEventListener('beforeunload',event=>{ if(!state.dirty)return; event.preventDefault(); event.returnValue=''; });
    loadOrders();
    if(autoRefreshEnabled) setInterval(()=>{ if(orderDetailCard.hidden&&!state.searchDirty){ loadOrders(); } },autoRefreshMs);
  </script>
</body>
</html>
