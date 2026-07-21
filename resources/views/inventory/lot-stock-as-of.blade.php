<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ ($printMode ?? false) ? 'ロット在庫一覧' : 'ロット在庫照会' }}</title>
  <style>
    *{box-sizing:border-box}body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:0;background:#f5f7fb;color:#172033;font-size:13px}main{max-width:1280px;margin:0 auto;padding:24px}header{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-bottom:16px}h1{margin:0;font-size:22px}.muted{color:#667085;font-size:12px}.card{background:#fff;border:1px solid #dce4ee;border-radius:8px;padding:16px;box-shadow:0 1px 2px rgba(15,23,42,.04)}.toolbar{display:grid;grid-template-columns:160px minmax(240px,1fr) auto auto auto auto;gap:10px;align-items:end;margin-bottom:14px}label{display:grid;gap:5px;font-size:12px;color:#475467}input{height:36px;border:1px solid #cbd5e1;border-radius:6px;padding:0 10px;background:#fff;font:inherit}button{height:36px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;padding:0 14px;font:inherit;cursor:pointer}button.primary{background:#126deb;border-color:#126deb;color:#fff;font-weight:700}.check-field{display:flex;align-items:center;gap:6px;height:36px;white-space:nowrap}.check-field input{width:auto;height:auto}.summary{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:2px 0 10px}.table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:8px}table{width:100%;border-collapse:collapse;background:#fff;min-width:940px;table-layout:fixed}th,td{border-bottom:1px solid #e2e8f0;padding:9px 10px;text-align:left;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}th{background:#f8fafc;color:#475467;font-size:12px;font-weight:700}td.num{text-align:right;font-variant-numeric:tabular-nums}.negative-stock{background:#fff1f0}.negative-stock td.num{color:#b42318;font-weight:700}.empty{padding:28px;text-align:center;color:#667085}.lot-name{font-weight:700;display:inline-block;max-width:100%;overflow:hidden;text-overflow:ellipsis;vertical-align:bottom}.sub{display:block;color:#667085;font-size:11px;margin-top:2px}.print-title{display:none}.print-actions{display:flex;justify-content:flex-end;gap:8px;margin-bottom:12px}.print-mode header,.print-mode .toolbar{display:none}.print-mode .print-title{display:block;margin:0 0 12px}.print-mode .card{box-shadow:none}
    @media print{body{background:#fff;padding:0;font-size:10.5px}main{max-width:none;padding:0}header,.toolbar,.screen-only,.no-print{display:none!important}.card{border:0;box-shadow:none;padding:0}.print-title{display:block;margin:0 0 12px}.print-title h1{font-size:18px;margin:0 0 4px}.table-wrap{overflow:visible;border:0}table{min-width:0}th,td{padding:5px 6px;border-bottom:1px solid #cfd6df}th{background:#f2f4f7!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}@page{size:A4 landscape;margin:12mm}}
    @media(max-width:900px){main{padding:16px}.toolbar{grid-template-columns:1fr 1fr}.toolbar button{width:100%}}
  </style>
</head>
<body class="{{ ($printMode ?? false) ? 'print-mode' : '' }}">
<main>
  <header>
    <div>
      <h1>ロット在庫照会</h1>
      <div class="muted">指定した基準日時点のロット別在庫を、在庫移動履歴から集計します。</div>
    </div>
  </header>

  <section class="card">
    @if($printMode ?? false)
      <div class="print-actions no-print">
        <button class="primary" onclick="window.print()">印刷する</button>
        <button onclick="window.close()">閉じる</button>
      </div>
    @endif
    <div class="toolbar">
      <label>基準日<input id="as-of-date" type="date" value="{{ $asOfDate ?? $today }}"></label>
      <label>ロット検索<input id="keyword" type="search" value="{{ $keyword ?? '' }}" placeholder="ロット名・ロットコード・商品名"></label>
      <label class="check-field"><input id="show-zero" type="checkbox" @checked($showZeroStockLots ?? false)>在庫0を表示</label>
      <button class="primary" id="search-button" type="button">検索</button>
      <button id="clear-button" type="button">クリア</button>
      <button id="print-button" type="button">印刷</button>
    </div>

    <div class="print-title">
      <h1>ロット在庫一覧</h1>
      <div id="print-condition"></div>
    </div>

    <div class="summary">
      <div id="condition" class="muted"></div>
      <div id="count" class="muted"></div>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
        <tr>
          <th style="width:110px">商品コード</th>
          <th>商品名</th>
          <th style="width:160px">ロット</th>
          <th style="width:150px">在庫場所</th>
          <th style="width:120px">数量</th>
          <th style="width:105px">製造日</th>
          <th style="width:105px">瓶詰日</th>
          <th style="width:105px">最終移動日</th>
        </tr>
        </thead>
        <tbody id="rows">
          <tr><td colspan="8" class="empty">読み込み中です。</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</main>

<script>
(() => {
  const els = {
    date: document.querySelector('#as-of-date'),
    keyword: document.querySelector('#keyword'),
    rows: document.querySelector('#rows'),
    condition: document.querySelector('#condition'),
    printCondition: document.querySelector('#print-condition'),
    count: document.querySelector('#count'),
    search: document.querySelector('#search-button'),
    clear: document.querySelector('#clear-button'),
    print: document.querySelector('#print-button'),
    showZero: document.querySelector('#show-zero'),
  };

  const api = async (url) => {
    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || '読み込みに失敗しました。');
    return payload.data;
  };
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
  const productTypeLabel = (type) => ({sake:'酒',kasu:'酒粕',food:'食品',goods:'グッズ・その他'}[type] || '-');
  const qty = (value) => {
    const n = Number(value ?? 0);
    return Number.isFinite(n) ? Math.round(n).toLocaleString('ja-JP') : '0';
  };
  const fmtDate = (value) => value ? String(value).replaceAll('-', '/') : '-';
  const typeField = document.createElement('label');
  typeField.innerHTML = '区分<select id="product-type"><option value="">すべて</option><option value="sake">酒</option><option value="kasu">酒粕</option><option value="food">食品</option><option value="goods">グッズ・その他</option></select>';
  els.keyword?.closest('label')?.after(typeField);
  const productType = document.querySelector('#product-type');
  productType.value = @json($productType ?? '');
  els.productType = productType;
  document.querySelector('#rows')?.closest('table')?.querySelector('thead tr th:first-child')?.insertAdjacentHTML('afterend', '<th style="width:90px">区分</th>');

  async function load() {
    const params = new URLSearchParams({ as_of_date: els.date.value });
    const keyword = els.keyword.value.trim();
    if (keyword) params.set('q', keyword);
    if (els.showZero?.checked) params.set('include_zero_stock', '1');
    els.rows.innerHTML = '<tr><td colspan="8" class="empty">読み込み中です。</td></tr>';
    try {
      const data = await api(`/api/v1/inventory/lot-stock-as-of?${params}`);
      const rows = data.lot_stock_balances || [];
      const condition = `基準日 ${fmtDate(data.as_of_date)}${keyword ? ` / 検索 ${keyword}` : ''}`;
      els.condition.textContent = condition;
      els.printCondition.textContent = condition;
      els.count.textContent = `${rows.length}件`;
      if (!rows.length) {
        els.rows.innerHTML = '<tr><td colspan="8" class="empty">該当するロット在庫はありません。</td></tr>';
        return;
      }
      els.rows.innerHTML = rows.map((row) => `
        <tr class="${Number(row.physical_quantity) < 0 ? 'negative-stock' : ''}">
          <td>${esc(row.product_code)}</td>
          <td>${esc(row.product_name)}</td>
          <td><span class="lot-name">${esc(row.lot_name || row.lot_code)}</span>${row.lot_name && row.lot_name !== row.lot_code ? `<span class="sub">コード: ${esc(row.lot_code)}</span>` : ''}</td>
          <td>${esc(row.stock_location_name)}<span class="sub">${esc(row.stock_location_code)}</span></td>
          <td class="num">${esc(qty(row.physical_quantity))} ${esc(row.unit_name)}</td>
          <td>${fmtDate(row.production_date)}</td>
          <td>${fmtDate(row.bottling_date)}</td>
          <td>${fmtDate(row.latest_movement_date)}</td>
        </tr>
      `).join('');
    } catch (error) {
      els.rows.innerHTML = `<tr><td colspan="8" class="empty">${esc(error.message)}</td></tr>`;
      els.count.textContent = '';
    }
  }

  els.search.addEventListener('click', load);
  els.keyword.addEventListener('keydown', (event) => { if (event.key === 'Enter') load(); });
  els.date.addEventListener('change', load);
  if (els.showZero) els.showZero.addEventListener('change', load);
  if (els.clear) els.clear.addEventListener('click', () => { els.date.value = '{{ $today }}'; els.keyword.value = ''; load(); });
  if (els.print) els.print.addEventListener('click', () => {
    const params = new URLSearchParams({ as_of_date: els.date.value });
    const keyword = els.keyword.value.trim();
    if (keyword) params.set('q', keyword);
    if (els.showZero?.checked) params.set('include_zero_stock', '1');
    window.open(`/inventory/lot-stock-as-of/print?${params}`, '_blank');
  });
  load = async function() {
    const params = new URLSearchParams({ as_of_date: els.date.value });
    const keyword = els.keyword.value.trim();
    if (keyword) params.set('q', keyword);
    if (els.productType?.value) params.set('product_type', els.productType.value);
    if (els.showZero?.checked) params.set('include_zero_stock', '1');
    els.rows.innerHTML = '<tr><td colspan="9" class="empty">読み込み中です。</td></tr>';
    try {
      const data = await api(`/api/v1/inventory/lot-stock-as-of?${params}`);
      const rows = data.lot_stock_balances || [];
      const condition = `基準日 ${fmtDate(data.as_of_date)}${keyword ? ` / 検索 ${keyword}` : ''}${els.productType?.value ? ` / 区分 ${productTypeLabel(els.productType.value)}` : ''}`;
      els.condition.textContent = condition;
      els.printCondition.textContent = condition;
      els.count.textContent = `${rows.length}件`;
      if (!rows.length) {
        els.rows.innerHTML = '<tr><td colspan="9" class="empty">該当するロット在庫はありません。</td></tr>';
        return;
      }
      els.rows.innerHTML = rows.map((row) => `
        <tr class="${Number(row.physical_quantity) < 0 ? 'negative-stock' : ''}">
          <td>${esc(row.product_code)}</td>
          <td>${esc(row.product_type_label || productTypeLabel(row.product_type))}</td>
          <td>${esc(row.product_name)}</td>
          <td><span class="lot-name">${esc(row.lot_name || row.lot_code)}</span>${row.lot_name && row.lot_name !== row.lot_code ? `<span class="sub">コード ${esc(row.lot_code)}</span>` : ''}</td>
          <td>${esc(row.stock_location_name)}<span class="sub">${esc(row.stock_location_code)}</span></td>
          <td class="num">${esc(qty(row.physical_quantity))} ${esc(row.unit_name)}</td>
          <td>${fmtDate(row.production_date)}</td>
          <td>${fmtDate(row.bottling_date)}</td>
          <td>${fmtDate(row.latest_movement_date)}</td>
        </tr>
      `).join('');
    } catch (error) {
      els.rows.innerHTML = `<tr><td colspan="9" class="empty">${esc(error.message)}</td></tr>`;
      els.count.textContent = '';
    }
  };
  if (els.search) els.search.addEventListener('click', (event) => { event.stopImmediatePropagation(); load(); }, true);
  if (els.date) els.date.addEventListener('change', (event) => { event.stopImmediatePropagation(); load(); }, true);
  if (els.showZero) els.showZero.addEventListener('change', (event) => { event.stopImmediatePropagation(); load(); }, true);
  if (els.clear) els.clear.addEventListener('click', (event) => { event.stopImmediatePropagation(); els.date.value = '{{ $today }}'; els.keyword.value = ''; if (els.productType) els.productType.value = ''; load(); }, true);
  if (els.productType) els.productType.addEventListener('change', load);
  if (els.print) els.print.addEventListener('click', (event) => {
    event.stopImmediatePropagation();
    const params = new URLSearchParams({ as_of_date: els.date.value });
    const keyword = els.keyword.value.trim();
    if (keyword) params.set('q', keyword);
    if (els.productType?.value) params.set('product_type', els.productType.value);
    if (els.showZero?.checked) params.set('include_zero_stock', '1');
    window.open(`/inventory/lot-stock-as-of/print?${params}`, '_blank');
  }, true);
  load();
})();
</script>
</body>
</html>
