@php
  $section = $section ?? 'register';
  $isHistory = $section === 'history';
@endphp
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>返品・赤伝 | 販売管理</title>
  <style>
    body{margin:0;background:#f5f7fb;color:#172033;font:13px Inter,"Noto Sans JP",system-ui,sans-serif}
    [hidden]{display:none!important}
    header{height:52px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;background:#fff;border-bottom:1px solid #dce4ee}
    header h1{font-size:15px;font-weight:800;padding-left:10px;letter-spacing:0}
    main{padding:12px 18px}
    h1,h2,h3,p{margin:0}
    h2{font-size:15px}
    h3{font-size:13px}
    .grid{display:grid;grid-template-columns:minmax(650px,1fr) 430px;gap:12px;align-items:start}
    .return-side{position:sticky;top:64px;max-height:calc(100vh - 76px);overflow:auto;align-self:start}
    body.mode-history .grid{grid-template-columns:1fr}
    body.mode-history .return-side{display:none}
    .stack{display:grid;gap:12px}
    .card{background:#fff;border:1px solid #dce4ee;border-radius:6px;overflow:hidden}
    .head{padding:11px 14px;border-bottom:1px solid #e7edf4;display:flex;justify-content:space-between;align-items:center;gap:10px}
    .panel{padding:12px 14px;display:grid;gap:10px}
    .muted{color:#64748b;font-size:11px}
    .notice{min-height:17px;color:#64748b;font-size:11px}
    .error{color:#b42318}
    .actions{display:flex;gap:8px;justify-content:flex-end;align-items:center;flex-wrap:wrap}
    table{width:100%;border-collapse:collapse;table-layout:fixed}
    th,td{padding:9px 11px;border-bottom:1px solid #edf1f6;text-align:left;font-size:11px;vertical-align:middle;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    th{background:#f8faff;color:#64748b;font-size:10px}
    tbody tr{cursor:pointer}
    tbody tr:hover,tbody tr.selected{background:#e7f0ff}
    tbody tr.selected{box-shadow:inset 3px 0 #0b6ff6}
    .badge{display:inline-block;max-width:100%;padding:2px 6px;border-radius:999px;background:#eaf3ff;color:#075ecf;font-size:10px;font-weight:800;overflow:hidden;text-overflow:ellipsis;vertical-align:middle}
    .badge.warn{background:#fff4dd;color:#9a6700}
    .badge.success{background:#e7f7ed;color:#137333}
    label{display:grid;gap:4px;color:#475569;font-size:10px;font-weight:700}
    .inline-check{display:flex;align-items:center;gap:5px;white-space:nowrap;color:#475569;font-size:10px;font-weight:700}
    .inline-check input{width:auto;min-height:auto}
    input,select,textarea,button{font:inherit;font-size:11px}
    input,select,textarea{width:100%;min-height:30px;border:1px solid #ced8e5;border-radius:4px;padding:5px 8px;background:#fff;color:#172033;box-sizing:border-box}
    textarea{min-height:52px;resize:vertical}
    button{cursor:pointer;border:1px solid #cad6e6;border-radius:4px;background:#fff;color:#25344a;padding:7px 9px}
    button:disabled{opacity:.45;cursor:not-allowed}
    .primary{background:#0b6ff6;color:#fff;border-color:#0b6ff6;font-weight:800}
    @keyframes searchPulse{0%,100%{background:#fff7d6;border-color:#f0b429;box-shadow:0 0 0 0 rgba(240,180,41,.28)}50%{background:#ffe08a;border-color:#d89b00;box-shadow:0 0 0 5px rgba(240,180,41,.12)}}
    button.search-attention,button.primary.search-attention{animation:searchPulse 1.8s ease-in-out infinite;color:#172033!important;font-weight:800}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:9px}
    .search-grid{display:grid;grid-template-columns:1fr 1fr 1fr 130px 130px auto;gap:8px;align-items:end}
    .return-search-grid{display:grid;grid-template-columns:1fr 130px 130px 120px auto;gap:8px;align-items:end}
    .full{grid-column:1/-1}
    .summary{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
    .summary div{border:1px solid #e7edf4;border-radius:5px;padding:9px;background:#f8faff}
    .summary strong{display:block;margin-top:3px;font-size:15px}
    .line-box{border:1px solid #e7edf4;border-radius:5px;padding:10px;display:grid;gap:9px;background:#fbfdff}
    .line-title{display:flex;justify-content:space-between;gap:8px}
    .modal-backdrop{position:fixed;inset:0;z-index:50;display:none;align-items:center;justify-content:center;background:rgba(15,23,42,.38);padding:20px}
    .modal-backdrop.open{display:flex}
    .modal{width:min(720px,100%);max-height:86vh;overflow:auto;background:#fff;border-radius:6px;border:1px solid #dce4ee;box-shadow:0 18px 45px rgba(15,23,42,.24)}
    .modal .head{position:sticky;top:0;background:#fff;z-index:1}
    .lot-row{display:grid;grid-template-columns:1.6fr .8fr .8fr;gap:8px;align-items:end;border-bottom:1px solid #edf1f6;padding:9px 0}
    .lot-add-row{display:grid;grid-template-columns:1fr 120px auto;gap:8px;align-items:end;border:1px solid #e7edf4;border-radius:5px;padding:10px;background:#fbfdff}
    .lot-allocation-row{display:grid;grid-template-columns:1fr 90px auto;gap:8px;align-items:center;border-bottom:1px solid #edf1f6;padding:8px 0}
    @media(max-width:1050px){.grid{grid-template-columns:1fr}.return-side{position:static;max-height:none}.form-grid,.summary,.search-grid,.return-search-grid{grid-template-columns:1fr}.full{grid-column:auto}}
  </style>
</head>
<body class="mode-{{ $section }}">
  <header>
    <h1>{{ $isHistory ? '返品・赤伝一覧' : '返品・赤伝登録' }}</h1>
    <div class="actions"><span class="muted">{{ $user->name }}</span><form method="post" action="{{ route('logout') }}">@csrf<button type="submit">ログアウト</button></form></div>
  </header>
  <main>
    <div class="grid">
      <div class="stack">
        <section class="card" @if(!$isHistory) hidden @endif>
          <div class="head"><h2>返品一覧</h2><div class="actions"><button id="refresh" type="button">最新に更新</button><span id="return-count" class="muted">読み込み中</span></div></div>
          <div class="panel">
            <div class="return-search-grid">
              <label>取引先<input id="return-customer" placeholder="取引先名"></label>
              <label>返品日From<input id="return-date-from" type="date"></label>
              <label>返品日To<input id="return-date-to" type="date"></label>
              <label>状態<select id="return-status"><option value="active">通常</option><option value="cancelled">取消済み</option><option value="all">すべて</option></select></label>
              <button type="button" id="return-search">検索</button>
            </div>
          </div>
          <table>
            <thead><tr><th>返品番号</th><th>取引先</th><th>返品日</th><th>状態</th><th>赤伝</th><th>税込返還</th><th>操作</th></tr></thead>
            <tbody id="return-list"></tbody>
          </table>
          <div class="panel">
            <p id="list-msg" class="notice"></p>
          </div>
        </section>
        <section class="card" @if($isHistory) hidden @endif>
          <div class="head"><h2>元請求明細</h2><div class="actions"><label class="inline-check"><input id="source-returnable-only" type="checkbox" checked>返品可能な明細のみ</label><span id="source-count" class="muted"></span></div></div>
          <div class="panel">
            <div class="search-grid">
              <label>取引先<input id="source-customer" placeholder="取引先名"></label>
              <label>請求番号<input id="source-invoice-number" placeholder="請求番号"></label>
              <label>商品<input id="source-product" placeholder="商品名・コード"></label>
              <label>請求日From<input id="source-date-from" type="date"></label>
              <label>請求日To<input id="source-date-to" type="date"></label>
              <button type="button" id="source-search">検索</button>
            </div>
          </div>
          <table>
            <colgroup>
              <col style="width:16%">
              <col style="width:24%">
              <col>
              <col style="width:72px">
              <col style="width:88px">
            </colgroup>
            <thead><tr><th>請求番号</th><th>取引先</th><th>商品</th><th>数量</th><th>税込</th></tr></thead>
            <tbody id="source-lines"></tbody>
          </table>
        </section>
      </div>
      <aside class="stack return-side">
        @if ($canCreate)
        <section id="return-card" class="card" hidden>
          <div class="head"><h2>返品登録</h2></div>
          <form id="return-form" class="panel">
            <div id="selected-line" class="line-box">
              <div class="line-title"><strong>元請求明細を選択</strong><span class="muted">一覧から選択</span></div>
              <p class="muted">確定済み請求の明細を選ぶと、元単価・元消費税率で赤伝を作成します。</p>
            </div>
            <div class="form-grid">
              <label>返品日<input id="return-date" type="date" required></label>
              <label>返品数量<input id="return-quantity" type="number" min="1" step="1" required disabled></label>
              <label class="full">在庫処理<select id="stock-action">
                <option value="return_dedicated_stock">返品専用在庫へ戻す</option>
                <option value="return_stock">通常販売在庫へ戻す</option>
                <option value="non_sales_stock_operation">販売外在庫出入へ回す</option>
                <option value="no_stock">赤伝のみ（在庫に戻さない）</option>
              </select></label>
              <label class="full" id="location-wrap">入庫先<select id="stock-location">
                @foreach($stockLocations as $location)
                  <option value="{{ $location->id }}">{{ $location->name }} / {{ $location->code }}</option>
                @endforeach
              </select></label>
              <div class="full" id="lot-allocation-wrap" style="display:none">
                <div class="line-box">
                  <div class="line-title"><strong>戻すロット内訳</strong><button type="button" id="open-lot-modal">ロット配分</button></div>
                  <p id="lot-allocation-summary" class="muted">通常販売在庫へ戻す場合は、元出荷ロットごとに本数を入力してください。</p>
                </div>
              </div>
              <label class="full">ロットメモ<input id="lot-code" maxlength="80" placeholder="返品専用ロットなど"></label>
              <label class="full">返品理由<textarea id="reason" required maxlength="1000"></textarea></label>
              <label class="full">備考<textarea id="note" maxlength="1000"></textarea></label>
            </div>
            <div class="summary">
              <div><span class="muted">税抜返還</span><strong id="amount-preview">¥0</strong></div>
              <div><span class="muted">消費税</span><strong id="tax-preview">¥0</strong></div>
              <div><span class="muted">税込返還</span><strong id="total-preview">¥0</strong></div>
            </div>
            <p id="form-msg" class="notice"></p>
            <div class="actions"><button class="primary" type="submit" id="submit-return" disabled>赤伝を作成</button></div>
          </form>
        </section>
        @endif
        <section class="card">
          <div class="head"><h2>処理の分離</h2></div>
          <div class="panel">
            <p class="muted">この画面では小売店への返品・赤伝・消費税返還だけを処理します。</p>
            <p class="muted">戻入、瓶詰、破損、詰替、廃棄は「販売外在庫出入」へ回します。</p>
          </div>
        </section>
      </aside>
    </div>
    <div id="lot-modal-backdrop" class="modal-backdrop" aria-hidden="true">
      <div class="modal" role="dialog" aria-modal="true" aria-labelledby="lot-modal-title">
        <div class="head"><h2 id="lot-modal-title">元出荷ロットへ戻す</h2><button type="button" id="close-lot-modal">閉じる</button></div>
        <div class="panel">
          <p class="muted">返品された現物のロットを確認し、戻す本数を入力してください。合計は返品数量と一致する必要があります。</p>
          <div id="lot-modal-lines"></div>
          <p id="lot-modal-msg" class="notice"></p>
          <div class="actions"><button type="button" id="clear-lot-allocation">配分をクリア</button><button class="primary" type="button" id="apply-lot-allocation">配分を反映</button></div>
        </div>
      </div>
    </div>
  </main>
  <script>
    const state = { returns: [], invoices: [], sourceLines: [], selected: null, sourceLots: [], lotAllocations: [] };
    const canCancelReturn = @json($canCancel ?? false);
    const stockLocations = @json($stockLocations->map(fn($location) => ['id' => $location->id, 'name' => $location->name, 'code' => $location->code])->values());
    const yen = value => `¥${Number(value || 0).toLocaleString('ja-JP',{maximumFractionDigits:0})}`;
    const quantityText = value => {
      const number = Number(value || 0);
      if (!Number.isFinite(number)) return '';
      return number.toLocaleString('ja-JP', { minimumFractionDigits: 0, maximumFractionDigits: 4 });
    };
    const quantityInputValue = value => {
      const number = Number(value || 0);
      if (!Number.isFinite(number) || number === 0) return '';
      return number.toLocaleString('en-US', { useGrouping: false, minimumFractionDigits: 0, maximumFractionDigits: 4 });
    };
    const lineQuantityText = line => line.quantity_display || quantityText(line.quantity);
    const lineRemainingQuantity = line => line.remaining_returnable_quantity ?? line.quantity;
    const lineRemainingQuantityText = line => line.remaining_returnable_quantity_display || quantityText(lineRemainingQuantity(line));
    const sourceLineQuantityText = line => Number(lineRemainingQuantity(line) || 0) === Number(line.quantity || 0)
      ? lineQuantityText(line)
      : `${lineRemainingQuantityText(line)} / 元 ${lineQuantityText(line)}`;
    const lotQuantityText = lot => lot.quantity_display || quantityText(lot.quantity);
    const sourceLot = lotId => state.sourceLots.find(lot => String(lot.production_lot_id) === String(lotId));
    const lotAllocatedQuantity = lotId => state.lotAllocations
      .filter(row => String(row.production_lot_id) === String(lotId))
      .reduce((sum, row) => sum + Number(row.quantity || 0), 0);
    const labels = { draft:'下書き', credit_drafted:'赤伝作成済', cancelled:'取消', confirmed:'確定' };
    const api = async (url, options = {}) => {
      const response = await fetch(url, { credentials:'same-origin', headers:{ Accept:'application/json', ...(options.body ? {'Content-Type':'application/json'} : {}) }, ...options });
      const body = await response.json();
      if (!response.ok) throw new Error(body.error?.message || body.message || '処理に失敗しました。');
      return body.data;
    };
    const message = (selector, text, isError = false) => {
      const el = document.querySelector(selector);
      if (!el) return;
      el.textContent = text;
      el.classList.toggle('error', isError);
    };
    const pulseSearch = (selector, dirty = true) => document.querySelector(selector)?.classList.toggle('search-attention', dirty);
    const markReturnSearchDirty = () => pulseSearch('#return-search', true);
    const markSourceSearchDirty = () => pulseSearch('#source-search', true);
    const clearSearchDirty = () => { pulseSearch('#return-search', false); pulseSearch('#source-search', false); };
    const cell = value => {
      const td = document.createElement('td');
      td.textContent = value ?? '';
      td.title = td.textContent;
      return td;
    };
    const badge = status => {
      const span = document.createElement('span');
      span.className = `badge ${status === 'credit_drafted' || status === 'confirmed' ? 'success' : 'warn'}`;
      span.textContent = labels[status] || status || '';
      return span;
    };
    const renderReturns = () => {
      const tbody = document.querySelector('#return-list');
      tbody.replaceChildren(...state.returns.map(item => {
        const total = (item.lines || []).reduce((sum, line) => sum + Number(line.total_amount || 0), 0);
        const tr = document.createElement('tr');
        tr.append(cell(item.return_number), cell(item.customer_name), cell(item.return_date));
        const status = document.createElement('td'); status.append(badge(item.status)); tr.append(status);
        tr.append(cell(item.credit_invoice_number || ''), cell(yen(total)));
        const action = document.createElement('td');
        if (canCancelReturn && item.status !== 'cancelled') {
          const button = document.createElement('button');
          button.type = 'button';
          button.textContent = '取消';
          button.addEventListener('click', event => {
            event.stopPropagation();
            cancelReturn(item);
          });
          action.append(button);
        }
        tr.append(action);
        return tr;
      }));
      document.querySelector('#return-count').textContent = `${state.returns.length}件`;
    };
    const cancelReturn = async item => {
      const reason = window.prompt(`${item.return_number} を取り消します。取消理由を入力してください。`);
      if (reason === null) return;
      if (!reason.trim()) {
        message('#list-msg', '取消理由を入力してください。', true);
        return;
      }
      try {
        await api(`/api/v1/sales-returns/${item.id}/cancel`, { method:'POST', body:JSON.stringify({ reason }) });
        message('#list-msg', `${item.return_number} を取り消しました。`);
        await load();
      } catch (error) {
        message('#list-msg', error.message, true);
      }
    };
    const renderSourceLines = () => {
      state.sourceLines = state.invoices
        .filter(invoice => invoice.status === 'confirmed' && invoice.document_type !== 'credit_memo')
        .flatMap(invoice => (invoice.lines || []).map(line => ({ ...line, invoice })))
        .filter(line => Number(lineRemainingQuantity(line) || 0) > 0);
      const tbody = document.querySelector('#source-lines');
      tbody.replaceChildren(...state.sourceLines.map(line => {
        const tr = document.createElement('tr');
        if (state.selected?.id === line.id) tr.classList.add('selected');
        tr.addEventListener('click', () => selectLine(line));
        tr.append(cell(line.invoice.invoice_number), cell(line.invoice.customer_name), cell(line.product_name), cell(sourceLineQuantityText(line)), cell(yen(line.total_amount)));
        return tr;
      }));
      document.querySelector('#source-count').textContent = `${state.sourceLines.length}件`;
    };
    const setReturnFormVisible = visible => {
      const card = document.querySelector('#return-card');
      const form = document.querySelector('#return-form');
      if (card) card.hidden = !visible;
      if (form) form.hidden = !visible;
    };
    const selectLine = line => {
      state.selected = line;
      state.sourceLots = [];
      state.lotAllocations = [];
      setReturnFormVisible(true);
      document.querySelector('#return-quantity').disabled = false;
      document.querySelector('#return-quantity').max = quantityInputValue(lineRemainingQuantity(line));
      document.querySelector('#return-quantity').value = quantityInputValue(lineRemainingQuantity(line));
      document.querySelector('#submit-return').disabled = false;
      document.querySelector('#selected-line').innerHTML = `
        <div class="line-title"><strong>${line.product_name}</strong><span class="muted">${line.invoice.invoice_number}</span></div>
        <p class="muted">${line.invoice.customer_name} / 返品可能 ${lineRemainingQuantityText(line)} / 元数量 ${lineQuantityText(line)} / 単価 ${yen(line.unit_price)} / 税率 ${Number(line.tax_rate || 0) * 100}%</p>
      `;
      renderSourceLines();
      updatePreview();
      loadSourceLots(line);
    };
    const allocationTotal = () => state.lotAllocations.reduce((sum, row) => sum + Number(row.quantity || 0), 0);
    const renderSourceLots = () => {
      const isReturnStock = document.querySelector('#stock-action').value === 'return_stock';
      document.querySelector('#lot-allocation-wrap').style.display = isReturnStock ? 'block' : 'none';
      if (!isReturnStock) return;
      const total = allocationTotal();
      const required = Number(document.querySelector('#return-quantity').value || 0);
      const summary = document.querySelector('#lot-allocation-summary');
      if (state.sourceLots.length === 0) {
        summary.textContent = '元出荷ロットがありません。通常販売在庫へ戻せません。';
      } else if (total === 0) {
        summary.textContent = `元出荷ロット候補 ${state.sourceLots.length}件。ロット配分を入力してください。`;
      } else {
        summary.textContent = `配分合計 ${quantityText(total)} / 返品数量 ${quantityText(required)}`;
      }
    };
    const loadSourceLots = async line => {
      try {
        const data = await api(`/api/v1/sales-return-source-lines/${line.shipment_line_id}/lots`);
        state.sourceLots = data.source_lots || [];
        if (state.sourceLots.length === 1) {
          state.lotAllocations = [{
            production_lot_id: state.sourceLots[0].production_lot_id,
            quantity: document.querySelector('#return-quantity').value,
          }];
        }
        renderSourceLots();
      } catch (error) {
        state.sourceLots = [];
        renderSourceLots();
        message('#form-msg', error.message, true);
      }
    };
    const updatePreview = () => {
      const line = state.selected;
      const qty = Number(document.querySelector('#return-quantity').value || 0);
      if (!line || qty <= 0) {
        document.querySelector('#amount-preview').textContent = yen(0);
        document.querySelector('#tax-preview').textContent = yen(0);
        document.querySelector('#total-preview').textContent = yen(0);
        return;
      }
      const amount = qty * Number(line.unit_price || 0);
      const ratio = qty / Number(line.quantity || 1);
      const tax = Number(line.tax_amount || 0) * ratio;
      document.querySelector('#amount-preview').textContent = yen(amount);
      document.querySelector('#tax-preview').textContent = yen(tax);
      document.querySelector('#total-preview').textContent = yen(amount + tax);
      renderSourceLots();
    };
    const syncStockAction = () => {
      const action = document.querySelector('#stock-action').value;
      document.querySelector('#location-wrap').style.display = ['return_stock','return_dedicated_stock'].includes(action) ? 'grid' : 'none';
      renderSourceLots();
    };
    const openLotModal = () => {
      if (!state.selected) { message('#form-msg','元請求明細を選択してください。',true); return; }
      const modalLines = document.querySelector('#lot-modal-lines');
      if (state.sourceLots.length === 0) {
        modalLines.innerHTML = '<p class="muted">元出荷ロットがありません。</p>';
      } else {
        renderLotModalLines();
      }
      document.querySelector('#lot-modal-backdrop').classList.add('open');
      document.querySelector('#lot-modal-backdrop').setAttribute('aria-hidden','false');
      message('#lot-modal-msg','');
    };
    const renderLotModalLines = () => {
      const modalLines = document.querySelector('#lot-modal-lines');
      const total = allocationTotal();
      const required = Number(document.querySelector('#return-quantity').value || 0);
      const addRow = document.createElement('div');
      addRow.className = 'lot-add-row';
      const options = state.sourceLots.map(lot => {
        const allocated = lotAllocatedQuantity(lot.production_lot_id);
        const remaining = Math.max(0, Number(lot.quantity || 0) - allocated);
        return `<option value="${lot.production_lot_id}" data-location-id="${lot.stock_location_id || ''}" data-lot-code="${lot.lot_code || ''}" ${remaining <= 0 ? 'disabled' : ''}>${lot.lot_code || ''} / ${lot.display_name || ''} / 元出荷 ${lotQuantityText(lot)} / 残 ${quantityText(remaining)}</option>`;
      }).join('');
      addRow.innerHTML = `
        <label>戻すロット<select id="lot-add-source">${options}</select></label>
        <label>戻す本数<input id="lot-add-quantity" type="number" min="0" step="1" value="${quantityInputValue(Math.max(0, required - total))}"></label>
        <button type="button" id="add-lot-allocation">追加</button>
      `;
      const list = document.createElement('div');
      list.id = 'lot-allocation-list';
      const rows = state.lotAllocations.map((row, index) => {
        const lot = sourceLot(row.production_lot_id) || {};
        const item = document.createElement('div');
        item.className = 'lot-allocation-row';
        item.innerHTML = `
          <div><strong>${row.lot_code || lot.lot_code || ''}</strong><div class="muted">${lot.display_name || ''}</div></div>
          <div>${quantityText(row.quantity)}</div>
          <button type="button" data-remove-lot-index="${index}">削除</button>
        `;
        return item;
      });
      list.replaceChildren(...rows);
      if (rows.length === 0) list.innerHTML = '<p class="muted">追加されたロット配分はまだありません。</p>';
      modalLines.replaceChildren(addRow, list);
      document.querySelector('#add-lot-allocation')?.addEventListener('click', addLotAllocation);
      document.querySelectorAll('[data-remove-lot-index]').forEach(button => {
        button.addEventListener('click', () => {
          state.lotAllocations.splice(Number(button.dataset.removeLotIndex), 1);
          renderSourceLots();
          renderLotModalLines();
          message('#lot-modal-msg','');
        });
      });
    };
    const addLotAllocation = () => {
      const select = document.querySelector('#lot-add-source');
      const input = document.querySelector('#lot-add-quantity');
      const lot = sourceLot(select?.value);
      const qty = Number(input?.value || 0);
      if (!lot || qty <= 0) {
        message('#lot-modal-msg', '追加するロットと本数を入力してください。', true);
        return;
      }
      const total = allocationTotal();
      const required = Number(document.querySelector('#return-quantity').value || 0);
      const lotTotal = lotAllocatedQuantity(lot.production_lot_id) + qty;
      if (lotTotal > Number(lot.quantity || 0)) {
        message('#lot-modal-msg', `このロットの元出荷本数 ${lotQuantityText(lot)} を超えています。`, true);
        return;
      }
      if (total + qty > required) {
        message('#lot-modal-msg', `配分合計が返品数量 ${quantityText(required)} を超えています。`, true);
        return;
      }
      state.lotAllocations.push({
        production_lot_id: Number(lot.production_lot_id),
        quantity: quantityInputValue(qty),
        stock_location_id: Number(lot.stock_location_id || document.querySelector('#stock-location').value),
        lot_code: lot.lot_code || null,
      });
      renderSourceLots();
      renderLotModalLines();
      message('#lot-modal-msg', `配分合計 ${quantityText(allocationTotal())} / 返品数量 ${quantityText(required)}`);
    };
    const closeLotModal = () => {
      document.querySelector('#lot-modal-backdrop').classList.remove('open');
      document.querySelector('#lot-modal-backdrop').setAttribute('aria-hidden','true');
    };
    const applyLotAllocation = () => {
      const rows = state.lotAllocations;
      const total = allocationTotal();
      const required = Number(document.querySelector('#return-quantity').value || 0);
      if (total !== required) {
        message('#lot-modal-msg', `配分合計 ${quantityText(total)} と返品数量 ${quantityText(required)} が一致していません。`, true);
        return;
      }
      renderSourceLots();
      closeLotModal();
    };
    const dateString = date => date.toISOString().slice(0, 10);
    const setDefaultSearchDates = () => {
      const to = new Date();
      const from = new Date();
      from.setMonth(from.getMonth() - 1);
      document.querySelector('#source-date-from').value = dateString(from);
      document.querySelector('#source-date-to').value = dateString(to);
      document.querySelector('#return-date-from').value = dateString(from);
      document.querySelector('#return-date-to').value = dateString(to);
    };
    const returnSearchQuery = () => {
      const params = new URLSearchParams();
      const add = (key, selector) => {
        const value = document.querySelector(selector)?.value?.trim();
        if (value) params.set(key, value);
      };
      add('customer', '#return-customer');
      add('return_date_from', '#return-date-from');
      add('return_date_to', '#return-date-to');
      params.set('status', document.querySelector('#return-status')?.value || 'active');
      params.set('limit', '200');
      return params.toString();
    };
    const invoiceSearchQuery = () => {
      const params = new URLSearchParams();
      const add = (key, selector) => {
        const value = document.querySelector(selector)?.value?.trim();
        if (value) params.set(key, value);
      };
      add('customer', '#source-customer');
      add('invoice_number', '#source-invoice-number');
      add('product', '#source-product');
      add('invoice_date_from', '#source-date-from');
      add('invoice_date_to', '#source-date-to');
      params.set('status', 'confirmed');
      params.set('limit', '200');
      if (document.querySelector('#source-returnable-only')?.checked) {
        params.set('returnable_only', '1');
      }
      return params.toString();
    };
    const load = async () => {
      try {
        document.querySelector('#refresh').disabled = true;
        document.querySelector('#return-search').disabled = true;
        document.querySelector('#source-search').disabled = true;
        const returnQuery = returnSearchQuery();
        const invoiceQuery = invoiceSearchQuery();
        const [returnData, invoiceData] = await Promise.all([
          api(`/api/v1/sales-returns?${returnQuery}`),
          api(`/api/v1/billing/invoices?${invoiceQuery}`)
        ]);
        state.returns = returnData.sales_returns || [];
        state.invoices = invoiceData.invoices || [];
        renderReturns();
        renderSourceLines();
        clearSearchDirty();
        message('#list-msg', `更新 ${new Date().toLocaleTimeString('ja-JP',{hour:'2-digit',minute:'2-digit',second:'2-digit'})}`);
      } catch (error) {
        message('#list-msg', error.message, true);
      } finally {
        document.querySelector('#refresh').disabled = false;
        document.querySelector('#return-search').disabled = false;
        document.querySelector('#source-search').disabled = false;
      }
    };
    setDefaultSearchDates();
    document.querySelector('#return-date').value = new Date().toISOString().slice(0,10);
    document.querySelector('#return-quantity')?.addEventListener('input', updatePreview);
    document.querySelector('#stock-action')?.addEventListener('change', syncStockAction);
    document.querySelector('#refresh')?.addEventListener('click', load);
    document.querySelector('#return-search')?.addEventListener('click', load);
    document.querySelector('#source-search')?.addEventListener('click', load);
    document.querySelectorAll('#return-customer,#return-date-from,#return-date-to').forEach(input => {
      input.addEventListener('input', markReturnSearchDirty);
      input.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
          event.preventDefault();
          load();
        }
      });
    });
    document.querySelector('#return-status')?.addEventListener('change', markReturnSearchDirty);
    document.querySelectorAll('#source-customer,#source-invoice-number,#source-product,#source-date-from,#source-date-to').forEach(input => {
      input.addEventListener('input', markSourceSearchDirty);
      input.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
          event.preventDefault();
          load();
        }
      });
    });
    document.querySelector('#source-returnable-only')?.addEventListener('change', markSourceSearchDirty);
    document.querySelector('#return-form')?.addEventListener('submit', async event => {
      event.preventDefault();
      if (!state.selected) {
        message('#form-msg', '元請求明細を選択してください。', true);
        return;
      }
      const action = document.querySelector('#stock-action').value;
      const payload = {
        customer_id: state.selected.invoice.customer_id,
        return_date: document.querySelector('#return-date').value,
        settlement_method: 'credit_memo',
        reason: document.querySelector('#reason').value,
        note: document.querySelector('#note').value || null,
        lines: [{
          source_invoice_line_id: state.selected.id,
          quantity: document.querySelector('#return-quantity').value,
          stock_action: action,
          stock_location_id: ['return_stock','return_dedicated_stock'].includes(action) ? Number(document.querySelector('#stock-location').value) : null,
          lot_code: document.querySelector('#lot-code').value || null,
          reason: document.querySelector('#reason').value,
          lots: action === 'return_stock' ? state.lotAllocations : [],
        }]
      };
      if (action === 'return_stock' && allocationTotal() !== Number(payload.lines[0].quantity || 0)) {
        message('#form-msg', '通常販売在庫へ戻す場合は、ロット配分合計を返品数量と一致させてください。', true);
        return;
      }
      try {
        document.querySelector('#submit-return').disabled = true;
        const data = await api('/api/v1/sales-returns', { method:'POST', body:JSON.stringify(payload) });
        message('#form-msg', `${data.sales_return.return_number} を作成しました。赤伝 ${data.sales_return.credit_invoice_number || ''}`);
        event.target.reset();
        document.querySelector('#return-date').value = new Date().toISOString().slice(0,10);
        state.selected = null;
        setReturnFormVisible(false);
        document.querySelector('#return-quantity').disabled = true;
        state.lotAllocations = [];
        updatePreview();
        syncStockAction();
        await load();
      } catch (error) {
        message('#form-msg', error.message, true);
        document.querySelector('#submit-return').disabled = false;
      }
    });
    document.querySelector('#open-lot-modal')?.addEventListener('click', openLotModal);
    document.querySelector('#close-lot-modal')?.addEventListener('click', closeLotModal);
    document.querySelector('#apply-lot-allocation')?.addEventListener('click', applyLotAllocation);
    document.querySelector('#clear-lot-allocation')?.addEventListener('click', () => { state.lotAllocations = []; openLotModal(); renderSourceLots(); });
    syncStockAction();
    load();
  </script>
</body>
</html>
