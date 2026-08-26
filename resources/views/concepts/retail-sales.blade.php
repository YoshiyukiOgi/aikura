<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>小売販売システム</title>
  <style>
    :root{
      --bg:#f3f6fa;
      --panel:#fff;
      --soft:#f8fbff;
      --line:#d9e2ee;
      --text:#172033;
      --muted:#65758c;
      --blue:#0b6ff6;
      --blue-dark:#075ecf;
      --green:#198754;
      --amber:#b9770e;
      --red:#b42318;
      --sidebar:#10243b;
    }
    *{box-sizing:border-box}
    body{margin:0;min-height:100vh;background:var(--bg);color:var(--text);font-family:Inter,"Noto Sans JP",system-ui,sans-serif;padding-left:230px}
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
    .nav a.active span:last-child{color:#fff}
    .content{padding:18px}
    .topbar{height:58px;display:flex;align-items:center;justify-content:space-between;gap:14px;background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:0 16px;margin-bottom:12px}
    .topbar h2{margin:0;font-size:17px}
    .meta{color:var(--muted);font-size:12px}
    .btn{border:1px solid #c7d3e2;border-radius:6px;background:#fff;color:#1d3450;padding:8px 12px;font:inherit;font-size:12px;cursor:pointer}
    .btn.primary{border-color:var(--blue);background:var(--blue);color:#fff;font-weight:800}
    .grid{display:grid;grid-template-columns:1fr 360px;gap:12px;align-items:start}
    .panel{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px}
    .panel h3{margin:0 0 4px;font-size:15px}
    .summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:12px 0}
    .kpi{background:var(--soft);border:1px solid var(--line);border-radius:6px;padding:12px}
    .kpi .label{color:var(--muted);font-size:11px}
    .kpi .value{margin-top:6px;font-size:22px;font-weight:800}
    .work{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:12px}
    .work-card{border:1px solid var(--line);border-radius:6px;background:#fff;padding:12px;min-height:118px}
    .work-card h4{margin:0 0 6px;font-size:14px}
    .work-card p{margin:0;color:var(--muted);font-size:12px;line-height:1.55}
    .work-card button{width:100%;margin-top:12px}
    .list{display:grid;gap:8px;margin-top:12px}
    .row{display:flex;align-items:center;justify-content:space-between;gap:10px;border:1px solid var(--line);border-radius:6px;background:var(--soft);padding:10px}
    .row strong{font-size:13px}
    .row small{display:block;color:var(--muted);font-size:11px;margin-top:4px}
    .badge{padding:5px 8px;border-radius:999px;background:#eaf7ef;color:var(--green);font-size:11px;font-weight:800}
    .badge.warn{background:#fff4e5;color:var(--amber)}
    .badge.alert{background:#feeceb;color:var(--red)}
    .sales{display:grid;grid-template-columns:1fr 320px;gap:12px;margin-top:12px}
    .products{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    .product{border:1px solid var(--line);border-radius:6px;background:var(--soft);padding:10px}
    .thumb{height:42px;border-radius:5px;background:linear-gradient(135deg,#dbeafe,#d9f6e7);border:1px solid #dbe5f0;margin-bottom:9px}
    .product strong{font-size:13px}
    .product small{display:block;color:var(--muted);font-size:11px;line-height:1.5;margin-top:4px}
    .price{margin-top:7px;color:var(--blue-dark);font-weight:800}
    .total{display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--line);margin-top:10px;padding-top:10px}
    .total strong{font-size:26px;color:var(--blue-dark)}
    @media(max-width:1050px){body{padding-left:0}.sidebar{position:static;width:auto}.grid,.sales{grid-template-columns:1fr}.summary,.work,.products{grid-template-columns:1fr 1fr}}
    @media(max-width:640px){.content{padding:10px}.topbar{height:auto;align-items:flex-start;flex-direction:column;padding:12px}.summary,.work,.products{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <aside class="sidebar">
    <div class="brand">
      <h1>小売販売システム</h1>
      <p>会社を選ぶと、その会社のメニューに切り替わります。</p>
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
        <h2 id="page-title">○社の業務</h2>
        <div class="meta" id="page-meta">本部経理 / 3店舗 / 通常販売</div>
      </div>
      <button class="btn primary" id="primary-action">販売画面へ</button>
    </header>

    <section class="grid">
      <div>
        <section class="panel">
          <h3>今日の状況</h3>
          <div class="meta">選択中の会社だけの数字です。</div>
          <div class="summary">
            <div class="kpi"><div class="label">売上</div><div class="value" id="sales">¥1,284,600</div></div>
            <div class="kpi"><div class="label">店舗</div><div class="value" id="stores">3</div></div>
            <div class="kpi"><div class="label">補充候補</div><div class="value" id="reorders">12</div></div>
            <div class="kpi"><div class="label">未締め</div><div class="value" id="pending">27</div></div>
          </div>
          <div class="work" id="work-menu"></div>
        </section>

        <section class="sales">
          <div class="panel">
            <h3 id="sales-title">販売画面プレビュー</h3>
            <div class="meta">実際の販売画面へ入る前の簡易表示。</div>
            <div class="products" id="products"></div>
          </div>
          <div class="panel">
            <h3>会計例</h3>
            <div class="list" id="cart"></div>
            <div class="total"><span>合計</span><strong id="total">¥9,614</strong></div>
          </div>
        </section>
      </div>

      <aside class="panel">
        <h3>店舗</h3>
        <div class="meta">選択会社に属する店舗。</div>
        <div class="list" id="store-list"></div>
      </aside>
    </section>
  </main>

  <script>
    const data = {
      maru: {
        name: '○社',
        meta: '本部経理 / 3店舗 / 通常販売',
        sales: '¥1,284,600',
        stores: '3',
        reorders: '12',
        pending: '27',
        storesList: [
          ['銀座店','営業中','ok','売上 ¥612,000 / 補充候補 5'],
          ['新宿店','注意','warn','低在庫SKU 7 / 発注待ち 1'],
          ['横浜店','要確認','alert','棚卸差異あり / 締め未完了'],
        ],
        products: [
          ['純米吟醸 720ml','BP-1001 / 在庫あり','¥1,980'],
          ['本醸造 300ml','BP-1008 / 在庫少','¥780'],
          ['梅酒 500ml','BP-1012 / 定番','¥1,250'],
        ],
        cart: [['純米吟醸 720ml','2本 / ¥3,960'],['本醸造 300ml','2本 / ¥1,560'],['ギフト 2本箱','1箱 / ¥4,400']],
        total: '¥9,614',
      },
      batsu: {
        name: '×社',
        meta: '別会計 / 2店舗 / 法人請求あり',
        sales: '¥864,200',
        stores: '2',
        reorders: '9',
        pending: '14',
        storesList: [
          ['恵比寿店','営業中','ok','売上 ¥501,700 / 補充候補 3'],
          ['池袋店','注意','warn','低在庫SKU 5 / 発注待ち 2'],
        ],
        products: [
          ['純米大吟醸 720ml','XC-2101 / 贈答向け','¥2,680'],
          ['スパークリング清酒','XC-2120 / 冷蔵','¥1,420'],
          ['飲み比べ3本セット','XC-2204 / 法人向け','¥3,900'],
        ],
        cart: [['純米大吟醸 720ml','1本 / ¥2,680'],['飲み比べ3本セット','1組 / ¥3,900'],['梅酒 720ml','2本 / ¥3,360']],
        total: '¥10,934',
      },
      sankaku: {
        name: '▲社',
        meta: '新規展開 / 4店舗 / 棚卸差異確認中',
        sales: '¥1,106,800',
        stores: '4',
        reorders: '15',
        pending: '19',
        storesList: [
          ['自由が丘店','要確認','alert','棚卸差異あり / 締め未完了'],
          ['二子玉川店','営業中','ok','補充候補 4'],
          ['武蔵小杉店','営業中','ok','低在庫SKU 3'],
          ['立川店','注意','warn','新商品配布あり'],
        ],
        products: [
          ['山廃純米 720ml','AC-3101 / 定番','¥1,880'],
          ['限定原酒 500ml','AC-3114 / 数量限定','¥2,420'],
          ['贈答セット 2本','AC-3202 / 化粧箱入り','¥4,980'],
        ],
        cart: [['山廃純米 720ml','1本 / ¥1,880'],['限定原酒 500ml','1本 / ¥2,420'],['贈答セット 2本','1組 / ¥4,980']],
        total: '¥10,208',
      },
    };

    const menu = [
      ['販売画面','POS','販売画面へ'],
      ['店舗一覧','店舗','店舗を見る'],
      ['店舗在庫','在庫','在庫を見る'],
      ['補充発注','発注','発注へ'],
      ['締め・経理','締め','締めへ'],
    ];

    const els = {
      select: document.getElementById('company-select'),
      nav: document.getElementById('main-nav'),
      title: document.getElementById('page-title'),
      meta: document.getElementById('page-meta'),
      sales: document.getElementById('sales'),
      stores: document.getElementById('stores'),
      reorders: document.getElementById('reorders'),
      pending: document.getElementById('pending'),
      work: document.getElementById('work-menu'),
      storesList: document.getElementById('store-list'),
      products: document.getElementById('products'),
      cart: document.getElementById('cart'),
      total: document.getElementById('total'),
    };

    function render(key) {
      const company = data[key];
      els.title.textContent = `${company.name}の業務`;
      els.meta.textContent = company.meta;
      els.sales.textContent = company.sales;
      els.stores.textContent = company.stores;
      els.reorders.textContent = company.reorders;
      els.pending.textContent = company.pending;
      els.nav.innerHTML = menu.map(([label,short], index) => `<a class="${index === 0 ? 'active' : ''}" href="#"><span>${company.name} ${label}</span><span>${short}</span></a>`).join('');
      els.work.innerHTML = menu.map(([label,,button]) => `<article class="work-card"><h4>${company.name} ${label}</h4><p>${company.name}のデータだけを開きます。</p><button class="btn primary">${button}</button></article>`).join('');
      els.storesList.innerHTML = company.storesList.map(([name,status,statusClass,meta]) => `<div class="row"><div><strong>${name}</strong><small>${meta}</small></div><span class="badge ${statusClass}">${status}</span></div>`).join('');
      els.products.innerHTML = company.products.map(([name,meta,price]) => `<div class="product"><div class="thumb"></div><strong>${name}</strong><small>${meta}</small><div class="price">${price}</div></div>`).join('');
      els.cart.innerHTML = company.cart.map(([name,meta]) => `<div class="row"><div><strong>${name}</strong><small>${meta}</small></div><button class="btn">編集</button></div>`).join('');
      els.total.textContent = company.total;
    }

    els.select.addEventListener('change', () => render(els.select.value));
    render('maru');
  </script>
</body>
</html>
