<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>小売販売入力</title>
  <style>
    :root{--bg:#f3f6fa;--panel:#fff;--soft:#f8fbff;--line:#d9e2ee;--text:#172033;--muted:#65758c;--blue:#0b6ff6;--blue-dark:#075ecf;--green:#198754;--amber:#b9770e;--red:#b42318;--sidebar:#10243b}
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
    .content{padding:18px}
    .topbar{height:58px;display:flex;align-items:center;justify-content:space-between;gap:14px;background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:0 16px;margin-bottom:12px}
    .topbar h2{margin:0;font-size:17px}
    .meta{color:var(--muted);font-size:12px}
    .btn{border:1px solid #c7d3e2;border-radius:6px;background:#fff;color:#1d3450;padding:8px 12px;font:inherit;font-size:12px;cursor:pointer}
    .btn.primary{border-color:var(--blue);background:var(--blue);color:#fff;font-weight:800}
    .btn.danger{border-color:#f1b7b1;color:var(--red)}
    .layout{display:grid;grid-template-columns:280px 1fr 360px;gap:12px;align-items:start}
    .panel{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px}
    .panel h3{margin:0 0 4px;font-size:15px}
    .field{display:grid;gap:6px;margin-top:12px}
    .field label{color:#4d6078;font-size:11px;font-weight:800}
    input,select,textarea{width:100%;min-height:36px;border:1px solid #cbd7e6;border-radius:6px;background:#fff;color:var(--text);font:inherit;padding:8px}
    textarea{min-height:76px;resize:vertical}
    .category{display:grid;gap:7px;margin-top:12px}
    .category button{display:flex;align-items:center;justify-content:space-between;border:1px solid var(--line);border-radius:6px;background:var(--soft);padding:10px;font:inherit;font-size:12px;cursor:pointer}
    .category button.active{border-color:var(--blue);background:#eef5ff;color:var(--blue-dark);font-weight:800}
    .products{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:12px}
    .product{border:1px solid var(--line);border-radius:6px;background:var(--soft);padding:10px;display:grid;gap:8px;min-height:142px}
    .thumb{height:44px;border-radius:5px;background:linear-gradient(135deg,#dbeafe,#d9f6e7);border:1px solid #dbe5f0}
    .product strong{font-size:13px}
    .product small{color:var(--muted);font-size:11px;line-height:1.45}
    .product .row{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:auto}
    .price{color:var(--blue-dark);font-weight:800}
    .cart{display:grid;gap:8px;margin-top:12px}
    .cart-item{display:grid;grid-template-columns:1fr auto;gap:8px;border:1px solid var(--line);border-radius:6px;background:var(--soft);padding:10px}
    .cart-item strong{font-size:13px}
    .cart-item small{display:block;color:var(--muted);font-size:11px;margin-top:4px}
    .qty{display:flex;align-items:center;gap:6px}
    .qty button{width:28px;height:28px;padding:0;border-radius:6px}
    .qty span{min-width:18px;text-align:center;font-weight:800}
    .totals{border-top:1px solid var(--line);margin-top:12px;padding-top:12px;display:grid;gap:8px}
    .total-row{display:flex;align-items:center;justify-content:space-between}
    .total-row strong{font-size:24px;color:var(--blue-dark)}
    .payment{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px}
    .pay{border:1px solid var(--line);border-radius:6px;background:var(--soft);padding:10px}
    .pay .label{font-size:11px;color:var(--muted)}
    .pay .value{font-size:18px;font-weight:800;color:var(--blue-dark);margin-top:5px}
    .actions{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px}
    @media(max-width:1180px){body{padding-left:0}.sidebar{position:static;width:auto}.layout{grid-template-columns:1fr}.products{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:640px){.content{padding:10px}.topbar{height:auto;align-items:flex-start;flex-direction:column;padding:12px}.products,.payment,.actions{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <aside class="sidebar">
    <div class="brand">
      <h1>小売販売システム</h1>
      <p>会社を選んで販売入力を行います。</p>
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
        <h2 id="title">○社 販売画面</h2>
        <div class="meta" id="meta">銀座店 / レジ02 / 担当: 佐藤</div>
      </div>
      <button class="btn">業務メニューへ戻る</button>
    </header>

    <section class="layout">
      <aside class="panel">
        <h3>販売条件</h3>
        <div class="meta">会社を変えると店舗と商品が切り替わります。</div>
        <div class="field">
          <label>店舗</label>
          <select id="store-select"></select>
        </div>
        <div class="field">
          <label>顧客</label>
          <input id="customer" value="酒舗 あかね屋">
        </div>
        <div class="field">
          <label>販売種別</label>
          <select>
            <option>通常販売</option>
            <option>法人請求</option>
            <option>予約受取</option>
          </select>
        </div>
        <div class="field">
          <label>メモ</label>
          <textarea id="memo">店舗受け取り。</textarea>
        </div>
        <div class="category">
          <button class="active">日本酒 <span>主</span></button>
          <button>ギフト <span>箱</span></button>
          <button>飲み切り <span>小</span></button>
        </div>
      </aside>

      <section class="panel">
        <h3>商品を選ぶ</h3>
        <div class="meta">商品を押して右のカートへ追加します。</div>
        <div class="products" id="products"></div>
      </section>

      <aside class="panel">
        <h3>カート・会計</h3>
        <div class="meta" id="cart-meta">○社 / 銀座店</div>
        <div class="cart" id="cart"></div>
        <div class="totals">
          <div class="total-row"><span>小計</span><b id="subtotal">¥8,740</b></div>
          <div class="total-row"><span>税額</span><b id="tax">¥874</b></div>
          <div class="total-row"><span>合計</span><strong id="total">¥9,614</strong></div>
        </div>
        <div class="payment">
          <div class="pay"><div class="label">現金</div><div class="value" id="cash">¥10,000</div></div>
          <div class="pay"><div class="label">おつり</div><div class="value" id="change">¥386</div></div>
          <div class="pay"><div class="label">カード</div><div class="value">¥0</div></div>
          <div class="pay"><div class="label">QR</div><div class="value">¥0</div></div>
        </div>
        <div class="actions">
          <button class="btn">保留</button>
          <button class="btn danger">取消</button>
          <button class="btn">返品</button>
          <button class="btn primary">会計確定</button>
        </div>
      </aside>
    </section>
  </main>

  <script>
    const companies = {
      maru: {
        name:'○社', meta:'銀座店 / レジ02 / 担当: 佐藤', customer:'酒舗 あかね屋', memo:'店舗受け取り。',
        stores:['銀座店','新宿店','横浜店'],
        products:[['純米吟醸 720ml','BP-1001 / 在庫あり','¥1,980'],['本醸造 300ml','BP-1008 / 在庫少','¥780'],['梅酒 500ml','BP-1012 / 定番','¥1,250'],['ギフト 2本箱','BP-2004 / 包装あり','¥4,400'],['しぼりたて 720ml','BP-1044 / 季節限定','¥2,310'],['飲み切りセット','BP-3007 / 3本組','¥1,650']],
        cart:[['純米吟醸 720ml','2本 / ¥3,960',2],['本醸造 300ml','2本 / ¥1,560',2],['ギフト 2本箱','1箱 / ¥4,400',1]],
        totals:['¥8,740','¥874','¥9,614','¥10,000','¥386']
      },
      batsu: {
        name:'×社', meta:'恵比寿店 / レジ01 / 担当: 高橋', customer:'ワインと肴 みつば', memo:'法人請求。月末締め。',
        stores:['恵比寿店','池袋店'],
        products:[['純米大吟醸 720ml','XC-2101 / 贈答向け','¥2,680'],['スパークリング清酒','XC-2120 / 冷蔵','¥1,420'],['飲み比べ3本セット','XC-2204 / 法人向け','¥3,900'],['吟醸 300ml','XC-2111 / 会食向け','¥860'],['梅酒 720ml','XC-2132 / 甘口','¥1,680'],['ギフト木箱','XC-2303 / 限定包装','¥5,200']],
        cart:[['純米大吟醸 720ml','1本 / ¥2,680',1],['飲み比べ3本セット','1組 / ¥3,900',1],['梅酒 720ml','2本 / ¥3,360',2]],
        totals:['¥9,940','¥994','¥10,934','¥11,000','¥66']
      },
      sankaku: {
        name:'▲社', meta:'自由が丘店 / レジ03 / 担当: 中村', customer:'地酒ダイニング さくら', memo:'夕方配送。贈答用が多い。',
        stores:['自由が丘店','二子玉川店','武蔵小杉店','立川店'],
        products:[['山廃純米 720ml','AC-3101 / 定番','¥1,880'],['限定原酒 500ml','AC-3114 / 数量限定','¥2,420'],['贈答セット 2本','AC-3202 / 化粧箱入り','¥4,980'],['純米吟醸 300ml','AC-3120 / 飲み切り','¥690'],['発泡清酒','AC-3130 / 食前酒向け','¥1,540'],['季節詰め合わせ','AC-3307 / 店舗限定','¥6,100']],
        cart:[['山廃純米 720ml','1本 / ¥1,880',1],['限定原酒 500ml','1本 / ¥2,420',1],['贈答セット 2本','1組 / ¥4,980',1]],
        totals:['¥9,280','¥928','¥10,208','¥20,000','¥9,792']
      }
    };
    const menu = [['販売画面','POS'],['店舗一覧','店舗'],['店舗在庫','在庫'],['補充発注','発注'],['締め・経理','締め']];
    const $ = (id) => document.getElementById(id);
    function render(key){
      const c = companies[key];
      $('title').textContent = `${c.name} 販売画面`;
      $('meta').textContent = c.meta;
      $('customer').value = c.customer;
      $('memo').value = c.memo;
      $('main-nav').innerHTML = menu.map(([label,short],i)=>`<a class="${i===0?'active':''}" href="#"><span>${c.name} ${label}</span><span>${short}</span></a>`).join('');
      $('store-select').innerHTML = c.stores.map((s)=>`<option>${s}</option>`).join('');
      $('cart-meta').textContent = `${c.name} / ${c.stores[0]}`;
      $('products').innerHTML = c.products.map(([name,meta,price])=>`<article class="product"><div class="thumb"></div><strong>${name}</strong><small>${meta}</small><div class="row"><span class="price">${price}</span><button class="btn primary">追加</button></div></article>`).join('');
      $('cart').innerHTML = c.cart.map(([name,meta,qty])=>`<div class="cart-item"><div><strong>${name}</strong><small>${meta}</small></div><div class="qty"><button class="btn">-</button><span>${qty}</span><button class="btn">+</button></div></div>`).join('');
      [$('subtotal').textContent,$('tax').textContent,$('total').textContent,$('cash').textContent,$('change').textContent] = c.totals;
    }
    $('company-select').addEventListener('change',()=>render($('company-select').value));
    render('maru');
  </script>
</body>
</html>
