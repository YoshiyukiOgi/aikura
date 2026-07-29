<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>出荷検索 | 販売管理</title>
  <style>
    body{margin:0;background:#f5f7fb;color:#172033;font:13px Inter,"Noto Sans JP",sans-serif}
    header{height:52px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;background:#fff;border-bottom:1px solid #dce4ee}
    header h1{font-size:15px;font-weight:800;padding-left:10px}
    main{padding:12px 18px}
    .grid{display:grid;grid-template-columns:minmax(720px,1fr) 380px;gap:12px;align-items:start}
    .grid>.card:first-child{max-height:calc(100vh - 76px);overflow:auto}
    .grid>.card:last-child{position:sticky;top:64px;max-height:calc(100vh - 76px);overflow:auto;align-self:start}
    .card{background:#fff;border:1px solid #dce4ee;border-radius:6px;overflow:hidden}
    .head{padding:11px 14px;border-bottom:1px solid #e7edf4;display:flex;justify-content:space-between;align-items:center;gap:12px}
    h1,h2,p{margin:0} h2{font-size:15px}.muted{color:#64748b;font-size:11px}.error{color:#b42318}
    .filters{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:8px;padding:10px 14px;border-bottom:1px solid #edf1f6;align-items:end}
    label{display:grid;gap:4px;color:#475569;font-size:10px;font-weight:800}
    input,select{font:inherit;border:1px solid #cad6e6;border-radius:4px;padding:7px 8px;background:#fff;min-width:0}
    .wide{grid-column:span 2}.actions{display:flex;gap:8px;align-items:center;justify-content:flex-end;flex-wrap:wrap}
    button{font:inherit;font-size:11px;cursor:pointer;border:1px solid #cad6e6;border-radius:4px;background:#fff;color:#25344a;padding:7px 9px}
    button.primary{background:#0b6ff6;color:#fff;border-color:#0b6ff6;font-weight:800}
    @keyframes searchPulse{0%,100%{background:#fff7d6;border-color:#f0b429;box-shadow:0 0 0 0 rgba(240,180,41,.28)}50%{background:#ffe08a;border-color:#d89b00;box-shadow:0 0 0 5px rgba(240,180,41,.12)}}
    button.search-attention,button.primary.search-attention{animation:searchPulse 1.8s ease-in-out infinite;color:#172033!important;font-weight:800}
    table{width:100%;border-collapse:collapse;table-layout:fixed}
    th,td{padding:9px 10px;border-bottom:1px solid #edf1f6;text-align:left;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    th{background:#f8faff;color:#64748b;font-size:10px}
    th.num,td.num{text-align:right}
    td.customer-cell{font-weight:800;color:#172033}
    table th:nth-child(2),table td:nth-child(2){width:62px!important}
    table th:nth-child(4),table td:nth-child(4){width:64px!important}
    table th:nth-child(5),table td:nth-child(5){width:56px!important}
    table th:nth-child(6),table td:nth-child(6){width:80px!important}
    tbody tr{cursor:pointer}tbody tr:hover,tbody tr.selected{background:#e7f0ff}tbody tr.selected{box-shadow:inset 3px 0 #0b6ff6}
    .badge{display:inline-block;max-width:100%;padding:2px 6px;border-radius:999px;font-size:10px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:middle}
    .draft{background:#fff4dd;color:#9a6700}.confirmed{background:#ecfdf3;color:#027a48}.cancelled{background:#fee4e2;color:#b42318}.invoice{background:#eaf3ff;color:#075ecf}.none{background:#f1f5f9;color:#64748b}
    .pager{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-top:1px solid #edf1f6}
    .panel{display:grid;gap:10px;padding:14px}.detail-list{display:grid;grid-template-columns:118px 1fr;gap:7px 10px}.detail-list dt{color:#64748b;font-size:11px}.detail-list dd{margin:0;font-weight:700;min-width:0;overflow-wrap:anywhere}
    .left-actions{justify-content:flex-start}
    .mini-table th:first-child,.mini-table td:first-child{white-space:normal;line-height:1.35}.mini-table th:nth-child(2),.mini-table td:nth-child(2),.mini-table th:nth-child(3),.mini-table td:nth-child(3){width:76px;text-align:right}
    @media(max-width:1100px){.grid{grid-template-columns:1fr}.grid>.card:first-child,.grid>.card:last-child{position:static;max-height:none}.filters{grid-template-columns:repeat(2,minmax(0,1fr))}.wide{grid-column:span 2}}
  </style>
</head>
<body>
  <header>
    <h1>出荷検索</h1>
    <div><a href="/shipments">出荷作業へ</a> <span class="muted">{{ $user->name }}</span></div>
  </header>
  <main>
    <div class="grid">
      <section class="card">
        <div class="head">
          <h2>過去出荷検索</h2>
          <div class="actions"><button id="clear" type="button">クリア</button><button id="search" type="button">検索</button><button id="export-csv" type="button">表示中CSV</button><span id="count" class="muted"></span></div>
        </div>
        <div class="filters">
          <label class="wide">取引先<input id="customer" placeholder="取引先名"></label>
          <label>出荷番号<input id="document-number" placeholder="S-..."></label>
          <label>受注番号<input id="sales-order-number" placeholder="O-..."></label>
          <label>状態<select id="status"><option value="">すべて</option><option value="draft">下書き</option><option value="confirmed">出荷済み</option><option value="cancelled">取消済み</option></select></label>
          <label>請求<select id="invoice-status"><option value="all">すべて</option><option value="invoiced">請求書作成済み</option><option value="uninvoiced">未請求</option></select></label>
          <label>出荷日From<input id="document-date-from" type="date"></label>
          <label>出荷日To<input id="document-date-to" type="date"></label>
          <label>請求対象From<input id="billing-target-from" type="month"></label>
          <label>請求対象To<input id="billing-target-to" type="month"></label>
          <label>並び順<select id="sort"><option value="document_date">出荷日</option><option value="document_number">出荷番号</option><option value="customer">取引先</option><option value="billing_target_date">請求対象日</option><option value="status">状態</option></select></label>
          <label>方向<select id="direction"><option value="desc">降順</option><option value="asc">昇順</option></select></label>
        </div>
        <table>
          <thead><tr><th style="width:132px">出荷番号</th><th style="width:92px">出荷日</th><th>取引先</th><th style="width:128px">受注番号</th><th style="width:82px">状態</th><th style="width:120px">請求</th></tr></thead>
          <tbody id="list"></tbody>
        </table>
        <div class="pager"><span id="page" class="muted"></span><div class="actions"><button id="prev" type="button">前へ</button><button id="next" type="button">次へ</button></div></div>
      </section>
      <section class="card">
        <div class="head"><h2>出荷詳細</h2></div>
        <div id="detail" class="panel"><p class="muted">出荷を選択してください。</p></div>
      </section>
    </div>
  </main>
  <script>
    const state = { shipments: [], selected: null, page: 1, lastPage: 1 };
    const labels = { draft:'下書き', confirmed:'出荷済み', cancelled:'取消済み' };
    const isoDate = date => `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
    const addMonths = (date, months) => {
      const copy = new Date(date);
      const day = copy.getDate();
      copy.setMonth(copy.getMonth() + months);
      if (copy.getDate() !== day) copy.setDate(0);
      return copy;
    };
    const monthStart = value => value ? `${value}-01` : '';
    const monthEnd = value => {
      if(!value) return '';
      const [year, month] = value.split('-').map(Number);
      return isoDate(new Date(year, month, 0));
    };
    const setDefaultDateFilters = () => {
      const today = new Date();
      document.querySelector('#document-date-from').value = isoDate(addMonths(today, -1));
      document.querySelector('#document-date-to').value = isoDate(today);
    };
    const api = async (url) => {
      const response = await fetch(url, { credentials:'same-origin', headers:{ Accept:'application/json' } });
      const body = await response.json();
      if(!response.ok) throw new Error(body.error?.message || body.message || '検索に失敗しました');
      return body.data;
    };
    const qs = (page = 1) => {
      const params = new URLSearchParams();
      const ids = ['customer','document-number','sales-order-number','status','invoice-status','document-date-from','document-date-to','sort','direction'];
      ids.forEach(id => {
        const value = document.querySelector(`#${id}`).value.trim();
        if(value) params.set(id.replaceAll('-', '_'), value);
      });
      const billingTargetFrom = monthStart(document.querySelector('#billing-target-from').value.trim());
      const billingTargetTo = monthEnd(document.querySelector('#billing-target-to').value.trim());
      if(billingTargetFrom) params.set('billing_target_from', billingTargetFrom);
      if(billingTargetTo) params.set('billing_target_to', billingTargetTo);
      params.set('page', page);
      params.set('per_page', 50);
      return params.toString();
    };
    const cell = (text, className = '') => { const td=document.createElement('td'); td.textContent=text ?? ''; if(className) td.className=className; td.title=td.textContent; return td; };
    const badge = (text, className) => { const span=document.createElement('span'); span.className=`badge ${className}`; span.textContent=text; return span; };
    const invoiceText = shipment => shipment.invoice_created ? shipment.invoice_numbers.join(', ') : '未請求';
    const invoiceBadge = shipment => badge(invoiceText(shipment), shipment.invoice_created ? 'invoice' : 'none');
    const setSearchDirty = dirty => {
      const button = document.querySelector('#search');
      button.classList.toggle('search-attention', dirty);
      button.title = dirty ? '検索条件が変更されています。検索ボタンを押して更新してください。' : '';
    };
    async function load(page = 1){
      try{
        setSearchDirty(false);
        const data = await api(`/api/v1/shipments-history?${qs(page)}`);
        state.shipments = data.shipments || [];
        state.page = data.pagination?.current_page || 1;
        state.lastPage = data.pagination?.last_page || 1;
        state.selected = null;
        renderList();
        renderDetail(null);
        document.querySelector('#count').textContent = `${data.pagination?.total || 0}件`;
        document.querySelector('#page').textContent = `${state.page} / ${state.lastPage}`;
      }catch(error){
        document.querySelector('#list').replaceChildren();
        document.querySelector('#count').textContent = error.message;
        document.querySelector('#count').classList.add('error');
      }
    }
    function renderList(){
      document.querySelector('#count').classList.remove('error');
      document.querySelector('#list').replaceChildren(...state.shipments.map(shipment => {
        const tr=document.createElement('tr');
        tr.classList.toggle('selected', state.selected?.id === shipment.id);
        const statusCell=document.createElement('td'); statusCell.append(badge(labels[shipment.status] || shipment.status, shipment.status));
        const invoiceCell=document.createElement('td'); invoiceCell.append(invoiceBadge(shipment));
        tr.append(cell(shipment.document_number), cell(shipment.document_date), cell(shipment.customer_name, 'customer-cell'), cell(shipment.sales_order_number), statusCell, invoiceCell);
        tr.addEventListener('click',()=>{ state.selected=shipment; renderList(); renderDetail(shipment); });
        return tr;
      }));
    }
    function renderDetail(shipment){
      const box=document.querySelector('#detail');
      if(!shipment){ box.innerHTML='<p class="muted">出荷を選択してください。</p>'; return; }
      const dl=document.createElement('dl'); dl.className='detail-list';
      const item=(label,value)=>{ const dt=document.createElement('dt'); dt.textContent=label; const dd=document.createElement('dd'); dd.textContent=value || ''; dl.append(dt,dd); };
      item('出荷番号', shipment.document_number);
      item('状態', labels[shipment.status] || shipment.status);
      item('取引先', shipment.customer_name);
      item('受注番号', shipment.sales_order_number);
      item('出荷指示', shipment.shipment_instruction_number);
      item('ピッキング', shipment.source_shipment_pick_number);
      item('出荷日', shipment.document_date);
      item('請求対象日', shipment.billing_target_date);
      item('酒税移出日', shipment.liquor_tax_transfer_date);
      item('請求書', invoiceText(shipment));
      if(shipment.cancelled_reason) item('取消理由', shipment.cancelled_reason);
      const actions=document.createElement('div');
      actions.className='actions left-actions';
      const slip=document.createElement('a');
      slip.href=`/shipments/${shipment.id}/print`;
      slip.target='_blank';
      slip.rel='noopener';
      slip.textContent='出荷伝票を見る';
      slip.style.cssText='display:inline-flex;align-items:center;border:1px solid #cad6e6;border-radius:4px;padding:7px 9px;text-decoration:none;color:#25344a;font-size:11px';
      actions.append(slip);
      const table=document.createElement('table'); table.className='mini-table';
      table.innerHTML='<thead><tr><th>商品</th><th>容量</th><th>数量</th><th>単位</th></tr></thead>';
      const tbody=document.createElement('tbody');
      tbody.replaceChildren(...(shipment.lines || []).map(line => {
        const tr=document.createElement('tr');
        const capacity = line.capacity_value == null ? '—' : `${Number(line.capacity_value).toLocaleString('ja-JP')} ${line.capacity_unit_name || ''}`.trim();
        tr.append(cell(line.product_name), cell(capacity, 'num'), cell(String(Number(line.confirmed_quantity || line.quantity || 0)).replace(/\\.0$/,''),'num'), cell(line.unit_name || line.unit_code));
        return tr;
      }));
      table.append(tbody);
      box.replaceChildren(dl, actions, table);
    }
    function exportCsv(){
      const headers = ['出荷番号','出荷日','取引先','受注番号','状態','請求対象日','請求書'];
      const rows = state.shipments.map(s => [s.document_number,s.document_date,s.customer_name,s.sales_order_number,labels[s.status] || s.status,s.billing_target_date,invoiceText(s)]);
      const csv = [headers, ...rows].map(row => row.map(value => `"${String(value ?? '').replaceAll('"','""')}"`).join(',')).join('\r\n');
      const blob = new Blob(['\uFEFF' + csv], { type:'text/csv;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const a=document.createElement('a'); a.href=url; a.download='shipment-history.csv'; a.click(); URL.revokeObjectURL(url);
    }
    document.querySelector('#search').addEventListener('click',()=>load(1));
    document.querySelector('#clear').addEventListener('click',()=>{ document.querySelectorAll('.filters input').forEach(input=>input.value=''); setDefaultDateFilters(); document.querySelector('#status').value=''; document.querySelector('#invoice-status').value='all'; document.querySelector('#sort').value='document_date'; document.querySelector('#direction').value='desc'; load(1); });
    document.querySelector('#prev').addEventListener('click',()=>{ if(state.page>1) load(state.page-1); });
    document.querySelector('#next').addEventListener('click',()=>{ if(state.page<state.lastPage) load(state.page+1); });
    document.querySelector('#export-csv').addEventListener('click', exportCsv);
    document.querySelectorAll('.filters input,.filters select').forEach(input => {
      input.addEventListener('input',()=>setSearchDirty(true));
      input.addEventListener('change',()=>setSearchDirty(true));
    });
    setDefaultDateFilters();
    load(1);
  </script>
</body>
</html>
