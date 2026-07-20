<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>出荷 | 販売管理</title>
  <style>
    body{margin:0;background:#f5f7fb;color:#172033;font:13px Inter,"Noto Sans JP",sans-serif}
    header{height:52px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;background:#fff;border-bottom:1px solid #dce4ee}
    header h1{font-size:15px;font-weight:800;padding-left:10px;letter-spacing:.02em}
    main{padding:12px 18px}
    .grid{display:grid;grid-template-columns:minmax(600px,1fr) 360px;gap:12px}
    .card{background:#fff;border:1px solid #dce4ee;border-radius:6px;overflow:hidden}
    .detail-card{position:sticky;top:12px;align-self:start;max-height:calc(100vh - 24px);overflow:auto}
    .head{padding:11px 14px;border-bottom:1px solid #e7edf4;display:flex;justify-content:space-between;align-items:center}
    h1,h2,p{margin:0}
    h2{font-size:15px}
    .muted{color:#64748b;font-size:11px}
    table{width:100%;border-collapse:collapse;table-layout:fixed}
    th,td{padding:9px 11px;border-bottom:1px solid #edf1f6;text-align:left;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    th{background:#f8faff;color:#64748b;font-size:10px}
    th.sortable{padding:0}
    th.sortable button{width:100%;height:100%;min-height:34px;display:flex;align-items:center;justify-content:space-between;gap:6px;border:0;border-radius:0;background:transparent;color:inherit;padding:9px 11px;font-size:10px;font-weight:800;text-align:left}
    th.sortable button::after{content:'↕';color:#94a3b8;font-size:10px}
    th.sortable button.active::after{content:'↓';color:#0b6ff6}
    th.sortable button.active.asc::after{content:'↑'}
    tbody tr{cursor:pointer}
    tbody tr:hover,tbody tr.selected{background:#e7f0ff}
    .selected{box-shadow:inset 3px 0 #0b6ff6}
    .panel{display:grid;gap:10px;padding:14px}
    .filters{display:flex;gap:8px;align-items:center;padding:10px 14px;border-bottom:1px solid #edf1f6}
    .filters select{font:inherit;border:1px solid #cad6e6;border-radius:4px;padding:6px 8px;background:#fff}
    .actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
    button{font:inherit;font-size:11px;cursor:pointer;border:1px solid #cad6e6;border-radius:4px;background:#fff;color:#25344a;padding:7px 9px}
    .primary{background:#0b6ff6;color:#fff;border-color:#0b6ff6;font-weight:800}
    .danger{color:#b42318;border-color:#f0b7b1}
    .notice{min-height:17px;font-size:11px;color:#64748b}
    .error{color:#b42318}
    .badge{display:inline-block;max-width:100%;padding:2px 6px;border-radius:999px;font-size:10px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:middle}
    .wait{background:#fff4dd;color:#9a6700}
    .ready{background:#eaf3ff;color:#075ecf}
    .progress{background:#fff7ed;color:#c2410c}
    .done{background:#ecfdf3;color:#027a48}
    .danger-badge{background:#fee4e2;color:#b42318}
    .detail-lines{border:1px solid #edf1f6;border-radius:6px;overflow:hidden}
    .detail-lines th:first-child,.detail-lines td:first-child{white-space:normal;line-height:1.35}
    .detail-lines th:nth-child(2),.detail-lines td:nth-child(2){width:90px;text-align:right;white-space:nowrap}
    .detail-lines th:nth-child(3),.detail-lines td:nth-child(3){width:70px;text-align:right;white-space:nowrap}
    a{color:#075ecf;text-decoration:none;font-weight:700}
    @media(max-width:1000px){.grid{grid-template-columns:1fr}.detail-card{position:static;max-height:none}}
  </style>
</head>
<body>
  <header>
    <h1>出荷</h1>
    <div><a href="{{ route('shipment-picks.index') }}">出荷作業</a>　<span class="muted">{{ $user->name }}</span></div>
  </header>
  <main>
    <div class="grid">
      <section class="card detail-card">
        <div class="head"><h2>出荷一覧</h2><div class="actions" style="padding:0"><button id="refresh-shipments" type="button">最新に更新</button><span id="count" class="muted"></span></div></div>
        <div class="filters"><select id="status-filter"><option value="">状態：すべて</option><option value="waiting">出荷指示</option><option value="ready">発伝待ち</option><option value="issued">伝票作成済</option><option value="completed">出荷済み</option></select></div>
        <table>
          <thead><tr><th class="sortable"><button type="button" data-sort-key="sales_order">受注番号</button></th><th class="sortable"><button type="button" data-sort-key="document_number">出荷伝票</button></th><th class="sortable"><button type="button" data-sort-key="shipment_date">出荷日</button></th><th class="sortable"><button type="button" data-sort-key="customer">取引先</button></th><th>出荷状態</th><th>ピッキング</th></tr></thead>
          <tbody id="list"></tbody>
        </table>
      </section>
      <section class="card">
        <div class="head"><h2 id="title">出荷</h2></div>
        <div class="panel">
          <p id="detail" class="notice">左の一覧から受注を選択してください。</p>
          <div id="detail-lines" class="detail-lines" hidden>
            <table>
              <thead><tr><th>商品</th><th>容量</th><th>本数</th></tr></thead>
              <tbody id="detail-line-list"></tbody>
            </table>
          </div>
          <div id="actions" class="actions"></div>
          <p id="message" class="notice"></p>
        </div>
      </section>
    </div>
  </main>
  <script>
    const canCreate = @json($canCreate), canConfirm = @json($canConfirm), canCancel = @json($canCancel);
    const refreshSettings = @json(\App\Models\AppSetting::values(['auto_refresh_enabled'=>'1','auto_refresh_interval_seconds'=>'30']));
    const autoRefreshEnabled = refreshSettings.auto_refresh_enabled === '1';
    const autoRefreshMs = Math.max(5, Number(refreshSettings.auto_refresh_interval_seconds || 30)) * 1000;
    const api = async (url, options = {}) => {
      const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', ...(options.body ? {'Content-Type': 'application/json'} : {}) },
        ...options
      });
      const body = await response.json();
      if (!response.ok) throw new Error(body.error?.message || body.message || '処理に失敗しました');
      return body.data;
    };
    const list = document.querySelector('#list'), count = document.querySelector('#count'), title = document.querySelector('#title'), detail = document.querySelector('#detail'), detailLines = document.querySelector('#detail-lines'), detailLineList = document.querySelector('#detail-line-list'), actions = document.querySelector('#actions'), message = document.querySelector('#message');
    let rows = [], selected = null, sortState = { key: 'shipment_date', direction: 'desc' };
    const show = (text, error = false) => {
      message.textContent = text;
      message.classList.toggle('error', error);
    };
    const statusKey = row => !row.shipment ? 'waiting' : row.shipment.shipping_status_key || '';
    const statusLabel = row => row.shipment?.shipping_status_label || ({waiting:'出荷指示',ready:'発伝待ち',issued:'伝票作成済',completed:'出荷済み'}[statusKey(row)] || '');
    const statusClass = row => ({waiting:'wait',ready:'progress',issued:'ready',completed:'done'}[statusKey(row)] || 'wait');
    const pickLabel = row => row.instruction?.status === 'picked' ? 'ピッキング済み' : row.instruction?.status === 'partially_picked' ? 'ピッキング中' : 'ピッキング待ち';
    const pickClass = row => row.instruction?.status === 'picked' ? 'done' : row.instruction?.status === 'partially_picked' ? 'progress' : 'wait';
    const isLotReady = row => (row.instruction?.lines || []).length > 0 && (row.instruction.lines || []).every(line => Number(line.lot_allocated_quantity || 0) >= Number(line.quantity || 0));
    const canCompleteShipment = row => statusKey(row) === 'issued' && row.instruction?.status === 'picked' && isLotReady(row);
    const shortDate = date => date ? date.slice(5).replace('-', '/') : '—';
    const capacityLabel = line => line.capacity_value == null ? '—' : `${Number(line.capacity_value).toLocaleString('ja-JP')} ${line.capacity_unit_name || ''}`.trim();
    const shipmentDate = row => row.shipment?.document_date || row.instruction.scheduled_shipment_date || '';
    const salesOrderNumber = row => row.instruction?.sales_order_number || row.shipment?.sales_order_number || '';
    const shipmentDocumentNumber = row => row.shipment?.document_number || '';
    const customerName = row => row.instruction?.customer_name || row.shipment?.customer_name || '';
    const sortValue = (row, key) => ({
      sales_order: salesOrderNumber(row),
      document_number: shipmentDocumentNumber(row),
      shipment_date: shipmentDate(row),
      customer: customerName(row),
    }[key] || '');
    const compareRows = (a, b) => {
      const av = sortValue(a, sortState.key);
      const bv = sortValue(b, sortState.key);
      const primary = String(av).localeCompare(String(bv), 'ja', { numeric: true, sensitivity: 'base' });
      const fallback = Number(a.shipment?.id || a.instruction?.id || 0) - Number(b.shipment?.id || b.instruction?.id || 0);
      const result = primary || fallback;
      return sortState.direction === 'asc' ? result : -result;
    };
    const updateSortButtons = () => {
      document.querySelectorAll('[data-sort-key]').forEach(button => {
        const active = button.dataset.sortKey === sortState.key;
        button.classList.toggle('active', active);
        button.classList.toggle('asc', active && sortState.direction === 'asc');
        button.title = active ? (sortState.direction === 'asc' ? '昇順' : '降順') : 'クリックして並び替え';
      });
    };
    const addOverflowTitles = (root = document) => root.querySelectorAll('td,th,.badge,#title,#detail').forEach(element => {
      const text = element.textContent.trim();
      if (text) element.title = text;
    });
    new MutationObserver(mutations => mutations.forEach(mutation => mutation.addedNodes.forEach(node => {
      if (node.nodeType === 1) addOverflowTitles(node);
    }))).observe(document.body, { childList: true, subtree: true });
    async function load() {
      const refresh = document.querySelector('#refresh-shipments');
      try {
        if (refresh) { refresh.disabled = true; refresh.textContent = '更新中...'; }
        const [shipData, pickData, instructionData] = await Promise.all([
          api('/api/v1/shipments'),
          api('/api/v1/shipment-picks'),
          api('/api/v1/shipment-instructions')
        ]);
        const instructions = instructionData.shipment_instructions;
        const picks = pickData.shipment_picks;
        const shipments = shipData.shipments;
        const instructionRows = instructions
          .filter(instruction => instruction.status !== 'cancelled')
          .map(instruction => {
            const pick = picks.find(p => p.shipment_instruction_id === instruction.id && p.status === 'picked');
            const shipment = shipments.find(s => s.source_shipment_instruction_id === instruction.id || (pick && s.source_shipment_pick_id === pick.id));
            return { instruction, pick, shipment };
          });
        const shipmentRows = shipments
          .filter(shipment => ['draft','confirmed'].includes(shipment.status) && !shipment.invoiced)
          .map(shipment => {
            const pick = shipment.source_shipment_pick_id ? picks.find(p => p.id === shipment.source_shipment_pick_id) : null;
            const instructionId = shipment.source_shipment_instruction_id || pick?.shipment_instruction_id;
            const instruction = instructions.find(item => item.id === instructionId) || null;
            return { instruction, pick, shipment };
          });
        const waitingRows = instructionRows.filter(row => !row.shipment);
        rows = [...waitingRows, ...shipmentRows]
          .sort((a,b) => Number(b.shipment?.id || b.instruction?.id || 0) - Number(a.shipment?.id || a.instruction?.id || 0));
        if (selected) {
          selected = rows.find(row => (selected.shipment && row.shipment?.id === selected.shipment.id) || (selected.instruction && row.instruction?.id === selected.instruction.id && !selected.shipment)) || null;
          if (selected) detailView();
        }
        render();
      } catch (error) {
        show(error.message, true);
      } finally {
        if (refresh) { refresh.disabled = false; refresh.textContent = '最新に更新'; }
      }
    }
    function render() {
      const filter = document.querySelector('#status-filter')?.value || '';
        const visible = rows.filter(row => !filter || statusKey(row) === filter).sort(compareRows);
        count.textContent = visible.length + '件 / 更新 ' + new Date().toLocaleTimeString('ja-JP',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
        updateSortButtons();
        list.replaceChildren(...visible.map(row => {
        const tr = document.createElement('tr');
        if ((selected?.shipment && selected.shipment.id === row.shipment?.id) || (!selected?.shipment && selected?.instruction?.id === row.instruction?.id)) tr.className = 'selected';
        tr.onclick = () => { selected = row; render(); detailView(); };
        const cells = [salesOrderNumber(row) || '—', shipmentDocumentNumber(row) || '未発伝', shortDate(shipmentDate(row)), customerName(row), statusLabel(row), pickLabel(row)];
        cells.forEach((value, index) => {
          const td = document.createElement('td');
          td.title = index === 2 && shipmentDate(row) ? shipmentDate(row) : value;
          if (index === 4 || index === 5) {
            const badge = document.createElement('span');
            badge.className = 'badge ' + (index === 4 ? statusClass(row) : pickClass(row));
            badge.textContent = value;
            badge.title = value;
            td.append(badge);
          } else {
            td.textContent = value;
          }
          tr.append(td);
        });
        return tr;
      }));
    }
    function detailView() {
      if (!selected) return;
      actions.replaceChildren();
      renderDetailLines();
      title.textContent = selected.instruction?.sales_order_number || selected.shipment?.sales_order_number || '出荷';
      title.title = title.textContent;
      const key = statusKey(selected);
      if (key === 'waiting') {
        detail.textContent = '出荷指示中です。伝票作成後も出荷済みになるまでは内容の見直し・取消ができます。ピッキング状況：' + pickLabel(selected);
        if (canCreate) {
          const button = document.createElement('button');
          button.className = 'primary';
          button.textContent = '出荷伝票を作成';
          button.onclick = issueDocument;
          actions.append(button);
        }
      } else if (key === 'ready') {
        detail.textContent = selected.shipment.document_number + ' はピッキング済みの出荷ドラフトです。まだ出荷伝票は発伝していません。ピッキング状況：' + pickLabel(selected);
        if (canCreate) {
          const button = document.createElement('button');
          button.className = 'primary';
          button.textContent = '出荷伝票を作成';
          button.onclick = issueExistingShipmentDocument;
          actions.append(button);
        }
      } else if (key === 'issued') {
        detail.textContent = selected.shipment.document_number + ' は伝票作成済です。出荷済みになるまでは伝票の再印刷・出荷取消ができます。ピッキング状況：' + pickLabel(selected);
        const reprint = document.createElement('button');
        reprint.textContent = '出荷伝票を再印刷';
        reprint.onclick = () => window.open('/shipments/' + selected.shipment.id + '/print', '_blank');
        actions.append(reprint);
        if (canConfirm) {
          const button = document.createElement('button');
          button.className = 'primary';
          button.textContent = '出荷完了';
          button.onclick = completeShipment;
          button.disabled = !canCompleteShipment(selected);
          button.title = button.disabled ? 'ピッキング済み・ロット割当済み・伝票作成済みになると押せます。' : '';
          actions.append(button);
        }
        if (!canCompleteShipment(selected)) {
          const note = document.createElement('p');
          note.className = 'muted';
          note.textContent = '出荷完了は、ピッキング済み・ロット割当済み・伝票作成済みの時だけ実行できます。';
          actions.append(note);
        }
      } else if (key === 'completed') {
        detail.textContent = selected.shipment.document_number + ' は出荷済みです。出荷済み後は直接書き換えず、返品・訂正で扱います。ピッキング状況：' + pickLabel(selected);
        const button = document.createElement('button');
        button.textContent = '出荷伝票を再印刷';
        button.onclick = () => window.open('/shipments/' + selected.shipment.id + '/print', '_blank');
        actions.append(button);
      } else {
        detail.textContent = selected.shipment.document_number + ' は取消済みです。履歴として表示しています。';
      }
      detail.title = detail.textContent;
      if (canCancel && key !== 'completed') {
        const cancel = document.createElement('button');
        cancel.className = 'danger';
        cancel.textContent = '出荷取消';
        cancel.onclick = cancelFlow;
        actions.append(cancel);
      }
    }
    function renderDetailLines() {
      const lines = selected.shipment?.lines?.length ? selected.shipment.lines : selected.instruction.lines || [];
      detailLines.hidden = !lines.length;
      detailLineList.replaceChildren(...lines.map(line => {
        const tr = document.createElement('tr');
        const product = document.createElement('td');
        product.textContent = line.product_name || '商品';
        product.title = product.textContent;
        const capacity = document.createElement('td');
        capacity.textContent = capacityLabel(line);
        const qty = document.createElement('td');
        qty.textContent = String(Math.round(Number(line.confirmed_quantity || line.quantity || 0)));
        tr.append(product, capacity, qty);
        return tr;
      }));
    }
    async function issueDocument() {
      const printWindow = window.open('about:blank', '_blank');
      try {
        const issued = await api('/api/v1/shipment-instructions/' + selected.instruction.id + '/issue-shipment-document', { method: 'POST', body: JSON.stringify({}) });
        if (printWindow) printWindow.location = '/shipments/' + issued.shipment.id + '/print';
        selected = null;
        title.textContent = '出荷';
        detail.textContent = '出荷伝票を作成しました。出荷完了までは出荷一覧で確認できます。';
        detailLines.hidden = true;
        detailLineList.replaceChildren();
        actions.replaceChildren();
        show('出荷伝票を作成しました。');
        await load();
      } catch (error) {
        printWindow?.close();
        show(error.message, true);
      }
    }
    async function issueExistingShipmentDocument() {
      const printWindow = window.open('about:blank', '_blank');
      try {
        const issued = await api('/api/v1/shipments/' + selected.shipment.id + '/issue-document', { method: 'POST', body: JSON.stringify({}) });
        if (printWindow) printWindow.location = '/shipments/' + issued.shipment.id + '/print';
        selected = null;
        title.textContent = '出荷';
        detail.textContent = '出荷伝票を作成しました。出荷完了までは出荷一覧で確認できます。';
        detailLines.hidden = true;
        detailLineList.replaceChildren();
        actions.replaceChildren();
        show('出荷伝票を作成しました。');
        await load();
      } catch (error) {
        printWindow?.close();
        show(error.message, true);
      }
    }
    async function completeShipment() {
      try {
        const done = await api('/api/v1/shipments/' + selected.shipment.id + '/print-and-confirm', { method: 'POST', body: JSON.stringify({ reason: '出荷完了' }) });
        selected = null;
        title.textContent = '出荷';
        detailLines.hidden = true;
        detailLineList.replaceChildren();
        actions.replaceChildren();
        show('出荷完了にしました。');
        await load();
      } catch (error) {
        show(error.message, true);
      }
    }
    async function cancelShipment() {
      const reason = window.prompt('出荷取消の理由を入力してください。');
      if (!reason) return;
      try {
        await api('/api/v1/shipments/' + selected.shipment.id + '/cancel', { method: 'POST', body: JSON.stringify({ reason }) });
        selected = null;
        detail.textContent = '出荷を取り消しました。';
        detailLines.hidden = true;
        detailLineList.replaceChildren();
        actions.replaceChildren();
        await load();
      } catch (error) {
        show(error.message, true);
      }
    }
    async function cancelFlow() {
      const reason = window.prompt('出荷取消の理由を入力してください。');
      if (!reason) return;
      try {
        await api('/api/v1/shipment-instructions/' + selected.instruction.id + '/cancel-shipping-flow', { method: 'POST', body: JSON.stringify({ reason }) });
        selected = null;
        detail.textContent = '出荷取消しました。受注一覧で確認してください。';
        detailLines.hidden = true;
        detailLineList.replaceChildren();
        actions.replaceChildren();
        await load();
      } catch (error) {
        show(error.message, true);
      }
    }
    document.querySelector('#status-filter')?.addEventListener('change',()=>render());
    document.querySelector('#refresh-shipments')?.addEventListener('click',()=>load());
    document.querySelectorAll('[data-sort-key]').forEach(button => {
      button.addEventListener('click', () => {
        const key = button.dataset.sortKey;
        if (sortState.key === key) {
          sortState.direction = sortState.direction === 'asc' ? 'desc' : 'asc';
        } else {
          sortState = { key, direction: 'asc' };
        }
        render();
      });
    });
    load();
    if(autoRefreshEnabled) setInterval(load,autoRefreshMs);
  </script>
</body>
</html>
