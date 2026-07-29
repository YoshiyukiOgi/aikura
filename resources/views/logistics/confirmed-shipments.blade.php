<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Confirmed Shipments | Aikura</title>
  <style>
    body{margin:0;background:#f5f7fb;color:#172033;font:13px Inter,"Noto Sans JP",sans-serif}
    header{height:52px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;background:#fff;border-bottom:1px solid #dce4ee}
    header h1{font-size:15px;font-weight:800;padding-left:10px;letter-spacing:.02em}
    main{padding:12px 18px}
    .layout{display:grid;grid-template-columns:minmax(620px,1fr) 360px;gap:12px}
    .card{background:#fff;border:1px solid #dce4ee;border-radius:6px;overflow:hidden}
    .head{padding:11px 14px;border-bottom:1px solid #e7edf4}
    .filters,.detail{padding:12px;display:grid;gap:9px}
    input,select,button,textarea{font:inherit;border:1px solid #cad6e6;border-radius:4px;padding:6px 8px}
    button{cursor:pointer}
    @keyframes searchPulse{0%,100%{background:#fff7d6;border-color:#f0b429;box-shadow:0 0 0 0 rgba(240,180,41,.28)}50%{background:#ffe08a;border-color:#d89b00;box-shadow:0 0 0 5px rgba(240,180,41,.12)}}
    button.search-attention{animation:searchPulse 1.8s ease-in-out infinite;color:#172033;font-weight:800}
    .danger{color:#b42318;border-color:#f0b7b1}
    table{width:100%;border-collapse:collapse;table-layout:fixed}
    th,td{padding:9px 11px;border-bottom:1px solid #edf1f6;text-align:left;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    th{background:#f8faff;color:#64748b;font-size:10px}
    tbody tr{cursor:pointer}
    tbody tr:hover,tbody tr.selected{background:#e7f0ff}
    .badge{display:inline-block;max-width:100%;padding:2px 6px;border-radius:999px;background:#eaf3ff;color:#075ecf;font-size:10px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:middle}
    .badge.cancelled{background:#fee4e2;color:#b42318}
    .badge.success{background:#e7f7ed;color:#137333}
    .badge.warn{background:#fff4dd;color:#9a6700}
    .muted{color:#64748b;font-size:11px}
    .line{display:block}
    .line span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    @media(max-width:1000px){.layout{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <header><h1 data-ja="出荷確定"></h1><span class="muted">{{ $user->name }}</span></header>
  <main>
    <div class="layout">
      <section class="card">
        <div class="head"><h2><span data-ja="出荷確定一覧"></span> <span id="count" class="muted"></span></h2><button id="refresh-confirmed" type="button">最新に更新</button></div>
        <div class="filters">
          <input id="q">
          <select id="status-filter"></select>
          <select id="pick-filter"></select>
        </div>
        <table>
          <thead><tr><th data-ja="出荷伝票"></th><th data-ja="受注番号"></th><th data-ja="取引先"></th><th data-ja="状態"></th><th data-ja="ピッキング"></th></tr></thead>
          <tbody id="list"></tbody>
        </table>
      </section>
      <section class="card">
        <div class="head"><h2 data-ja="返品・訂正／出荷取消"></h2></div>
        <div id="detail" class="detail"><p class="muted" data-ja="一覧から伝票を選択してください。"></p></div>
      </section>
    </div>
  </main>
  <script>
    const canCancel = @json($canCancel);
    const canConfirm = @json($canConfirm);
    const refreshSettings = @json(\App\Models\AppSetting::values(['auto_refresh_enabled'=>'1','auto_refresh_interval_seconds'=>'30']));
    const autoRefreshEnabled = refreshSettings.auto_refresh_enabled === '1';
    const autoRefreshMs = Math.max(5, Number(refreshSettings.auto_refresh_interval_seconds || 30)) * 1000;
    const ja = {
      searchPlaceholder: '伝票番号・受注番号・取引先で検索',
      allStatus: '状態：すべて',
      issuedOnly: '発伝済のみ',
      confirmedOnly: '出荷確定のみ',
      cancelledOnly: '確定取消のみ',
      allPick: 'ピッキング状況：すべて',
      picked: 'ピッキング済み',
      openPick: 'ピッキング中／待ち',
      partiallyPicked: 'ピッキング中',
      waitingPick: 'ピッキング待ち',
      ready: '発伝待ち',
      issued: '発伝済',
      confirmed: '出荷確定',
      cancelled: '確定取消',
      reprintButton: '出荷伝票を再印刷',
      confirmPrintButton: '出荷確定・印刷',
      confirmedDone: '出荷確定しました。',
      items: '件',
      pickStatus: 'ピッキング状況：',
      cancelButton: '確定取消（請求前のみ）',
      cancelPrompt: '取消理由を入力してください。在庫を戻し、請求対象から除外します。',
      cancelNote: '取消すると在庫を戻し、請求対象から除外します。取消後も確定取消としてこの一覧に残ります。',
      cancelledNote: 'この伝票は確定取消済みです。受注には戻さず、履歴としてこの一覧に残しています。',
      invoicedNote: '請求書に含まれているため出荷取消はできません。返品・訂正として処理します。',
      cancelledDone: '確定取消しました。在庫を戻し、請求対象から外しました。',
      returnReason: '返品・訂正理由',
      shippedPrefix: '（出荷 ',
      shippedSuffix: '）',
      error: '処理に失敗しました',
      dash: '—'
    };
    document.querySelectorAll('[data-ja]').forEach(el => { el.textContent = el.dataset.ja; });
    document.querySelector('#q').placeholder = ja.searchPlaceholder;
    const dateHeader = document.createElement('th');
    dateHeader.textContent = '日付';
    document.querySelector('thead tr th:first-child')?.after(dateHeader);
    const statusFilter = document.querySelector('#status-filter');
    statusFilter.append(new Option(ja.allStatus, ''), new Option(ja.issuedOnly, 'draft'), new Option(ja.confirmedOnly, 'confirmed'), new Option(ja.cancelledOnly, 'cancelled'));
    const pickFilter = document.querySelector('#pick-filter');
    pickFilter.append(new Option(ja.allPick, ''), new Option(ja.picked, 'picked'), new Option(ja.openPick, 'open'));
    const api = async (url, options = {}) => {
      const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', ...(options.body ? {'Content-Type': 'application/json'} : {}) },
        ...options
      });
      const body = await response.json();
      if (!response.ok) throw new Error(body.error?.message || body.message || ja.error);
      return body.data;
    };
    const list = document.querySelector('#list'), detail = document.querySelector('#detail'), q = document.querySelector('#q');
    let rows = [], selected = null, searchDirty = false;
    const setSearchDirty = dirty => {
      searchDirty = dirty;
      document.querySelector('#refresh-confirmed')?.classList.toggle('search-attention', dirty);
    };
    const pickLabel = status => status === 'picked' ? ja.picked : status === 'partially_picked' ? ja.partiallyPicked : ja.waitingPick;
    const isDocumentIssued = shipment => !!(shipment?.document_issued_at && shipment.document_issued_at !== 'null');
    const shipmentStatusLabel = shipment => shipment.status === 'cancelled' ? ja.cancelled : shipment.status === 'draft' ? (isDocumentIssued(shipment) ? ja.issued : ja.ready) : ja.confirmed;
    const formatMonthDay = value => value ? value.slice(5, 10) : ja.dash;
    const addOverflowTitles = (root = document) => root.querySelectorAll('td,th,.badge,.line span,strong,p').forEach(element => {
      const text = element.textContent.trim();
      if (text) element.title = text;
    });
    new MutationObserver(mutations => mutations.forEach(mutation => mutation.addedNodes.forEach(node => {
      if (node.nodeType === 1) addOverflowTitles(node);
    }))).observe(document.body, { childList: true, subtree: true });
    function draw() {
      const term = q.value.toLowerCase();
      const status = statusFilter.value;
      const pick = pickFilter.value;
      const visible = rows.filter(row => {
        const text = [row.shipment.document_number, row.shipment.sales_order_number, row.shipment.customer_name].join(' ').toLowerCase();
        return (!term || text.includes(term))
          && (!status || row.shipment.status === status)
          && (!pick || (pick === 'picked' ? row.pickStatus === 'picked' : row.pickStatus !== 'picked'));
      });
      list.replaceChildren(...visible.map(row => {
        const tr = document.createElement('tr');
        if (selected?.shipment.id === row.shipment.id) tr.className = 'selected';
        tr.onclick = () => { selected = row; draw(); showDetail(); };
        const values = [
          row.shipment.document_number,
          formatMonthDay(row.shipment.document_date),
          row.shipment.sales_order_number || ja.dash,
          row.shipment.customer_name || '',
          shipmentStatusLabel(row.shipment),
          pickLabel(row.pickStatus)
        ];
        values.forEach((value, index) => {
          const td = document.createElement('td');
          td.title = index === 1 ? (row.shipment.document_date || value) : value;
          if (index === 4 || index === 5) {
            const badge = document.createElement('span');
            badge.className = 'badge' + (index === 4 ? (row.shipment.status === 'cancelled' ? ' cancelled' : row.shipment.status === 'draft' ? (isDocumentIssued(row.shipment) ? ' warn' : '') : ' success') : (row.pickStatus === 'picked' ? ' success' : ' warn'));
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
      document.querySelector('#count').textContent = visible.length + ja.items + ' / 更新 ' + new Date().toLocaleTimeString('ja-JP',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
    }
    function quantityControl(max) {
      const wrap = document.createElement('div');
      wrap.className = 'quantity';
      const minus = document.createElement('button');
      minus.type = 'button';
      minus.textContent = '−';
      const input = document.createElement('input');
      input.type = 'text';
      input.inputMode = 'numeric';
      input.value = '0';
      const plus = document.createElement('button');
      plus.type = 'button';
      plus.textContent = '＋';
      const clamp = () => {
        const value = Math.max(0, Math.min(max, Math.round(Number(input.value) || 0)));
        input.value = String(value);
      };
      minus.onclick = () => { input.value = String(Math.max(0, Number(input.value) - 1)); clamp(); };
      plus.onclick = () => { input.value = String(Math.min(max, Number(input.value) + 1)); clamp(); };
      input.oninput = clamp;
      input.onblur = clamp;
      wrap.append(minus, input, plus);
      return wrap;
    }
    function showDetail() {
      if (!selected) return;
      detail.replaceChildren();
      const title = document.createElement('strong');
      title.textContent = selected.shipment.document_number;
      title.title = title.textContent;
      detail.append(title);
      const status = document.createElement('p');
      status.className = 'muted';
      status.textContent = shipmentStatusLabel(selected.shipment);
      detail.append(status);
      const state = document.createElement('p');
      state.className = 'muted';
      state.textContent = ja.pickStatus + pickLabel(selected.pickStatus);
      state.title = state.textContent;
      detail.append(state);
      if (isDocumentIssued(selected.shipment) || selected.shipment.status !== 'draft') {
        const reprint = document.createElement('button');
        reprint.type = 'button';
        reprint.textContent = ja.reprintButton;
        reprint.onclick = () => window.open('/shipments/' + selected.shipment.id + '/print', '_blank');
        detail.append(reprint);
      }
      selected.shipment.lines.forEach(line => {
        const row = document.createElement('label');
        row.className = 'line';
        const name = document.createElement('span');
        name.textContent = line.product_name + ja.shippedPrefix + Math.round(Number(line.quantity)) + (line.unit_name || '') + ja.shippedSuffix;
        name.title = name.textContent;
        row.append(name);
        detail.append(row);
      });
      if (selected.shipment.status === 'cancelled') {
        const note = document.createElement('p');
        note.className = 'muted';
        note.textContent = ja.cancelledNote;
        detail.append(note);
        return;
      }
      if (selected.shipment.status === 'draft' && isDocumentIssued(selected.shipment)) {
        if (canConfirm) {
          const confirm = document.createElement('button');
          confirm.className = 'primary';
          confirm.type = 'button';
          confirm.textContent = ja.confirmPrintButton;
          confirm.onclick = confirmPrint;
          detail.append(confirm);
        }
        const note = document.createElement('p');
        note.className = 'muted';
        note.textContent = '伝票は発伝済みです。出荷確定・印刷を押すと出荷確定になります。';
        detail.append(note);
        return;
      }
      if (selected.shipment.status === 'draft') {
        const note = document.createElement('p');
        note.className = 'muted';
        note.textContent = 'まだ発伝していません。出荷画面で出荷伝票を作成してください。';
        detail.append(note);
        return;
      }
      if (!selected.shipment.invoiced && canCancel) {
        const cancel = document.createElement('button');
        cancel.className = 'danger';
        cancel.textContent = ja.cancelButton;
        cancel.onclick = cancelShipment;
        detail.append(cancel);
        const note = document.createElement('p');
        note.className = 'muted';
        note.textContent = ja.cancelNote;
        detail.append(note);
      } else {
        const note = document.createElement('p');
        note.className = 'muted';
        note.textContent = ja.invoicedNote;
        detail.append(note);
      }
      const reason = document.createElement('textarea');
      reason.placeholder = ja.returnReason;
      detail.append(reason);
    }
    async function cancelShipment() {
      const reason = window.prompt(ja.cancelPrompt);
      if (!reason) return;
      try {
        await api('/api/v1/shipments/' + selected.shipment.id + '/cancel', { method: 'POST', body: JSON.stringify({ reason }) });
        selected = null;
        detail.innerHTML = '';
        const p = document.createElement('p');
        p.className = 'muted';
        p.textContent = ja.cancelledDone;
        detail.append(p);
        await load();
      } catch (error) {
        alert(error.message);
      }
    }
    async function confirmPrint() {
      if (!selected?.shipment) return;
      const printWindow = window.open('about:blank', '_blank');
      try {
        const done = await api('/api/v1/shipments/' + selected.shipment.id + '/print-and-confirm', { method: 'POST', body: JSON.stringify({ reason: ja.confirmPrintButton }) });
        if (printWindow) printWindow.location = '/shipments/' + done.shipment.id + '/print';
        selected = null;
        detail.replaceChildren();
        const p = document.createElement('p');
        p.className = 'muted';
        p.textContent = ja.confirmedDone;
        detail.append(p);
        await load();
      } catch (error) {
        printWindow?.close();
        alert(error.message);
      }
    }
    async function load() {
      const refresh = document.querySelector('#refresh-confirmed');
      try {
        setSearchDirty(false);
        if (refresh) { refresh.disabled = true; refresh.textContent = '更新中...'; }
        const [ships, picks, instructions] = await Promise.all([
          api('/api/v1/shipments'),
          api('/api/v1/shipment-picks'),
          api('/api/v1/shipment-instructions')
        ]);
        rows = ships.shipments
          .filter(shipment => ['draft', 'confirmed', 'cancelled'].includes(shipment.status))
          .map(shipment => {
            const instructionId = shipment.source_shipment_instruction_id || picks.shipment_picks.find(pick => pick.id === shipment.source_shipment_pick_id)?.shipment_instruction_id;
            const instruction = instructions.shipment_instructions.find(item => item.id === instructionId);
            return { shipment, pickStatus: instruction?.status || 'instructed' };
          });
        draw();
      } catch (error) {
        alert(error.message);
      } finally {
        if (refresh) { refresh.disabled = false; refresh.textContent = '最新に更新'; }
      }
    }
    q.oninput = () => setSearchDirty(true);
    statusFilter.onchange = () => setSearchDirty(true);
    pickFilter.onchange = () => setSearchDirty(true);
    document.querySelector('#refresh-confirmed')?.addEventListener('click',()=>load());
    load();
    if(autoRefreshEnabled) setInterval(()=>{ if(!selected && !searchDirty){ load(); } },autoRefreshMs);
  </script>
</body>
</html>
