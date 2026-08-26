<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>小売販売システム 設定</title>
  <style>
    :root{--bg:#f3f6fa;--panel:#fff;--soft:#f8fbff;--line:#d9e2ee;--text:#172033;--muted:#65758c;--blue:#0b6ff6;--blue-dark:#075ecf;--green:#198754;--amber:#b9770e;--red:#b42318;--sidebar:#10243b}
    *{box-sizing:border-box}
    body{margin:0;min-height:100vh;background:var(--bg);color:var(--text);font-family:"Noto Sans JP",Meiryo,system-ui,sans-serif;padding-left:230px}
    .sidebar{position:fixed;inset:0 auto 0 0;width:230px;background:var(--sidebar);color:#eaf1fb;padding:18px 12px}
    .brand{padding:6px 10px 16px;border-bottom:1px solid rgba(255,255,255,.14)}
    .brand h1{margin:0;font-size:16px;line-height:1.45}
    .brand p{margin:6px 0 0;color:#a8b8cc;font-size:12px;line-height:1.6}
    .company-select{display:grid;gap:6px;margin:16px 0 14px;padding:0 10px}
    .company-select label{color:#a8b8cc;font-size:11px;font-weight:800}
    .company-select select{width:100%;height:38px;border:1px solid rgba(255,255,255,.24);border-radius:6px;background:#fff;color:#172033;padding:0 10px;font:inherit;font-size:13px}
    .nav{display:grid;gap:4px}
    .nav a{display:flex;align-items:center;justify-content:space-between;min-height:38px;padding:0 12px;border-radius:6px;color:#dbe7f5;text-decoration:none;font-size:12px}
    .nav a.active{background:var(--blue);color:#fff;font-weight:800}
    .nav a span:last-child{color:#b8c6d9;font-size:11px}
    .content{padding:18px}
    .topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px 16px;margin-bottom:12px}
    .topbar h2{margin:0;font-size:17px}
    .meta{color:var(--muted);font-size:12px;line-height:1.65}
    .btn{border:1px solid #c7d3e2;border-radius:6px;background:#fff;color:#1d3450;padding:8px 12px;font:inherit;font-size:12px;cursor:pointer}
    .btn.primary{border-color:var(--blue);background:var(--blue);color:#fff;font-weight:800}
    .layout{display:grid;grid-template-columns:260px 1fr;gap:12px;align-items:start}
    .panel{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px}
    .panel h3{margin:0 0 4px;font-size:15px}
    .settings-nav{display:grid;gap:7px;margin-top:12px}
    .settings-nav button{display:flex;align-items:center;justify-content:space-between;border:1px solid var(--line);border-radius:6px;background:var(--soft);padding:11px 10px;font:inherit;font-size:12px;cursor:pointer;text-align:left}
    .settings-nav button.active{border-color:var(--blue);background:#eef5ff;color:var(--blue-dark);font-weight:800}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px}
    .card{border:1px solid var(--line);border-radius:8px;background:var(--soft);padding:12px}
    .card h4{margin:0 0 8px;font-size:14px}
    .field{display:grid;gap:6px;margin-top:10px}
    .field label{color:#4d6078;font-size:11px;font-weight:800}
    input,select,textarea{width:100%;min-height:36px;border:1px solid #cbd7e6;border-radius:6px;background:#fff;color:var(--text);font:inherit;padding:8px}
    textarea{min-height:70px;resize:vertical}
    .table{margin-top:10px;border:1px solid var(--line);border-radius:7px;overflow:hidden;background:#fff}
    .tr{display:grid;grid-template-columns:1fr 110px 110px;gap:8px;padding:9px 10px;border-top:1px solid var(--line);align-items:center;font-size:12px}
    .tr:first-child{border-top:0;background:#eef3f8;color:#4d6078;font-weight:800}
    .badge{display:inline-flex;align-items:center;justify-content:center;min-height:24px;border-radius:999px;background:#e8f2ff;color:var(--blue-dark);font-size:11px;font-weight:800;padding:0 9px}
    .badge.green{background:#e9f8ef;color:var(--green)}
    .badge.amber{background:#fff4df;color:var(--amber)}
    .footer-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}
    @media(max-width:980px){body{padding-left:0}.sidebar{position:static;width:auto}.layout,.grid{grid-template-columns:1fr}}
    @media(max-width:640px){.content{padding:10px}.topbar{align-items:flex-start;flex-direction:column}.tr{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <aside class="sidebar">
    <div class="brand">
      <h1>小売販売システム</h1>
      <p>販売・請求・入金・仕入の基本設定を管理します。</p>
    </div>
    <div class="company-select">
      <label for="company-select">会社</label>
      <select id="company-select">
        <option value="maru">○社</option>
        <option value="batsu">×社</option>
        <option value="sankaku">▲社</option>
      </select>
    </div>
    <nav class="nav" id="main-nav"></nav>
  </aside>

  <main class="content">
    <header class="topbar">
      <div>
        <h2 id="title">○社 設定</h2>
        <div class="meta" id="meta">小売販売の運用条件を会社単位で設定します。</div>
      </div>
      <button class="btn primary">設定を保存</button>
    </header>

    <section class="layout">
      <aside class="panel">
        <h3>設定メニュー</h3>
        <div class="meta">最初はこの範囲で十分です。</div>
        <div class="settings-nav">
          <button class="active">基本情報 <span>会社</span></button>
          <button>店舗・レジ <span>店舗</span></button>
          <button>小売顧客 <span>請求</span></button>
          <button>納品書・請求書 <span>帳票</span></button>
          <button>入金管理 <span>消込</span></button>
          <button>仕入先 <span>蔵/外部</span></button>
          <button>蔵連携 <span>自動発注</span></button>
        </div>
      </aside>

      <section class="panel">
        <h3 id="section-title">○社の設定内容</h3>
        <div class="meta">蔵から仕入れる商品と、蔵以外から仕入れる商品を同じ小売在庫に入れます。発注先だけを区分します。</div>

        <div class="grid">
          <article class="card">
            <h4>小売顧客・請求条件</h4>
            <div class="field">
              <label>標準締日</label>
              <select>
                <option>月末締め</option>
                <option>20日締め</option>
                <option>都度請求</option>
              </select>
            </div>
            <div class="field">
              <label>標準支払条件</label>
              <select>
                <option>翌月末払い</option>
                <option>翌月20日払い</option>
                <option>現金・即時</option>
              </select>
            </div>
            <div class="field">
              <label>納品書発行</label>
              <select>
                <option>掛売・配送時に発行</option>
                <option>すべての売上で発行</option>
                <option>必要時のみ発行</option>
              </select>
            </div>
          </article>

          <article class="card">
            <h4>入金管理</h4>
            <div class="field">
              <label>入金方法</label>
              <select>
                <option>現金 / 振込 / カード / QR</option>
              </select>
            </div>
            <div class="field">
              <label>消込方法</label>
              <select>
                <option>請求書単位で消込</option>
                <option>古い請求から自動消込</option>
                <option>手動消込のみ</option>
              </select>
            </div>
            <div class="field">
              <label>未収警告</label>
              <select>
                <option>支払期日超過で警告</option>
                <option>与信限度超過で警告</option>
              </select>
            </div>
          </article>

          <article class="card">
            <h4>仕入先設定</h4>
            <div class="meta">蔵以外の仕入れもここに登録します。</div>
            <div class="table" id="supplier-table"></div>
            <div class="footer-actions">
              <button class="btn">仕入先を追加</button>
              <button class="btn primary">発注設定</button>
            </div>
          </article>

          <article class="card">
            <h4>蔵連携・自動発注</h4>
            <div class="field">
              <label>蔵への発注方式</label>
              <select>
                <option>販売数に応じて補充発注案を作成</option>
                <option>在庫下限を下回ったら発注案を作成</option>
                <option>手動発注のみ</option>
              </select>
            </div>
            <div class="field">
              <label>蔵側取引先コード</label>
              <input id="brewery-code" value="BR-CUST-001">
            </div>
            <div class="field">
              <label>外部仕入先の扱い</label>
              <textarea>蔵商品は蔵へ自動発注。食品・包装資材・他社商品は登録した外部仕入先へ発注。</textarea>
            </div>
          </article>
        </div>
      </section>
    </section>
  </main>

  <script>
    const companies = {
      maru: {
        name:'○社',
        meta:'銀座店・新宿店を運営。掛売と配送が多い。',
        breweryCode:'BR-CUST-001',
        suppliers:[['蔵販売業務システム','蔵商品','自動発注'],['山田食品','おつまみ','手動発注'],['東都包材','箱・袋','定期発注']]
      },
      batsu: {
        name:'×社',
        meta:'法人向けギフト販売が中心。請求締め処理を重視。',
        breweryCode:'BR-CUST-018',
        suppliers:[['蔵販売業務システム','日本酒','自動発注'],['ギフト工房ミツバ','包装加工','都度発注'],['中央運送','配送資材','手動発注']]
      },
      sankaku: {
        name:'▲社',
        meta:'飲食店向け小売と外部銘柄の仕入れが多い。',
        breweryCode:'BR-CUST-027',
        suppliers:[['蔵販売業務システム','自社蔵商品','自動発注'],['北浜酒販','他社銘柄','手動発注'],['三角フーズ','食品','定期発注']]
      }
    };
    const menu = [['販売画面','POS'],['小売顧客','顧客'],['納品書','納品'],['請求書','請求'],['入金管理','入金'],['仕入・発注','仕入'],['設定','設定']];
    const $ = (id) => document.getElementById(id);
    function render(key){
      const c = companies[key];
      $('title').textContent = `${c.name} 設定`;
      $('section-title').textContent = `${c.name}の設定内容`;
      $('meta').textContent = c.meta;
      $('brewery-code').value = c.breweryCode;
      $('main-nav').innerHTML = menu.map(([label,short],i)=>`<a class="${i===6?'active':''}" href="#"><span>${c.name} ${label}</span><span>${short}</span></a>`).join('');
      $('supplier-table').innerHTML = '<div class="tr"><span>仕入先</span><span>対象</span><span>方式</span></div>' + c.suppliers.map(([name,type,mode],i)=>`<div class="tr"><strong>${name}</strong><span>${type}</span><span class="badge ${i===0?'green':mode==='定期発注'?'amber':''}">${mode}</span></div>`).join('');
    }
    $('company-select').addEventListener('change',()=>render($('company-select').value));
    render('maru');
  </script>
</body>
</html>
