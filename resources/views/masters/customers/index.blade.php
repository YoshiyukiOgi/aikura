<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>取引先マスター | 販売管理</title>
  <style>
    :root{font-family:Inter,"Noto Sans JP",system-ui,sans-serif;color:#172033;background:#f5f7fb;font-size:13px}
    *{box-sizing:border-box}[hidden]{display:none!important}body{margin:0}h1,h2,h3,p{margin:0}
    header{height:52px;display:flex;align-items:center;justify-content:space-between;padding:0 20px;border-bottom:1px solid #dce4ee;background:#fff}header h1{font-size:15px;font-weight:800;letter-spacing:0}
    main{padding:12px 18px 40px}.layout{display:grid;grid-template-columns:360px minmax(0,1fr);gap:12px;align-items:start}.card{min-width:0;border:1px solid #dce4ee;border-radius:6px;background:#fff;overflow:hidden}.head{display:flex;align-items:center;justify-content:space-between;gap:10px;min-height:46px;padding:9px 12px;border-bottom:1px solid #e7edf4}.head h2{font-size:14px}.actions,.filter-actions{display:flex;align-items:center;gap:7px}.muted{color:#64748b;font-size:11px;line-height:1.5}
    button,input,select,textarea{font:inherit;color:#172033}button{min-height:30px;padding:5px 9px;border:1px solid #cad6e6;border-radius:4px;background:#fff;cursor:pointer}button:hover{border-color:#8ebcff}button:disabled{opacity:.45;cursor:not-allowed}.primary{background:#0b6ff6;color:#fff;border-color:#0b6ff6;font-weight:800}.secondary{color:#075ecf;border-color:#a9caff}.danger{color:#b42318;border-color:#f0b6b1}
    label{display:grid;gap:4px;color:#475569;font-size:10px;font-weight:700}input,select,textarea{width:100%;min-height:31px;padding:5px 8px;border:1px solid #ced8e5;border-radius:4px;background:#fff}textarea{min-height:68px;resize:vertical}input[readonly]{background:#f3f6fa;color:#64748b}
    .filters{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:10px 12px;border-bottom:1px solid #e7edf4}.filters .wide{grid-column:1/-1}.customer-table{width:100%;border-collapse:collapse;table-layout:fixed}.customer-table th,.customer-table td{padding:8px;border-bottom:1px solid #edf1f6;text-align:left;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.customer-table th{background:#f8faff;color:#64748b;font-size:10px}.customer-table th:first-child,.customer-table td:first-child{width:110px}.customer-table th:last-child,.customer-table td:last-child{width:58px}.customer-table tbody tr{cursor:pointer}.customer-table tbody tr:hover{background:#e7f0ff}.customer-table tbody tr.selected{background:#d9e9ff;box-shadow:inset 3px 0 #0b6ff6}.code{font-family:"Roboto Mono","SFMono-Regular",Consolas,monospace;color:#075ecf;font-size:10px}.status{display:inline-block;padding:2px 5px;border-radius:3px;background:#e7f7ed;color:#137333;font-size:10px;font-weight:800}.status.inactive{background:#eef1f5;color:#64748b}.empty{padding:28px 12px;text-align:center;color:#64748b}.pager{display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border-top:1px solid #edf1f6}
    .detail-toolbar{display:flex;align-items:center;gap:8px;padding:8px 12px;border-bottom:1px solid #e7edf4}.detail-toolbar .message{margin-right:auto}.tabs{display:flex;gap:0;border-bottom:1px solid #dce4ee;padding:0 12px}.tab{border:0;border-bottom:2px solid transparent;border-radius:0;padding:10px 14px;color:#64748b;background:#fff}.tab.active{border-bottom-color:#0b6ff6;color:#075ecf;font-weight:800}.panel{padding:14px}.form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.form-grid .two{grid-column:span 2}.form-grid .full{grid-column:1/-1}.section-title{grid-column:1/-1;margin-top:5px;padding-top:12px;border-top:1px solid #edf1f6;font-size:12px}.section-title:first-child{margin-top:0;padding-top:0;border-top:0}.check{display:flex;align-items:center;gap:7px;min-height:31px;color:#334155;font-size:11px}.check input{width:auto;min-height:auto}.form-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-top:14px;padding-top:12px;border-top:1px solid #edf1f6}.form-actions .notice{margin-right:auto}.notice{color:#64748b;font-size:11px}.notice.error{color:#b42318}.warning{margin:0 14px 12px;padding:9px 11px;border:1px solid #f6d365;border-radius:4px;background:#fff8e1;color:#7a4b00;font-size:11px}.duplicate-list{display:grid;gap:4px;margin-top:6px}.duplicate-list button{text-align:left;background:transparent;border:0;padding:2px 0;color:#075ecf}
    .stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));border:1px solid #e2e8f0;border-radius:5px;overflow:hidden}.stat{padding:12px;border-right:1px solid #e2e8f0}.stat:last-child{border-right:0}.stat strong{display:block;margin-top:3px;font-size:17px}.history{width:100%;border-collapse:collapse;margin-top:14px}.history th,.history td{padding:8px;border-bottom:1px solid #edf1f6;text-align:left;font-size:11px}.history th{background:#f8faff;color:#64748b;font-size:10px}.legacy{display:grid;grid-template-columns:1fr 2fr;gap:8px;margin-top:14px;padding:10px;border:1px solid #e2e8f0;border-radius:5px;background:#f8fafc}.legacy dt{color:#64748b;font-size:10px}.legacy dd{margin:2px 0 8px;font-size:11px;word-break:break-all}.legacy dd:last-child{margin-bottom:0}
    @media(max-width:1120px){.layout{grid-template-columns:1fr}.list-card{max-height:480px}.form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:680px){main{padding:8px}.filters,.form-grid{grid-template-columns:1fr}.filters .wide,.form-grid .two{grid-column:auto}.stats{grid-template-columns:repeat(2,1fr)}.stat{border-bottom:1px solid #e2e8f0}.legacy{grid-template-columns:1fr}.tabs{overflow:auto}.tab{white-space:nowrap}}
  </style>
</head>
<body>
  <header><h1>取引先マスター</h1><div class="actions"><span class="muted">{{ $user->name }}</span><form method="post" action="{{ route('logout') }}">@csrf<button type="submit">ログアウト</button></form></div></header>
  <main>
    <div class="layout">
      <section class="card list-card">
        <div class="head"><div><h2>取引先一覧</h2><p id="result-count" class="muted"></p></div>@if($canEdit)<button id="new-customer" class="secondary" type="button">新規登録</button>@endif</div>
        <form id="filters" class="filters">
          <label class="wide">検索<input id="filter-q" autocomplete="off" placeholder="コード・名称・電話番号・住所"></label>
          <label>状態<select id="filter-active"><option value="active">有効</option><option value="inactive">休止</option><option value="all">すべて</option></select></label>
          <label>価格区分<select id="filter-transaction"><option value="">すべて</option>@foreach($transactionCategories as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
          <label class="wide">決済・売掛区分<select id="filter-settlement"><option value="">すべて</option>@foreach($settlementCategories as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
          <div class="filter-actions wide"><button id="reset-filter" type="button">条件をクリア</button><button class="primary" type="submit">検索</button></div>
        </form>
        <table class="customer-table"><thead><tr><th>コード</th><th>取引先</th><th>状態</th></tr></thead><tbody id="customer-list"></tbody></table>
        <div id="empty-list" class="empty" hidden>該当する取引先はありません。</div>
        <div class="pager"><button id="prev-page" type="button">前へ</button><span id="page-info" class="muted"></span><button id="next-page" type="button">次へ</button></div>
      </section>

      <section class="card detail-card">
        <div class="head"><div><h2 id="detail-title">取引先を選択</h2><p id="detail-subtitle" class="muted">一覧から取引先を選択してください。</p></div></div>
        <div class="detail-toolbar"><span id="detail-message" class="message muted"></span><button id="reload-customer" type="button" disabled>再読込</button></div>
        <div id="detail-empty" class="empty">取引先の基本情報、請求条件、変更履歴を確認できます。</div>
        <form id="customer-form" hidden>
          <div class="tabs" role="tablist">
            <button class="tab active" type="button" data-tab="basic">基本情報</button>
            <button class="tab" type="button" data-tab="billing">請求・税設定</button>
            <button class="tab" type="button" data-tab="history">利用状況・履歴</button>
          </div>
          <div class="panel" data-panel="basic">
            <div class="form-grid">
              <h3 class="section-title">名称</h3>
              <label>取引先コード<input id="customer-code" maxlength="80" required></label>
              <label class="two">正式名称<input id="name" maxlength="160" required></label>
              <label>略称<input id="short-name" maxlength="120"></label>
              <label class="two">名称カナ<input id="name-kana" maxlength="160"></label>
              <label class="two">請求書宛名<input id="billing-name" maxlength="160"></label>
              <h3 class="section-title">所在地・連絡先</h3>
              <label>郵便番号<input id="postal-code" maxlength="20" inputmode="numeric"></label>
              <label class="two">住所<input id="address1" maxlength="255"></label>
              <label>建物名等<input id="address2" maxlength="255"></label>
              <label>電話番号<input id="phone" maxlength="50" inputmode="tel"></label>
              <label>FAX<input id="fax" maxlength="50" inputmode="tel"></label>
              <label>メール<input id="email" maxlength="255" inputmode="email"></label>
              <label>担当者<input id="contact-name" maxlength="120"></label>
              <h3 class="section-title">管理</h3>
              <label class="full">備考<textarea id="note" maxlength="5000"></textarea></label>
              <label class="check full"><input id="is-active" type="checkbox">有効な取引先として使用する</label>
            </div>
          </div>
          <div class="panel" data-panel="billing" hidden>
            <div class="form-grid">
              <h3 class="section-title">取引・請求条件</h3>
              <label>価格区分<select id="transaction-category" required>@foreach($transactionCategories as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
              <label>決済・売掛区分<select id="settlement-category" required>@foreach($settlementCategories as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
              <label class="two">締め・入金条件<select id="billing-cycle" required>@foreach($billingCycles as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
              <label class="check full"><input id="invoice-required" type="checkbox">請求書を発行する</label>
              <h3 class="section-title">消費税・端数処理</h3>
              <label>消費税計算単位<select id="tax-calculation-unit"><option value="invoice">請求書合計</option><option value="line">明細単位</option></select></label>
              <label>消費税端数<select id="tax-rounding-method"><option value="ceil">切り上げ</option><option value="round">四捨五入</option><option value="floor">切り捨て</option></select></label>
              <label>金額端数<select id="amount-rounding-method"><option value="round">四捨五入</option><option value="ceil">切り上げ</option><option value="floor">切り捨て</option></select></label>
            </div>
          </div>
          <div class="panel" data-panel="history" hidden>
            <div class="stats">
              <div class="stat"><span class="muted">受注</span><strong id="count-orders">0</strong></div><div class="stat"><span class="muted">出荷</span><strong id="count-shipments">0</strong></div><div class="stat"><span class="muted">請求</span><strong id="count-invoices">0</strong></div><div class="stat"><span class="muted">入金</span><strong id="count-payments">0</strong></div><div class="stat"><span class="muted">個別価格</span><strong id="count-prices">0</strong></div>
            </div>
            <dl class="legacy"><div><dt>Access旧コード</dt><dd id="legacy-code">-</dd></div><div><dt>Access旧名称</dt><dd id="legacy-name">-</dd></div></dl>
            <h3 class="section-title" style="margin-top:14px">最近の変更</h3>
            <table class="history"><thead><tr><th>日時</th><th>担当者</th><th>内容</th><th>理由</th></tr></thead><tbody id="history-list"></tbody></table>
            <div id="history-empty" class="empty" hidden>この画面での変更履歴はまだありません。</div>
          </div>
          <div id="duplicate-warning" class="warning" hidden><strong>重複候補があります</strong><div id="duplicate-list" class="duplicate-list"></div></div>
          <div class="form-actions"><span id="form-message" class="notice"></span><label id="reason-label" style="width:min(360px,45%)">変更理由<input id="change-reason" maxlength="1000" placeholder="例：電話番号変更"></label>@if($canEdit)<button id="save-customer" class="primary" type="submit">保存</button>@endif</div>
        </form>
      </section>
    </div>
  </main>
  <script>
    const canEdit = @json($canEdit);
    const state = { page:1, lastPage:1, selectedId:null, selected:null, isNew:false, dirty:false, loading:false };
    const el = id => document.getElementById(id);
    const fields = ['name','short-name','name-kana','billing-name','postal-code','address1','address2','phone','fax','email','contact-name','note','transaction-category','settlement-category','billing-cycle','tax-calculation-unit','tax-rounding-method','amount-rounding-method'];
    const labels = {name:'正式名称',transaction_category_id:'価格区分',settlement_receivable_category_id:'決済・売掛区分',billing_cycle_id:'締め・入金条件',change_reason:'変更理由',email:'メール'};
    const api = async (url, options={}) => {
      const response = await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('input[name="_token"]')?.value || ''},...options});
      const payload = await response.json().catch(()=>({}));
      if(!response.ok){const errors=payload.errors?Object.entries(payload.errors).map(([key,value])=>`${labels[key]||key}: ${value[0]}`).join(' / '):null;throw new Error(errors||payload.message||'処理に失敗しました。');}
      return payload.data;
    };
    const query = () => { const p=new URLSearchParams({page:String(state.page),per_page:'40',active:el('filter-active').value}); const q=el('filter-q').value.trim(); if(q)p.set('q',q); if(el('filter-transaction').value)p.set('transaction_category_id',el('filter-transaction').value); if(el('filter-settlement').value)p.set('settlement_receivable_category_id',el('filter-settlement').value); return p; };
    const setMessage=(node,text,error=false)=>{node.textContent=text;node.classList.toggle('error',error)};
    const confirmDiscard=()=>!state.dirty||window.confirm('保存していない変更があります。破棄してよいですか？');
    async function loadList(){state.loading=true;setMessage(el('detail-message'),'取引先一覧を読み込んでいます。');try{const data=await api(`/api/v1/masters/customers?${query()}`);state.lastPage=data.pagination.last_page;renderList(data.customers,data.pagination);setMessage(el('detail-message'),'');}catch(error){setMessage(el('detail-message'),error.message,true)}finally{state.loading=false}}
    function renderList(customers,pagination){const body=el('customer-list');body.innerHTML='';customers.forEach(customer=>{const row=document.createElement('tr');row.dataset.id=customer.id;row.classList.toggle('selected',String(customer.id)===String(state.selectedId));row.innerHTML=`<td class="code"></td><td><strong></strong><div class="muted"></div></td><td><span class="status"></span></td>`;row.children[0].textContent=customer.customer_code;row.querySelector('strong').textContent=customer.short_name||customer.name;row.querySelector('.muted').textContent=customer.transaction_category_name||'';const badge=row.querySelector('.status');badge.textContent=customer.is_active?'有効':'休止';badge.classList.toggle('inactive',!customer.is_active);row.onclick=()=>selectCustomer(customer.id);body.appendChild(row)});el('empty-list').hidden=customers.length>0;el('result-count').textContent=`${pagination.total.toLocaleString()}件`;el('page-info').textContent=`${pagination.current_page} / ${Math.max(1,pagination.last_page)}ページ`;el('prev-page').disabled=pagination.current_page<=1;el('next-page').disabled=pagination.current_page>=pagination.last_page;}
    async function selectCustomer(id){if(String(id)===String(state.selectedId)&&!state.isNew)return;if(!confirmDiscard())return;state.selectedId=id;state.isNew=false;state.dirty=false;await loadCustomer(id);await loadList();}
    async function loadCustomer(id){setMessage(el('detail-message'),'読み込んでいます。');try{const data=await api(`/api/v1/masters/customers/${id}`);state.selected=data.customer;fillForm(data.customer);showForm();setMessage(el('detail-message'),'');}catch(error){setMessage(el('detail-message'),error.message,true)}}
    function value(id,value=''){el(id).value=value??''}
    function fillForm(c){value('customer-code',c.customer_code);el('customer-code').readOnly=true;value('name',c.name);value('short-name',c.short_name);value('name-kana',c.name_kana);value('billing-name',c.billing_name);value('postal-code',c.postal_code);value('address1',c.address1);value('address2',c.address2);value('phone',c.phone);value('fax',c.fax);value('email',c.email);value('contact-name',c.contact_name);value('note',c.note);value('transaction-category',c.transaction_category_id);value('settlement-category',c.settlement_receivable_category_id);value('billing-cycle',c.billing_cycle_id);value('tax-calculation-unit',c.tax_calculation_unit);value('tax-rounding-method',c.tax_rounding_method);value('amount-rounding-method',c.amount_rounding_method);el('invoice-required').checked=!!c.invoice_required;el('is-active').checked=!!c.is_active;value('change-reason','');el('detail-title').textContent=c.name;el('detail-subtitle').textContent=`${c.customer_code} / ${c.settlement_receivable_category_name||''} / ${c.billing_cycle_name||''}`;el('reason-label').hidden=!canEdit;renderHistory(c);renderDuplicates(c.duplicate_candidates||[]);setDisabled(!canEdit);state.dirty=false;}
    function showForm(){el('detail-empty').hidden=true;el('customer-form').hidden=false;el('reload-customer').disabled=false;}
    function newCustomer(){if(!confirmDiscard())return;state.selectedId=null;state.selected=null;state.isNew=true;state.dirty=false;el('customer-form').reset();el('customer-code').readOnly=false;el('tax-calculation-unit').value='invoice';el('tax-rounding-method').value='ceil';el('amount-rounding-method').value='round';el('invoice-required').checked=true;el('is-active').checked=true;el('detail-title').textContent='新規取引先';el('detail-subtitle').textContent='取引先コードは登録後に変更できません。';el('reason-label').hidden=true;renderHistory({usage_counts:{},history:[],legacy_code:null,legacy_name:null});renderDuplicates([]);showForm();setDisabled(!canEdit);activateTab('basic');}
    function setDisabled(disabled){el('customer-form').querySelectorAll('input,select,textarea').forEach(input=>{if(input.id==='customer-code'&&!state.isNew){input.readOnly=true;return}input.disabled=disabled});if(el('save-customer'))el('save-customer').hidden=disabled;}
    function payload(){return {customer_code:el('customer-code').value.trim(),name:el('name').value.trim(),short_name:el('short-name').value.trim()||null,name_kana:el('name-kana').value.trim()||null,billing_name:el('billing-name').value.trim()||null,postal_code:el('postal-code').value.trim()||null,address1:el('address1').value.trim()||null,address2:el('address2').value.trim()||null,phone:el('phone').value.trim()||null,fax:el('fax').value.trim()||null,email:el('email').value.trim()||null,contact_name:el('contact-name').value.trim()||null,transaction_category_id:Number(el('transaction-category').value),settlement_receivable_category_id:Number(el('settlement-category').value),billing_cycle_id:Number(el('billing-cycle').value),tax_calculation_unit:el('tax-calculation-unit').value,tax_rounding_method:el('tax-rounding-method').value,amount_rounding_method:el('amount-rounding-method').value,invoice_required:el('invoice-required').checked,is_active:el('is-active').checked,note:el('note').value.trim()||null,change_reason:el('change-reason').value.trim()||null};}
    async function saveCustomer(event){event.preventDefault();if(!canEdit)return;const body=payload();if(!state.isNew&&!body.change_reason){setMessage(el('form-message'),'変更理由を入力してください。',true);return}setMessage(el('form-message'),'保存しています。');try{const data=await api(state.isNew?'/api/v1/masters/customers':`/api/v1/masters/customers/${state.selectedId}`,{method:state.isNew?'POST':'PUT',body:JSON.stringify(body)});state.selectedId=data.customer.id;state.isNew=false;state.dirty=false;await loadCustomer(state.selectedId);await loadList();setMessage(el('form-message'),'保存しました。');}catch(error){setMessage(el('form-message'),error.message,true)}}
    function renderHistory(c){const counts=c.usage_counts||{};el('count-orders').textContent=(counts.sales_orders||0).toLocaleString();el('count-shipments').textContent=(counts.shipments||0).toLocaleString();el('count-invoices').textContent=(counts.invoices||0).toLocaleString();el('count-payments').textContent=(counts.payments||0).toLocaleString();el('count-prices').textContent=(counts.price_rules||0).toLocaleString();el('legacy-code').textContent=c.legacy_code||'-';el('legacy-name').textContent=c.legacy_name||'-';const body=el('history-list');body.innerHTML='';(c.history||[]).forEach(item=>{const row=document.createElement('tr');['occurred_at','user_name','event','reason'].forEach(key=>{const cell=document.createElement('td');cell.textContent=key==='occurred_at'&&item[key]?new Date(item[key]).toLocaleString('ja-JP'):item[key]||'-';row.appendChild(cell)});body.appendChild(row)});el('history-empty').hidden=(c.history||[]).length>0;}
    function renderDuplicates(items){const box=el('duplicate-list');box.innerHTML='';items.forEach(item=>{const button=document.createElement('button');button.type='button';button.textContent=`${item.customer_code} ${item.name}（${item.matches.map(key=>({name:'名称',phone:'電話',address1:'住所'}[key]||key)).join('・')}一致）`;button.onclick=()=>selectCustomer(item.id);box.appendChild(button)});el('duplicate-warning').hidden=items.length===0;}
    function activateTab(name){document.querySelectorAll('.tab').forEach(tab=>tab.classList.toggle('active',tab.dataset.tab===name));document.querySelectorAll('[data-panel]').forEach(panel=>panel.hidden=panel.dataset.panel!==name);}
    el('filters').onsubmit=event=>{event.preventDefault();state.page=1;loadList()};el('reset-filter').onclick=()=>{el('filters').reset();state.page=1;loadList()};el('prev-page').onclick=()=>{if(state.page>1){state.page--;loadList()}};el('next-page').onclick=()=>{if(state.page<state.lastPage){state.page++;loadList()}};el('reload-customer').onclick=()=>state.selectedId&&loadCustomer(state.selectedId);el('new-customer')?.addEventListener('click',newCustomer);el('customer-form').onsubmit=saveCustomer;document.querySelectorAll('.tab').forEach(tab=>tab.onclick=()=>activateTab(tab.dataset.tab));el('customer-form').addEventListener('input',()=>{state.dirty=true});el('customer-form').addEventListener('change',()=>{state.dirty=true});window.addEventListener('beforeunload',event=>{if(state.dirty){event.preventDefault();event.returnValue=''}});loadList();
  </script>
</body>
</html>
