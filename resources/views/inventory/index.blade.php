<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>在庫業務 | 販売管理</title>
  <style>
    :root{font-family:Inter,"Noto Sans JP",system-ui,sans-serif;color:#172033;background:#f5f7fb;font-size:13px}*{box-sizing:border-box}body{margin:0}header{height:52px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;background:#fff;border-bottom:1px solid #dce4ee}h1,h2,h3,p{margin:0}h1{font-size:15px}main{padding:12px 18px}.tabs{display:flex;gap:2px;border-bottom:1px solid #cfd9e7}.tab{border:0;border-bottom:3px solid transparent;background:transparent;padding:10px 14px;font-weight:700;color:#526176}.tab.active{border-bottom-color:#0b6ff6;color:#075ecf}.panel{display:none;padding-top:12px}.panel.active{display:block}.toolbar label,.field{display:grid;gap:4px;color:#64748b;font-size:10px}.field[hidden]{display:none!important}.toolbar input,.toolbar select,.field input,.field select,.field textarea,td input,td select{min-height:31px;border:1px solid #cad6e6;border-radius:4px;padding:5px 7px;background:#fff;font:inherit}.toolbar{display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:10px}.card{background:#fff;border:1px solid #dce4ee;border-radius:6px;overflow:hidden}.card-head{padding:10px 12px;border-bottom:1px solid #e7edf4;display:flex;align-items:center;justify-content:space-between;gap:8px}.card-head h2{font-size:13px}.table-wrap{overflow:auto;max-height:calc(100vh - 210px)}table{width:100%;border-collapse:collapse;white-space:nowrap}th,td{padding:6px 7px;border-bottom:1px solid #edf1f6;text-align:left;font-size:11px}th{position:sticky;top:0;z-index:1;background:#f8faff;color:#64748b;font-size:10px}td.num,th.num{text-align:right;font-variant-numeric:tabular-nums}.num-compact{min-width:54px;max-width:74px}.primary,button{border:1px solid #cad6e6;border-radius:4px;background:#fff;color:#25344a;padding:7px 10px;font:inherit;font-size:11px;cursor:pointer}.primary{background:#0b6ff6;color:#fff;border-color:#0b6ff6;font-weight:800}@keyframes searchPulse{0%,100%{background:#fff7d6;border-color:#f0b429;box-shadow:0 0 0 0 rgba(240,180,41,.28)}50%{background:#ffe08a;border-color:#d89b00;box-shadow:0 0 0 5px rgba(240,180,41,.12)}}button.search-attention{animation:searchPulse 1.8s ease-in-out infinite;color:#172033;font-weight:800}.danger{border-color:#e6a7a1;color:#b42318}.muted{color:#64748b;font-size:11px}.message{min-height:18px;margin:8px 0;color:#137333}.message.error{color:#b42318}.status{display:inline-block;padding:2px 6px;border-radius:3px;background:#eaf3ff;color:#075ecf;font-size:10px;font-weight:800}.status.confirmed,.status.closed{background:#e7f7ed;color:#137333}.status.cancelled{background:#fee4e2;color:#b42318}.variance{color:#b42318;font-weight:800}.summary{display:flex;gap:14px;align-items:center}.split{display:grid;grid-template-columns:minmax(360px,1fr) minmax(420px,1.35fr);gap:12px}.form-grid{display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:8px;padding:12px}.form-grid .wide{grid-column:span 2}.form-grid .full{grid-column:1/-1}.operation-workspace{display:grid;grid-template-columns:minmax(620px,1.45fr) minmax(360px,.9fr);gap:12px}.operation-entry{min-height:calc(100vh - 150px)}.operation-actions{display:flex;gap:7px;align-items:center;flex-wrap:wrap}.operation-form{display:grid;grid-template-columns:170px 140px 140px 140px minmax(260px,1fr);gap:9px;padding:12px;border-bottom:1px solid #edf1f6;background:#fbfcff}.operation-form .reason{grid-column:auto}.operation-section-head{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 12px;border-bottom:1px solid #edf1f6}.operation-section-head h3{font-size:12px}.operation-lines-wrap{max-height:calc(100vh - 340px)}.operation-lines th:nth-child(1){min-width:240px}.operation-lines th:nth-child(2){min-width:150px}.operation-lines th:nth-child(3){min-width:150px}.operation-lines th:nth-child(4){width:76px}.operation-lines td select{width:100%;min-width:0}.operation-lines td input,td input[type=number]{width:68px}.operation-lines .remove-line{padding:5px 8px}.operation-message{padding:0 12px 10px}.history-wrap{max-height:calc(100vh - 190px)}.lot-analysis-date{width:118px}.lot-analysis-status{width:76px}.actions{display:flex;gap:7px;align-items:center}.empty{padding:32px;text-align:center;color:#64748b}@media(max-width:1200px){.operation-workspace{grid-template-columns:1fr}.operation-entry{min-height:0}.operation-lines-wrap,.history-wrap{max-height:420px}.operation-form{grid-template-columns:repeat(4,minmax(120px,1fr))}.operation-form .reason{grid-column:1/-1}}@media(max-width:1100px){.split{grid-template-columns:1fr}.form-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:700px){main{padding:8px}.tabs{overflow:auto}.form-grid,.operation-form{grid-template-columns:1fr}.form-grid .wide{grid-column:auto}.operation-form .reason{grid-column:auto}.operation-actions{width:100%}.operation-actions button{flex:1}}
    .operation-layout{display:grid;grid-template-columns:390px minmax(560px,1fr);gap:12px;align-items:start}.operation-list-card,.operation-detail-column{min-width:0}.operation-detail-column{display:grid;gap:8px}.operation-list-note{padding:9px 12px;border-bottom:1px solid #e7edf4;background:#f8faff;color:#526176;font-size:11px}.operation-list{table-layout:fixed}.operation-list th,.operation-list td{overflow:hidden;text-overflow:ellipsis}.operation-list th:nth-child(1){width:124px}.operation-list th:nth-child(2){width:78px}.operation-list th:nth-child(4){width:48px}.operation-list tbody tr{cursor:pointer}.operation-list tbody tr:hover{background:#e7f0ff}.operation-list tbody tr.selected{background:#d9e9ff;box-shadow:inset 3px 0 0 #0b6ff6}.operation-detail-toolbar{padding:8px 12px;justify-content:flex-end}.operation-detail-toolbar .message{margin:0 auto 0 0}.operation-detail{min-height:360px}.operation-detail[hidden]{display:none}.operation-form{grid-template-columns:180px 150px minmax(260px,1fr)}.operation-lines-wrap{max-height:calc(100vh - 390px)}.operation-lines{table-layout:fixed}.operation-lines th:nth-child(1){width:auto}.operation-lines th:nth-child(2){width:145px}.operation-lines th:nth-child(3){width:90px}.operation-lines th:nth-child(4){width:68px}.operation-lines td select{width:100%}.operation-lines td input{width:72px}.op-location-text{display:block;color:#64748b;font-size:10px;margin-top:2px}.operation-detail-actions{display:flex;gap:7px;align-items:center;justify-content:flex-end;padding:10px 12px}.operation-detail-actions .danger{margin-right:auto}.period-lock{color:#9a6700;background:#fff4dd}.operation-number{color:#075ecf;font-weight:800;font-family:Consolas,monospace}.operation-empty{padding:42px 16px;text-align:center;color:#64748b}.revision-wrap{border-top:1px solid #e7edf4}.revision-table{table-layout:fixed}.revision-table th:nth-child(1){width:56px}.revision-table th:nth-child(2){width:72px}.revision-table th:nth-child(3){width:90px}.revision-table th:nth-child(5){width:110px}.operation-confirm-dialog{width:min(620px,calc(100vw - 32px));border:0;border-radius:8px;padding:0;box-shadow:0 24px 70px rgba(15,23,42,.28)}.operation-confirm-dialog::backdrop{background:rgba(15,23,42,.4)}.confirm-body{display:grid;gap:12px;padding:14px}.impact-grid{display:grid;grid-template-columns:1fr 1fr;border:1px solid #dce4ee;border-radius:6px;overflow:hidden}.impact-grid>div{padding:12px}.impact-grid>div+div{border-left:1px solid #dce4ee}.impact-grid strong{display:block;margin-bottom:5px}.confirm-impact{padding:10px;border-left:3px solid #0b6ff6;background:#eef5ff;color:#25405f}.confirm-actions{display:flex;justify-content:flex-end;gap:7px}.confirm-reason{display:grid;gap:4px}.version-badge{margin-left:7px}@media(max-width:1100px){.operation-layout{grid-template-columns:1fr}.operation-lines-wrap{max-height:420px}}@media(max-width:700px){.operation-form{grid-template-columns:1fr}.operation-detail-actions{flex-wrap:wrap}.operation-detail-actions button{flex:1}.impact-grid{grid-template-columns:1fr}.impact-grid>div+div{border-left:0;border-top:1px solid #dce4ee}}
    .alcohol-warning{padding:11px 12px;border:1px solid #e6a13a;border-left:4px solid #c66a00;border-radius:5px;background:#fff8e6;color:#6f3b00}.alcohol-warning strong{display:block;margin-bottom:5px}.alcohol-warning ul{margin:5px 0 0;padding-left:20px}.alcohol-warning li+li{margin-top:4px}.alcohol-warning[hidden],.confirm-reason[hidden]{display:none!important}
    .toolbar .check-field{display:flex;align-items:center;gap:6px;min-height:31px;font-size:11px;color:#475569}.toolbar .check-field input{width:auto;min-height:auto}.negative-stock{background:#fff1f0}.negative-stock td.num{color:#b42318;font-weight:800}
    .count-toolbar{display:flex;gap:8px;align-items:end;flex-wrap:wrap}.count-toolbar .count-confirm{margin-left:auto}
    @media(max-width:700px){.count-toolbar .count-confirm{margin-left:0}}
    #panel-stock table{table-layout:fixed;min-width:1120px}#panel-stock th:nth-child(1){width:260px}#panel-stock td:nth-child(1){max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}#panel-stock td:nth-child(1) strong{display:inline-block;max-width:100%;overflow:hidden;text-overflow:ellipsis;vertical-align:bottom}
  </style>
</head>
<body>
<header><h1>在庫業務</h1><span class="muted">{{ $user->name }}</span></header>
<main>
  <nav class="tabs" aria-label="在庫業務"><button class="tab active" data-tab="stock">現在庫</button><button class="tab" data-tab="non-sales">販売外出入</button><button class="tab" data-tab="movements">移動履歴</button><button class="tab" data-tab="count">月次棚卸</button><button class="tab" data-tab="closing">月次締め</button></nav>

  <section id="panel-stock" class="panel active"><div class="toolbar"><label>基準日<input id="stock-as-of-date" type="date" value="{{ $today }}"></label><label>ロット検索<input id="stock-search" type="search"></label><label class="check-field"><input id="stock-show-zero" type="checkbox" @checked($showZeroStockLotsDefault)>在庫0を表示</label><button id="refresh-stock">更新</button><button id="print-stock">印刷</button></div><div class="card"><div class="card-head"><h2>ロット現在庫</h2><span id="stock-count" class="muted"></span></div><div class="table-wrap"><table><thead><tr><th>ロット</th><th>容量</th><th>在庫場所</th><th>実測度数</th><th>日本酒度</th><th>酸度</th><th>アミノ酸度</th><th class="num">実在庫</th><th class="num">引当</th><th class="num">利用可能</th></tr></thead><tbody id="stock-body"></tbody></table></div></div><p id="stock-message" class="message"></p></section>

  <section id="panel-count" class="panel"><div class="toolbar count-toolbar"><label>対象年<input id="count-year" type="number" min="2000" max="2100"></label><label>対象月<input id="count-month" type="number" min="1" max="12"></label><label>表示<select id="count-filter"><option value="all">すべて</option><option value="uncounted">未入力</option><option value="variance">差異あり</option></select></label>@if($canAdjust)<button id="create-count">棚卸を準備</button><button id="save-count">入力保存</button><button id="confirm-count" class="primary count-confirm">棚卸確定</button>@endif</div><p id="count-message" class="message"></p><div class="card"><div class="card-head"><h2 id="count-title">月次棚卸</h2><div id="count-summary" class="summary muted"></div></div><div class="table-wrap"><table><thead><tr><th>ロット</th><th>容量</th><th>在庫場所</th><th class="num">帳簿数</th><th class="num">実地数</th><th class="num">差異</th><th>差異理由</th></tr></thead><tbody id="count-body"><tr><td colspan="7" class="empty">対象年月を指定してください。</td></tr></tbody></table></div></div></section>

  <section id="panel-closing" class="panel"><div class="toolbar"><label>対象年<input id="close-year" type="number" min="2000" max="2100"></label><label>対象月<input id="close-month" type="number" min="1" max="12"></label><label>処理理由<input id="close-reason" value="月次在庫締め"></label><button id="load-close">照会</button>@if($canClose)<button id="draft-close">残高再計算</button><button id="confirm-close" class="primary">月次確定</button>@endif</div><p id="close-message" class="message"></p><div class="card"><div class="card-head"><h2>ロット月次在庫残高</h2><span id="close-summary" class="muted"></span></div><div class="table-wrap"><table><thead><tr><th>状態</th><th>ロット</th><th>容量</th><th>在庫場所</th><th>単位</th><th class="num">期末在庫</th></tr></thead><tbody id="close-body"></tbody></table></div></div></section>

  <section id="panel-non-sales" class="panel">
    <div class="operation-layout">
      <section class="card operation-list-card">
        <div class="card-head"><h2>販売外出入一覧</h2><span id="operation-count" class="muted"></span></div>
        <div class="operation-list-note">月次締めしていない登録だけを表示しています。</div>
        <div class="toolbar" style="padding:10px 12px 0;margin-bottom:0">
          <label>ID<input id="operation-search-id" type="search" placeholder="番号またはID"></label>
          <label>日付<input id="operation-search-date" type="date"></label>
          <label>区分<select id="operation-search-type"><option value="">すべて</option><option value="repackaging">詰替</option><option value="bottling">瓶詰</option><option value="breakage">破損</option><option value="disposal">廃棄</option><option value="loss">滅失</option><option value="return_to_manufacturing">戻入</option><option value="inspection">検査・提出</option><option value="adjustment">その他調整</option></select></label>
          <button id="search-operations" type="button">検索</button>
          <button id="clear-operation-search" type="button">クリア</button>
        </div>
        <div class="table-wrap history-wrap"><table class="operation-list"><thead><tr><th>番号</th><th>処理日</th><th>区分</th><th>版</th></tr></thead><tbody id="operations-body"></tbody></table></div>
      </section>
      <div class="operation-detail-column">
        <div class="card"><div class="actions operation-detail-toolbar"><span id="op-message" class="message"></span><button id="refresh-operations" type="button">最新に更新</button>@if($canCreateNonSales)<button id="new-operation" class="primary" type="button">新規作成</button>@endif</div></div>
        <section id="operation-detail" class="card operation-detail" hidden>
          <div class="card-head"><div><h2 id="operation-title">新規登録</h2><span id="operation-lock" class="muted"></span></div><div><span id="operation-version" class="status version-badge">第1版</span><span id="operation-state" class="status confirmed">未締め</span></div></div>
          <div class="operation-form">
            <label class="field">区分<select id="op-type"><option value="repackaging">詰替</option><option value="bottling">瓶詰</option><option value="breakage">破損</option><option value="disposal">廃棄</option><option value="loss">滅失</option><option value="return_to_manufacturing">戻入</option><option value="inspection">検査・提出</option><option value="adjustment">その他調整</option></select></label>
            <label class="field">処理日<input id="op-date" type="date"></label>
            <label id="op-reason-field" class="field reason">理由<input id="op-reason" maxlength="1000"></label>
          </div>
          <div class="operation-section-head"><h3>明細</h3><div class="actions"><span id="op-line-count" class="muted"></span>@if($canCreateNonSales)<button id="add-op-line" type="button">明細追加</button>@endif</div></div>
          <div class="table-wrap operation-lines-wrap"><table class="operation-lines"><thead><tr><th>ロット</th><th>在庫場所</th><th class="num">数量</th><th></th></tr></thead><tbody id="op-lines"></tbody></table></div>
          <div class="revision-wrap"><div class="operation-section-head"><h3>変更履歴</h3><span id="revision-count" class="muted"></span></div><div class="table-wrap"><table class="revision-table"><thead><tr><th>版</th><th>操作</th><th>処理日</th><th>理由</th><th>担当者</th></tr></thead><tbody id="revision-body"></tbody></table></div></div>
          <div class="operation-detail-actions">@if($canCreateNonSales)<button id="delete-operation" class="danger" type="button" hidden>この登録を取り消す</button><button id="reset-operation" type="button">入力を戻す</button><button id="save-operation" class="primary" type="button">登録</button>@endif</div>
        </section>
        <div id="operation-placeholder" class="card operation-empty">一覧から登録内容を選択するか、「新規作成」を押してください。</div>
      </div>
    </div>
    <dialog id="operation-confirm-dialog" class="operation-confirm-dialog"><div class="card-head"><h2 id="confirm-title">変更内容の確認</h2></div><div class="confirm-body"><div class="impact-grid"><div><strong id="confirm-before-title">変更前</strong><span id="confirm-before"></span></div><div><strong id="confirm-after-title">変更後</strong><span id="confirm-after"></span></div></div><div id="confirm-alcohol-warning" class="alcohol-warning" hidden></div><p id="confirm-impact" class="confirm-impact"></p><label id="confirm-reason-field" class="confirm-reason" hidden>取消理由<input id="confirm-reason" maxlength="1000"></label><div class="confirm-actions"><button id="confirm-back" type="button">戻る</button><button id="confirm-commit" class="primary" type="button">確定</button></div></div></dialog>
  </section>

  <section id="panel-movements" class="panel"><div class="toolbar"><label>年<select id="movement-year"></select></label><label>月<select id="movement-month"></select></label><label>区分<select id="movement-type"><option value="">すべて</option><option value="opening_stock">期首在庫</option><option value="production_receipt">製造入庫</option><option value="shipment">出荷</option><option value="shipment_cancellation">出荷取消</option><option value="inventory_adjustment">在庫調整</option><option value="transfer">在庫移動</option><option value="stock_correction">在庫移動訂正</option><option value="sales_return">売上返品</option><option value="non_sales_inspection">検査・提出</option><option value="non_sales_breakage">破損</option><option value="non_sales_disposal">廃棄</option><option value="non_sales_loss">滅失</option><option value="non_sales_return_to_manufacturing">戻入</option><option value="non_sales_bottling">瓶詰</option><option value="non_sales_repackaging">詰替</option><option value="non_sales_adjustment">その他調整</option><option value="non_sales_revision_reversal">販売外変更打消</option><option value="non_sales_cancellation">販売外取消打消</option></select></label><label>検索<input id="movement-search" placeholder="元伝票番号 / ロット"></label><button id="load-movements">検索</button><button id="print-movements">印刷</button></div><p id="movement-message" class="message"></p><div class="card"><div class="table-wrap"><table><thead><tr><th>日付</th><th>区分</th><th>ロット</th><th>場所</th><th class="num">数量</th><th>元伝票</th></tr></thead><tbody id="movements-body"></tbody></table></div></div></section>
</main>
<script>
const locations=@json($locationOptions);
const lots=@json($lotOptions);
const canAdjust=@json($canAdjust); const canClose=@json($canClose); const canCreateNonSales=@json($canCreateNonSales);
const today=new Date().toISOString().slice(0,10), now=new Date();
['count-year','close-year'].forEach(id=>document.getElementById(id).value=now.getFullYear()); ['count-month','close-month'].forEach(id=>document.getElementById(id).value=now.getMonth()+1); document.getElementById('op-date').value=today;
document.getElementById('movement-year').innerHTML=Array.from({length:7},(_,i)=>now.getFullYear()+1-i).map(year=>`<option value="${year}" ${year===now.getFullYear()?'selected':''}>${year}年</option>`).join('');
document.getElementById('movement-month').innerHTML=Array.from({length:12},(_,i)=>`<option value="${i+1}" ${i===now.getMonth()?'selected':''}>${i+1}月</option>`).join('');
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const num=v=>{if(v===null||v===undefined||v==='')return null;const n=Number(v);return Number.isFinite(n)?n:null};
const fixed=(v,d=1)=>{const n=num(v);return n===null?'-':n.toFixed(d)};
const qty=v=>{const n=num(v);return n===null?'-':Math.round(n).toLocaleString('ja-JP')};
const wholeNumber=v=>{const n=num(v);return n===null?'-':Math.round(n).toLocaleString('ja-JP')};
const alcohol=v=>{const n=num(v);return n===null?'-':`${n.toFixed(1)}％`};
const sakeMeter=v=>{const n=num(v);if(n===null)return'-';const rounded=Math.round(n);return rounded===0?'±0':`${rounded>0?'+':''}${rounded}`};
const decimalInput=v=>{const n=num(v);return n===null?'':n.toFixed(1)};
const integerInput=v=>{const n=num(v);return n===null?'':String(Math.round(n))};
const api=async(url,opt={})=>{const r=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json',...(opt.headers||{})},...opt});const p=await r.json().catch(()=>({}));if(!r.ok)throw new Error(p.message||p.error?.message||Object.values(p.errors||{}).flat()[0]||`HTTP ${r.status}`);return p.data};
const msg=(id,text,error=false)=>{const el=document.getElementById(id);el.textContent=text;el.classList.toggle('error',error)};
const pulseButton=(id,dirty=true)=>document.getElementById(id)?.classList.toggle('search-attention',dirty);
const normalizeSearchText=value=>String(value??'').normalize('NFKC').toLowerCase().replace(/[\s\u3000]+/g,'');
const normalizeSearchTerms=value=>String(value??'').normalize('NFKC').toLowerCase().trim().split(/[\s\u3000]+/).map(x=>x.replace(/[\s\u3000]+/g,'')).filter(Boolean);
const matchesSearchText=(haystack,query)=>{const terms=normalizeSearchTerms(query);if(!terms.length)return true;const normalizedHaystack=normalizeSearchText(haystack);return terms.every(term=>normalizedHaystack.includes(term));};
const productTypeLabel=t=>({sake:'酒',kasu:'酒粕',food:'食品',goods:'グッズ・その他'}[t]||'-');
const locationMap=new Map(locations.map(x=>[String(x.id),x]));
const optionHtml=(rows,label,value='id')=>rows.map(x=>`<option value="${x[value]}">${esc(label(x))}</option>`).join('');
const lotDisplay=(name,code)=>{const primary=name||code||'-',secondary=name&&code&&name!==code?`<br><span class="muted">コード: ${esc(code)}</span>`:'';return `<strong>${esc(primary)}</strong>${secondary}`};
const lotHover=(name,code)=>esc(name&&code&&name!==code?`${name}（コード: ${code}）`:name||code||'-');
const lotOptionLabel=(name,code)=>name&&code&&name!==code?`${name}（${code}）`:name||code||'-';
const operationTypeLabels={inspection:'検査・提出',breakage:'破損',disposal:'廃棄',loss:'滅失',return_to_manufacturing:'戻入',bottling:'瓶詰',repackaging:'詰替',adjustment:'その他調整',self_consumption:'自家消費',gift:'贈答',sample:'見本'};
const operationTypeLabel=type=>operationTypeLabels[type]||type||'-';
const movementTypeLabels={opening_stock:'期首在庫',production_receipt:'製造入庫',shipment:'出荷',shipment_cancellation:'出荷取消',inventory_adjustment:'在庫調整',transfer:'在庫移動',stock_correction:'在庫移動訂正',sales_return:'売上返品',non_sales_revision_reversal:'販売外変更打消',non_sales_cancellation:'販売外取消打消'};
const movementTypeLabel=type=>movementTypeLabels[type]||(type?.startsWith('non_sales_')?operationTypeLabel(type.slice(10)):type||'-');
const operationLots=lots.filter(x=>x.unit_id&&x.stock_location_id);
const stockTypeFilter=document.createElement('label');
stockTypeFilter.innerHTML='区分<select id="stock-product-type"><option value="">すべて</option><option value="sake" selected>酒</option><option value="kasu">酒粕</option><option value="food">食品</option><option value="goods">グッズ・その他</option></select>';
document.getElementById('stock-search')?.closest('label')?.after(stockTypeFilter);
document.querySelector('#stock-body')?.closest('table')?.querySelector('thead tr th:first-child')?.insertAdjacentHTML('afterend','<th>区分</th>');
function activateInventoryTab(tab){
  const nextTab=['stock','non-sales','movements','count','closing'].includes(tab)?tab:'stock';
  document.querySelectorAll('.tab,.panel').forEach(x=>x.classList.remove('active'));
  document.querySelector(`.tab[data-tab="${nextTab}"]`)?.classList.add('active');
  document.getElementById(`panel-${nextTab}`)?.classList.add('active');
}
document.querySelectorAll('.tab').forEach(b=>b.onclick=()=>{const tab=b.dataset.tab;activateInventoryTab(tab);if(window.location.hash!==`#${tab}`)window.location.hash=tab});
window.addEventListener('hashchange',()=>activateInventoryTab(window.location.hash.replace(/^#/,'')));
activateInventoryTab(window.location.hash.replace(/^#/,'' )||'stock');

let stockRows=[]; async function loadStock(){try{msg('stock-message','読込中...');const qs=new URLSearchParams({as_of_date:document.getElementById('stock-as-of-date').value});if(document.getElementById('stock-show-zero').checked)qs.set('include_zero_stock','1');const d=await api(`/api/v1/inventory/stock?${qs}`);stockRows=d.stock_balances||[];renderStock();msg('stock-message','')}catch(e){msg('stock-message',e.message,true)}}
function renderStock(){const q=normalizeSearchText(document.getElementById('stock-search').value);const rows=stockRows.filter(r=>!q||normalizeSearchText(`${r.lot_code} ${r.lot_name} ${r.product_code} ${r.product_name} ${r.capacity_value??''} ${r.capacity_unit_name??''}`).includes(q));document.getElementById('stock-count').textContent=`基準日 ${document.getElementById('stock-as-of-date').value} / ${rows.length}件`;document.getElementById('stock-body').innerHTML=rows.map(r=>{const l=locationMap.get(String(r.stock_location_id)),negative=Number(r.physical_quantity)<0||Number(r.available_quantity)<0;return `<tr class="${negative?'negative-stock':''}"><td>${lotDisplay(r.lot_name,r.lot_code)}</td><td>${esc(r.capacity_value??'-')} ${esc(r.capacity_unit_name??'')}</td><td>${esc(l?.name)}</td><td class="num num-compact">${alcohol(r.alcohol_percentage)}</td><td class="num num-compact">${sakeMeter(r.sake_meter_value)}</td><td class="num num-compact">${fixed(r.acidity)}</td><td class="num num-compact">${fixed(r.amino_acidity)}</td><td class="num num-compact">${qty(r.physical_quantity)} ${esc(r.unit_name??'')}</td><td class="num num-compact">${qty(r.allocated_quantity)}</td><td class="num num-compact">${qty(r.available_quantity)}</td></tr>`}).join('')||'<tr><td colspan="10" class="empty">在庫はありません。</td></tr>'}
function printStock(){const params=new URLSearchParams({as_of_date:document.getElementById('stock-as-of-date').value});const q=document.getElementById('stock-search').value.trim();if(q)params.set('q',q);if(document.getElementById('stock-show-zero').checked)params.set('include_zero_stock','1');window.open(`/inventory/lot-stock-as-of/print?${params}`,'_blank')}
document.getElementById('refresh-stock').onclick=loadStock;document.getElementById('stock-as-of-date').onchange=()=>pulseButton('refresh-stock');document.getElementById('stock-show-zero').onchange=()=>pulseButton('refresh-stock');document.getElementById('stock-search').oninput=()=>pulseButton('refresh-stock');document.getElementById('print-stock').onclick=printStock;

let activeCount=null;async function loadCount(){pulseButton('create-count',false);const y=+document.getElementById('count-year').value,m=+document.getElementById('count-month').value;const d=await api(`/api/v1/inventory/counts?year=${y}&month=${m}`);const h=(d.inventory_counts||[])[0];if(!h){activeCount=null;renderCount();return}activeCount=(await api(`/api/v1/inventory/counts/${h.id}`)).inventory_count;renderCount()}
function renderCount(){const body=document.getElementById('count-body');if(!activeCount){document.getElementById('count-title').textContent='月次棚卸';document.getElementById('count-summary').textContent='未準備';body.innerHTML='<tr><td colspan="7" class="empty">棚卸を準備してください。</td></tr>';return}document.getElementById('count-title').textContent=`${activeCount.year}年${activeCount.month}月棚卸（基準日 ${activeCount.count_date}）`;const filter=document.getElementById('count-filter').value;const rows=activeCount.lines.filter(x=>filter==='uncounted'?x.counted_quantity===null:filter==='variance'?Math.abs(Number(x.variance_quantity||0))>.00005:true);const un=activeCount.lines.filter(x=>x.counted_quantity===null).length,vr=activeCount.lines.filter(x=>Math.abs(Number(x.variance_quantity||0))>.00005).length;document.getElementById('count-summary').innerHTML=`<span class="status ${activeCount.status}">${esc(activeCount.status)}</span><span>未入力 ${un}</span><span>差異 ${vr}</span>`;body.innerHTML=rows.map(x=>{const countedValue=num(x.counted_quantity)===null?Math.round(num(x.book_quantity)||0):Math.round(num(x.counted_quantity));return `<tr data-id="${x.id}"><td>${lotDisplay(x.lot_name||x.product_name,x.lot_code||'未割当')}</td><td>${esc(x.capacity_value??'-')} ${esc(x.capacity_unit_name??'')}</td><td>${esc(x.stock_location_name)}</td><td class="num num-compact">${qty(x.book_quantity)}</td><td class="num num-compact">${activeCount.status==='draft'?`<input class="counted" type="number" min="0" step="1" value="${countedValue}">`:qty(x.counted_quantity)}</td><td class="num num-compact ${Math.abs(Number(x.variance_quantity||0))>.00005?'variance':''}">${qty(x.variance_quantity)}</td><td>${activeCount.status==='draft'?`<input class="variance-reason" value="${esc(x.reason||'')}">`:esc(x.reason||'-')}</td></tr>`}).join('')||'<tr><td colspan="7" class="empty">該当明細はありません。</td></tr>'}
document.getElementById('create-count')?.addEventListener('click',async()=>{try{pulseButton('create-count',false);msg('count-message','準備中...');activeCount=(await api('/api/v1/inventory/counts',{method:'POST',body:JSON.stringify({year:+countYear(),month:+countMonth()})})).inventory_count;renderCount();msg('count-message','棚卸を準備しました。')}catch(e){msg('count-message',e.message,true)}});
const countYear=()=>document.getElementById('count-year').value,countMonth=()=>document.getElementById('count-month').value;
async function saveCount(){if(!activeCount)return;const lines=[...document.querySelectorAll('#count-body tr[data-id]')].map(tr=>({id:+tr.dataset.id,counted_quantity:tr.querySelector('.counted')?.value||null,reason:tr.querySelector('.variance-reason')?.value||null}));activeCount=(await api(`/api/v1/inventory/counts/${activeCount.id}`,{method:'PUT',body:JSON.stringify({lines})})).inventory_count;renderCount()}
document.getElementById('save-count')?.addEventListener('click',async()=>{try{await saveCount();msg('count-message','入力を保存しました。')}catch(e){msg('count-message',e.message,true)}});
document.getElementById('confirm-count')?.addEventListener('click',async()=>{try{await saveCount();const reason=prompt('棚卸確定理由を入力してください。','月次棚卸確定');if(!reason)return;activeCount=(await api(`/api/v1/inventory/counts/${activeCount.id}/confirm`,{method:'POST',body:JSON.stringify({reason})})).inventory_count;renderCount();msg('count-message','棚卸を確定し、差異を在庫移動へ反映しました。');await loadStock()}catch(e){msg('count-message',e.message,true)}});document.getElementById('count-filter').onchange=()=>pulseButton('create-count');['count-year','count-month'].forEach(id=>document.getElementById(id).onchange=()=>pulseButton('create-count'));

let closeRows=[];async function loadClose(){const y=document.getElementById('close-year').value,m=document.getElementById('close-month').value;const d=await api(`/api/v1/monthly-closing/stock-balances?year=${y}&month=${m}`);closeRows=d.stock_lot_monthly_balances||[];renderClose()}
function renderClose(){document.getElementById('close-summary').textContent=`${closeRows.length}件`;document.getElementById('close-body').innerHTML=closeRows.map(x=>`<tr><td><span class="status ${x.status}">${esc(x.status)}</span></td><td>${lotDisplay(x.lot_name,x.lot_code)}</td><td>${esc(x.capacity_value??'-')} ${esc(x.capacity_unit_name??'')}</td><td>${esc(x.stock_location_name)}</td><td>${esc(x.unit_name??'')}</td><td class="num num-compact">${qty(x.closing_quantity)}</td></tr>`).join('')||'<tr><td colspan="6" class="empty">月次残高はありません。</td></tr>'}
document.getElementById('load-close').onclick=()=>loadClose().catch(e=>msg('close-message',e.message,true));document.getElementById('draft-close')?.addEventListener('click',async()=>{try{const body={year:+document.getElementById('close-year').value,month:+document.getElementById('close-month').value,reason:document.getElementById('close-reason').value};closeRows=(await api('/api/v1/monthly-closing/stock-balances',{method:'POST',body:JSON.stringify(body)})).stock_lot_monthly_balances;renderClose();msg('close-message','月次残高を再計算しました。')}catch(e){msg('close-message',e.message,true)}});document.getElementById('confirm-close')?.addEventListener('click',async()=>{try{const y=document.getElementById('close-year').value,m=document.getElementById('close-month').value,reason=document.getElementById('close-reason').value;const counts=(await api(`/api/v1/inventory/counts?year=${y}&month=${m}`)).inventory_counts||[];if(!counts.some(x=>x.status==='confirmed'))throw new Error('先に対象月の棚卸を確定してください。');closeRows=(await api(`/api/v1/monthly-closing/stock-balances/${y}/${m}/confirm`,{method:'POST',body:JSON.stringify({reason})})).stock_lot_monthly_balances;renderClose();msg('close-message','月次在庫を確定しました。')}catch(e){msg('close-message',e.message,true)}});

let operationRows=[];
let selectedOperation=null;
function lotLabel(lot){return lotOptionLabel(lot.name,lot.code)}
function selectedOperationLot(tr){return operationLots.find(x=>String(x.id)===tr.querySelector('.op-lot').value)}
function updateOpLineCount(){document.getElementById('op-line-count').textContent=`${document.querySelectorAll('#op-lines tr').length}行`}
function syncOperationLotInfo(tr){const lot=selectedOperationLot(tr);tr.querySelector('.op-location-text').textContent=lot?.stock_location_name||'-'}
function addOpLine(line=null,editable=true){
  const tr=document.createElement('tr');
  tr.innerHTML=`<td><select class="op-lot">${optionHtml(operationLots,lotLabel)}</select></td><td class="op-location-text"></td><td class="num"><input class="op-qty" type="number" step="1" value="${esc(integerInput(line?.quantity??'-1'))}"></td><td><button type="button" class="danger remove-line">削除</button></td>`;
  document.getElementById('op-lines').append(tr);
  if(line?.production_lot_id)tr.querySelector('.op-lot').value=String(line.production_lot_id);
  tr.querySelector('.op-lot').onchange=()=>syncOperationLotInfo(tr);
  tr.querySelector('.remove-line').onclick=()=>{tr.remove();updateOpLineCount()};
  tr.querySelectorAll('select,input,button').forEach(x=>x.disabled=!editable);
  syncOperationLotInfo(tr);updateOpLineCount();
}
function operationStatus(operation){
  if(operation.status==='cancelled')return{label:'取消済み',className:'cancelled'};
  if(!operation.is_period_open)return{label:'締め済み',className:'period-lock'};
  return{label:'未締め',className:'confirmed'};
}
const revisionActionLabels={created:'登録',updated:'変更',cancelled:'取消'};
function renderRevisionHistory(operation){
  const revisions=operation?.revisions?.length?operation.revisions:(operation?[{revision_no:operation.revision_no||1,action:operation.status==='cancelled'?'cancelled':'created',operation_date:operation.operation_date,reason:operation.reason,created_by_name:'-'}]:[]);
  document.getElementById('revision-count').textContent=`${revisions.length}件`;
  document.getElementById('revision-body').innerHTML=[...revisions].reverse().map(x=>`<tr><td>第${x.revision_no}版</td><td>${esc(revisionActionLabels[x.action]||x.action)}</td><td>${esc(x.operation_date)}</td><td>${esc(x.reason)}</td><td>${esc(x.created_by_name||'-')}</td></tr>`).join('')||'<tr><td colspan="5" class="empty">新規登録時に第1版が作成されます。</td></tr>';
}
function syncOperationReasonField(){const optional=!selectedOperation&&['bottling','repackaging'].includes(document.getElementById('op-type').value);const field=document.getElementById('op-reason-field'),input=document.getElementById('op-reason');field.hidden=optional;input.required=!optional;if(optional)input.value=''}
function setOperationForm(operation=null){
  selectedOperation=operation;
  document.getElementById('operation-detail').hidden=false;
  document.getElementById('operation-placeholder').hidden=true;
  const editable=canCreateNonSales&&(!operation||operation.is_editable);
  document.getElementById('operation-title').textContent=operation?operation.operation_number:'新規登録';
  document.getElementById('op-type').value=operation?.operation_type||'repackaging';
  document.getElementById('op-date').value=operation?.operation_date||today;
  document.getElementById('op-reason').value=operation?.reason||'';
  syncOperationReasonField();
  document.getElementById('op-lines').replaceChildren();
  (operation?.lines?.length?operation.lines:[null]).forEach(line=>addOpLine(line,editable));
  document.querySelectorAll('#operation-detail input,#operation-detail select').forEach(x=>x.disabled=!editable);
  const addButton=document.getElementById('add-op-line'),saveButton=document.getElementById('save-operation'),deleteButton=document.getElementById('delete-operation'),resetButton=document.getElementById('reset-operation');
  if(addButton)addButton.disabled=!editable;
  if(saveButton){saveButton.disabled=!editable;saveButton.textContent=operation?'変更内容を確認':'登録'}
  if(deleteButton)deleteButton.hidden=!operation||!editable;
  if(resetButton)resetButton.hidden=!editable;
  const state=operationStatus(operation||{status:'confirmed',is_period_open:true});
  const badge=document.getElementById('operation-state');badge.textContent=state.label;badge.className=`status ${state.className}`;
  document.getElementById('operation-version').textContent=`第${operation?.revision_no||1}版`;
  document.getElementById('operation-lock').textContent=operation&&!editable?(operation.source_sales_return_header_id?'返品処理から作成されたため閲覧のみです。':'月次締め済み、取消済み、または訂正済みのため閲覧のみです。'):'';
  renderRevisionHistory(operation);
  renderOperations();
}
function renderOperations(){
  const rows=operationRows;
  document.getElementById('operation-count').textContent=`${rows.length}件`;
  document.getElementById('operations-body').innerHTML=rows.map(x=>`<tr data-operation-id="${x.id}" class="${selectedOperation?.id===x.id?'selected':''}"><td class="operation-number">${esc(x.operation_number)}</td><td>${esc(x.operation_date)}</td><td>${esc(operationTypeLabel(x.operation_type))}</td><td>第${x.revision_no||1}版</td></tr>`).join('')||'<tr><td colspan="4" class="empty">該当する登録はありません。</td></tr>';
  document.querySelectorAll('#operations-body tr[data-operation-id]').forEach(row=>row.onclick=()=>selectOperation(+row.dataset.operationId));
}
function operationSearchParams(){const qs=new URLSearchParams();const id=document.getElementById('operation-search-id').value.trim(),date=document.getElementById('operation-search-date').value,type=document.getElementById('operation-search-type').value;if(id)qs.set('id',id);if(date)qs.set('operation_date',date);if(type)qs.set('operation_type',type);return qs}
async function loadOperations(selectId=null){
  const qs=operationSearchParams().toString();
  const d=await api(`/api/v1/non-sales-stock-operations${qs?`?${qs}`:''}`);operationRows=d.non_sales_stock_operations||[];renderOperations();
  const id=selectId??selectedOperation?.id;if(id){const found=operationRows.find(x=>x.id===id);if(found)setOperationForm(found)}
}
async function selectOperation(id){
  try{const d=await api(`/api/v1/non-sales-stock-operations/${id}`);setOperationForm(d.non_sales_stock_operation);msg('op-message','')}catch(e){msg('op-message',e.message,true)}
}
function operationPayload(){
  const lines=[...document.querySelectorAll('#op-lines tr')].map(tr=>{const lot=selectedOperationLot(tr);if(!lot)throw new Error('ロットを選択してください。');return{stock_location_id:+lot.stock_location_id,quantity:tr.querySelector('.op-qty').value,production_lot_id:+lot.id}});
  if(!lines.length)throw new Error('明細を1行以上入力してください。');
  const operationType=document.getElementById('op-type').value,reason=document.getElementById('op-reason').value.trim();if((selectedOperation||!['bottling','repackaging'].includes(operationType))&&!reason)throw new Error('理由を入力してください。');
  return{operation_type:operationType,operation_date:document.getElementById('op-date').value,reason,note:selectedOperation?.note||null,lines};
}
let pendingOperationConfirmation=null;
function confirmationLines(lines){return lines.map(line=>{const lot=operationLots.find(x=>x.id===line.production_lot_id);return `${lot?.name||line.lot_name||line.lot_code||'-'} ${qty(line.quantity)}`}).join('、')}
async function checkRepackagingAlcohol(payload){if(payload.operation_type!=='repackaging')return null;const d=await api('/api/v1/non-sales-stock-operations/repackaging-alcohol-check',{method:'POST',body:JSON.stringify({lines:payload.lines})});return d.alcohol_check}
function renderAlcoholWarning(check){const box=document.getElementById('confirm-alcohol-warning');box.hidden=!check?.has_warning;if(!check?.has_warning){box.replaceChildren();return}const range=check.source_average===null?'出庫側の基準度数を算出できません。':`出庫側平均 ${Number(check.source_average).toFixed(1)}％ / 許容範囲 ${Number(check.allowed_min).toFixed(1)}～${Number(check.allowed_max).toFixed(1)}％`;box.innerHTML=`<strong>アルコール度数の警告</strong><div>${esc(range)}</div><ul>${check.warnings.map(x=>`<li>${esc(x.message)}</li>`).join('')}</ul>`}
function openCreateWarningConfirmation(payload,check){pendingOperationConfirmation={action:'create-warning',payload:{...payload,alcohol_warning_acknowledged:true}};document.getElementById('confirm-title').textContent='詰替ロットの警告確認';document.getElementById('confirm-before-title').textContent='登録する詰替';document.getElementById('confirm-before').textContent=`${payload.operation_date} / ${confirmationLines(payload.lines)}`;document.getElementById('confirm-after-title').textContent='確認後';document.getElementById('confirm-after').textContent='警告確認済みとして第1版を登録';renderAlcoholWarning(check);document.getElementById('confirm-impact').textContent='ロットの選択と分析値を確認してください。続行すると警告内容を確認済みとして在庫移動を登録します。';document.getElementById('confirm-reason-field').hidden=true;document.getElementById('confirm-commit').textContent='警告を確認して登録';document.getElementById('operation-confirm-dialog').showModal()}
function openUpdateConfirmation(payload,check=null){
  pendingOperationConfirmation={action:'update',payload:{...payload,...(check?.has_warning?{alcohol_warning_acknowledged:true}:{})}};
  const nextRevision=(selectedOperation.revision_no||1)+1;
  document.getElementById('confirm-title').textContent='変更内容の確認';
  document.getElementById('confirm-before-title').textContent=`現在の第${selectedOperation.revision_no||1}版`;
  document.getElementById('confirm-before').textContent=`${operationTypeLabel(selectedOperation.operation_type)} / ${selectedOperation.operation_date} / ${confirmationLines(selectedOperation.lines)}`;
  document.getElementById('confirm-after-title').textContent=`変更後の第${nextRevision}版`;
  document.getElementById('confirm-after').textContent=`${operationTypeLabel(payload.operation_type)} / ${payload.operation_date} / ${confirmationLines(payload.lines)}`;
  document.getElementById('confirm-impact').textContent=`現在の在庫移動 ${selectedOperation.lines.length}件を反対数量で打ち消し、新しい在庫移動 ${payload.lines.length}件を追加します。元の履歴は削除されません。`;
  renderAlcoholWarning(check);
  document.getElementById('confirm-reason-field').hidden=true;
  document.getElementById('confirm-commit').textContent=`打消移動を追加して第${nextRevision}版を登録`;
  document.getElementById('operation-confirm-dialog').showModal();
}
function openCancelConfirmation(){
  pendingOperationConfirmation={action:'cancel'};
  const nextRevision=(selectedOperation.revision_no||1)+1;
  document.getElementById('confirm-title').textContent='販売外出入の取消確認';
  document.getElementById('confirm-before-title').textContent=`有効な第${selectedOperation.revision_no||1}版`;
  document.getElementById('confirm-before').textContent=confirmationLines(selectedOperation.lines);
  document.getElementById('confirm-after-title').textContent=`取消後の第${nextRevision}版`;
  document.getElementById('confirm-after').textContent='有効数量 0（元の登録と打消履歴は保持）';
  document.getElementById('confirm-impact').textContent=`現在の在庫移動 ${selectedOperation.lines.length}件を反対数量で打ち消します。販売外出入と移動履歴は削除されません。`;
  renderAlcoholWarning(null);
  document.getElementById('confirm-reason-field').hidden=false;document.getElementById('confirm-reason').value='';
  document.getElementById('confirm-commit').textContent='反対移動を追加して取り消す';
  document.getElementById('operation-confirm-dialog').showModal();
}
document.getElementById('new-operation')?.addEventListener('click',()=>{setOperationForm();msg('op-message','新しい登録内容を入力してください。')});
document.getElementById('add-op-line')?.addEventListener('click',()=>addOpLine(null,true));
document.getElementById('reset-operation')?.addEventListener('click',()=>setOperationForm(selectedOperation));
document.getElementById('op-type').addEventListener('change',syncOperationReasonField);
async function createOperation(payload){const d=await api('/api/v1/non-sales-stock-operations',{method:'POST',body:JSON.stringify(payload)});msg('op-message','第1版を登録し、在庫移動を追加しました。');await Promise.all([loadOperations(d.non_sales_stock_operation.id),loadStock(),loadMovements()])}
document.getElementById('save-operation')?.addEventListener('click',async()=>{try{const payload=operationPayload(),check=await checkRepackagingAlcohol(payload);if(selectedOperation){openUpdateConfirmation(payload,check);return}if(check?.has_warning){openCreateWarningConfirmation(payload,check);return}await createOperation(payload)}catch(e){msg('op-message',e.message,true)}});
document.getElementById('delete-operation')?.addEventListener('click',()=>{if(selectedOperation)openCancelConfirmation()});
document.getElementById('confirm-back').onclick=()=>document.getElementById('operation-confirm-dialog').close();
document.getElementById('confirm-commit').onclick=async()=>{const button=document.getElementById('confirm-commit');try{button.disabled=true;if(pendingOperationConfirmation?.action==='create-warning'){await createOperation(pendingOperationConfirmation.payload);document.getElementById('operation-confirm-dialog').close()}else if(pendingOperationConfirmation?.action==='update'){const d=await api(`/api/v1/non-sales-stock-operations/${selectedOperation.id}`,{method:'PUT',body:JSON.stringify(pendingOperationConfirmation.payload)});document.getElementById('operation-confirm-dialog').close();msg('op-message',`第${d.non_sales_stock_operation.revision_no}版を登録しました。旧版の移動は打消履歴として保持されています。`);await Promise.all([loadOperations(d.non_sales_stock_operation.id),loadStock(),loadMovements()])}else if(pendingOperationConfirmation?.action==='cancel'){const reason=document.getElementById('confirm-reason').value.trim();if(!reason)throw new Error('取消理由を入力してください。');const d=await api(`/api/v1/non-sales-stock-operations/${selectedOperation.id}/cancel`,{method:'POST',body:JSON.stringify({reason})});document.getElementById('operation-confirm-dialog').close();msg('op-message',`第${d.non_sales_stock_operation.revision_no}版で取消しました。反対移動と元履歴を保持しています。`);await Promise.all([loadOperations(d.non_sales_stock_operation.id),loadStock(),loadMovements()])}}catch(e){msg('op-message',e.message,true)}finally{button.disabled=false}};
document.getElementById('refresh-operations').onclick=()=>loadOperations().catch(e=>msg('op-message',e.message,true));
document.getElementById('search-operations').onclick=()=>loadOperations().catch(e=>msg('op-message',e.message,true));
document.getElementById('clear-operation-search').onclick=()=>{document.getElementById('operation-search-id').value='';document.getElementById('operation-search-date').value='';document.getElementById('operation-search-type').value='';loadOperations().catch(e=>msg('op-message',e.message,true))};
document.getElementById('operation-search-id').addEventListener('keydown',e=>{if(e.key!=='Enter')return;e.preventDefault();loadOperations().catch(error=>msg('op-message',error.message,true))});
['operation-search-date','operation-search-type'].forEach(id=>document.getElementById(id).addEventListener('change',()=>loadOperations().catch(error=>msg('op-message',error.message,true))));

function movementParams(){const qs=new URLSearchParams({year:document.getElementById('movement-year').value,month:document.getElementById('movement-month').value});const type=document.getElementById('movement-type').value,search=document.getElementById('movement-search').value.trim();if(type)qs.set('movement_type',type);if(search)qs.set('q',search);return qs}
async function loadMovements(){pulseButton('load-movements',false);const d=await api(`/api/v1/inventory/movements?${movementParams()}`);document.getElementById('movements-body').innerHTML=(d.stock_movements||[]).map(x=>`<tr><td>${x.movement_date}</td><td>${esc(movementTypeLabel(x.movement_type))}</td><td>${lotDisplay(x.lot_name,x.lot_code)}</td><td>${esc(x.stock_location_name)}</td><td class="num num-compact">${qty(x.quantity)}</td><td>${esc(x.source_document_number||'-')}</td></tr>`).join('')||'<tr><td colspan="6" class="empty">移動履歴はありません。</td></tr>'};document.getElementById('load-movements').onclick=()=>loadMovements().catch(e=>msg('movement-message',e.message,true));['movement-year','movement-month','movement-type'].forEach(id=>document.getElementById(id).onchange=()=>pulseButton('load-movements'));document.getElementById('print-movements').onclick=()=>window.open(`/inventory/movements/print?${movementParams()}`,'_blank');
document.getElementById('movement-search').oninput=()=>pulseButton('load-movements');

loadStock=async()=>{try{pulseButton('refresh-stock',false);msg('stock-message','読込中...');const qs=new URLSearchParams({as_of_date:document.getElementById('stock-as-of-date').value});const type=document.getElementById('stock-product-type')?.value;if(type)qs.set('product_type',type);if(document.getElementById('stock-show-zero').checked)qs.set('include_zero_stock','1');const d=await api(`/api/v1/inventory/stock?${qs}`);stockRows=d.stock_balances||[];renderStock();msg('stock-message','')}catch(e){msg('stock-message',e.message,true)}};
renderStock=()=>{const q=document.getElementById('stock-search').value;const rows=stockRows.filter(r=>matchesSearchText(`${r.lot_code} ${r.lot_name} ${r.product_code} ${r.product_name} ${r.capacity_value??''} ${r.capacity_unit_name??''}`,q));document.getElementById('stock-count').textContent=`基準日 ${document.getElementById('stock-as-of-date').value} / ${rows.length}件`;document.getElementById('stock-body').innerHTML=rows.map(r=>{const l=locationMap.get(String(r.stock_location_id)),negative=Number(r.physical_quantity)<0||Number(r.available_quantity)<0;return `<tr class="${negative?'negative-stock':''}"><td title="${lotHover(r.lot_name,r.lot_code)}">${lotDisplay(r.lot_name,r.lot_code)}</td><td>${esc(r.product_type_label||productTypeLabel(r.product_type))}</td><td>${wholeNumber(r.capacity_value)} ${esc(r.capacity_unit_name??'')}</td><td>${esc(l?.name)}</td><td class="num num-compact">${alcohol(r.alcohol_percentage)}</td><td class="num num-compact">${sakeMeter(r.sake_meter_value)}</td><td class="num num-compact">${fixed(r.acidity)}</td><td class="num num-compact">${fixed(r.amino_acidity)}</td><td class="num num-compact">${qty(r.physical_quantity)} ${esc(r.unit_name??'')}</td><td class="num num-compact">${qty(r.allocated_quantity)}</td><td class="num num-compact">${qty(r.available_quantity)}</td></tr>`}).join('')||'<tr><td colspan="11" class="empty">在庫はありません。</td></tr>'};
printStock=()=>{const params=new URLSearchParams({as_of_date:document.getElementById('stock-as-of-date').value});const q=document.getElementById('stock-search').value.trim(),type=document.getElementById('stock-product-type')?.value;if(q)params.set('q',q);if(type)params.set('product_type',type);if(document.getElementById('stock-show-zero').checked)params.set('include_zero_stock','1');window.open(`/inventory/lot-stock-as-of/print?${params}`,'_blank')};
document.getElementById('refresh-stock').onclick=loadStock;document.getElementById('stock-as-of-date').onchange=()=>pulseButton('refresh-stock');document.getElementById('stock-show-zero').onchange=()=>pulseButton('refresh-stock');document.getElementById('stock-product-type').onchange=()=>pulseButton('refresh-stock');document.getElementById('stock-search').oninput=()=>pulseButton('refresh-stock');document.getElementById('print-stock').onclick=printStock;
loadStock();loadCount().catch(()=>{});loadClose().catch(()=>{});loadOperations().catch(()=>{});loadMovements().catch(()=>{});
</script>
</body></html>
