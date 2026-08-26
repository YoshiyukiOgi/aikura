<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>小売側トップ画面</title>
  <style>
    :root{
      --bg:#f4f7fb;
      --panel:#ffffff;
      --panel-soft:#f8fbff;
      --line:#dbe4ef;
      --line-strong:#c8d4e2;
      --text:#162033;
      --muted:#66758c;
      --blue:#0b6ff6;
      --blue-dark:#075ecf;
      --mint:#198754;
      --amber:#b9770e;
      --rose:#b42318;
      --shadow:0 10px 30px rgba(24,38,60,.08);
      --radius:14px;
      --sidebar:#11243b;
      --sidebar-hover:#173356;
    }

    *{box-sizing:border-box}
    html,body{margin:0;min-height:100%;background:var(--bg);color:var(--text);font-family:Inter,"Noto Sans JP",system-ui,sans-serif}
    body{padding-left:226px}

    .sidebar{
      position:fixed;inset:0 auto 0 0;width:226px;background:linear-gradient(180deg,#10233a 0%, #0d1b2c 100%);
      color:#eaf1fb;box-shadow:2px 0 12px rgba(15,31,54,.12);padding:18px 12px;overflow:auto
    }
    .brand{
      padding:6px 10px 16px;border-bottom:1px solid rgba(255,255,255,.12)
    }
    .brand .eyebrow{display:inline-flex;align-items:center;gap:8px;color:#b8c6d9;font-size:11px;letter-spacing:.08em;text-transform:uppercase}
    .brand .eyebrow::before{content:"";width:8px;height:8px;border-radius:999px;background:#7dd3fc;box-shadow:0 0 0 4px rgba(125,211,252,.14)}
    .brand h1{margin:10px 0 0;font-size:16px;line-height:1.45;letter-spacing:.02em}
    .brand p{margin:6px 0 0;color:#91a4bd;font-size:12px;line-height:1.6}
    .nav{display:grid;gap:4px;margin-top:16px}
    .nav a{
      display:flex;align-items:center;justify-content:space-between;gap:10px;
      min-height:38px;padding:0 12px;border-radius:10px;color:#dbe7f5;text-decoration:none;font-size:12px
    }
    .nav a:hover{background:var(--sidebar-hover)}
    .nav a.active{background:var(--blue);color:#fff;font-weight:800;box-shadow:inset 3px 0 0 rgba(255,255,255,.45)}
    .nav .section{margin:14px 10px 6px;color:#7f95af;font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}

    .content{padding:18px 18px 26px}
    .topbar{
      display:flex;align-items:center;justify-content:space-between;gap:16px;
      min-height:54px;padding:0 18px;margin-bottom:14px;background:var(--panel);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow)
    }
    .topbar h2{margin:0;font-size:15px}
    .topbar .meta{color:var(--muted);font-size:12px}
    .top-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .pill{
      display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid var(--line);border-radius:999px;background:#fff;font-size:12px;color:#35506f
    }
    .pill strong{font-weight:800;color:#18263b}
    .btn{
      border:1px solid var(--line-strong);border-radius:10px;background:#fff;color:#1f3550;padding:8px 12px;font:inherit;font-size:12px;cursor:pointer
    }
    .btn.primary{border-color:var(--blue);background:var(--blue);color:#fff;font-weight:800}

    .hero{
      display:grid;grid-template-columns:minmax(0,1.45fr) minmax(290px,.9fr);gap:14px;align-items:stretch;margin-bottom:14px
    }
    .panel{
      background:var(--panel);border:1px solid var(--line);border-radius:18px;box-shadow:var(--shadow)
    }
    .hero-main{padding:20px;position:relative;overflow:hidden;background:
      linear-gradient(135deg,rgba(11,111,246,.08),transparent 38%),
      linear-gradient(180deg,#ffffff 0%,#fbfdff 100%)}
    .hero-main::after{
      content:"";position:absolute;right:-44px;top:-44px;width:180px;height:180px;border-radius:50%;
      background:radial-gradient(circle,rgba(11,111,246,.12),transparent 64%);pointer-events:none
    }
    .eyebrow{
      display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#eef5ff;border:1px solid #d9e9ff;
      color:var(--blue-dark);font-size:12px;font-weight:700
    }
    .eyebrow::before{content:"";width:8px;height:8px;border-radius:999px;background:#21c17a}
    .hero-main h3{
      margin:14px 0 10px;font-size:28px;line-height:1.15;letter-spacing:.01em;max-width:13ch
    }
    .hero-main p{margin:0;color:var(--muted);font-size:13px;line-height:1.8;max-width:58ch}
    .hero-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:18px}
    .kpi{
      padding:14px;border:1px solid var(--line);border-radius:14px;background:#fff;min-height:92px
    }
    .kpi .label{color:var(--muted);font-size:11px;letter-spacing:.04em}
    .kpi .value{margin-top:8px;font-size:24px;font-weight:800;letter-spacing:-.02em}
    .kpi .sub{margin-top:5px;color:var(--muted);font-size:11px;line-height:1.5}
    .hero-side{padding:18px}
    .hero-side h4,.section-head h4{margin:0;font-size:16px}
    .side-note{margin:6px 0 0;color:var(--muted);font-size:12px;line-height:1.7}
    .store-list{display:grid;gap:10px;margin-top:14px}
    .store-card{
      padding:14px;border:1px solid var(--line);border-radius:14px;background:var(--panel-soft)
    }
    .store-card .row{display:flex;align-items:center;justify-content:space-between;gap:12px}
    .store-card .name{font-weight:800}
    .store-card .status{font-size:11px;padding:5px 8px;border-radius:999px;background:#edf7ef;color:var(--mint);font-weight:800}
    .store-card .status.warn{background:#fff4e5;color:var(--amber)}
    .store-card .status.alert{background:#feeceb;color:var(--rose)}
    .store-card .meta{margin-top:6px;color:var(--muted);font-size:12px;line-height:1.6}

    .grid{
      display:grid;grid-template-columns:minmax(0,1.1fr) minmax(320px,.9fr);gap:14px;align-items:start
    }
    .section{
      padding:18px
    }
    .section-head{
      display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px
    }
    .section-head .meta{color:var(--muted);font-size:12px}

    .table{
      width:100%;border-collapse:collapse;table-layout:fixed
    }
    .table th,.table td{
      padding:11px 10px;border-bottom:1px solid #edf1f6;text-align:left;font-size:12px;vertical-align:middle
    }
    .table th{color:var(--muted);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;background:#fbfcfe}
    .table tr:hover td{background:#f8fbff}
    .table .num{text-align:right;font-variant-numeric:tabular-nums}
    .tag{
      display:inline-flex;align-items:center;padding:5px 8px;border-radius:999px;font-size:11px;font-weight:800
    }
    .tag.ok{background:#edf7ef;color:var(--mint)}
    .tag.warn{background:#fff4e5;color:var(--amber)}
    .tag.alert{background:#feeceb;color:var(--rose)}
    .tag.info{background:#eef5ff;color:var(--blue-dark)}

    .cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .mini-card{
      border:1px solid var(--line);border-radius:14px;padding:14px;background:#fff
    }
    .mini-card h5{margin:0 0 8px;font-size:14px}
    .mini-card p{margin:0;color:var(--muted);font-size:12px;line-height:1.7}
    .bar-list{display:grid;gap:10px;margin-top:14px}
    .bar-row{display:grid;grid-template-columns:110px minmax(0,1fr) 60px;gap:10px;align-items:center}
    .bar-row .label{font-size:12px;color:#29415c}
    .bar{height:10px;border-radius:999px;background:#e8eef6;overflow:hidden}
    .bar span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#7cc4ff 0%,#0b6ff6 100%)}
    .bar-row .value{text-align:right;font-size:12px;font-variant-numeric:tabular-nums;color:#20324a}

    .tasks{display:grid;gap:10px}
    .task{
      display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:14px;border:1px solid var(--line);border-radius:14px;background:#fff
    }
    .task strong{display:block;margin-bottom:5px;font-size:13px}
    .task span{color:var(--muted);font-size:12px;line-height:1.6}
    .task .priority{font-size:11px;font-weight:800;padding:5px 8px;border-radius:999px;background:#fff4e5;color:var(--amber);white-space:nowrap}
    .task .priority.high{background:#feeceb;color:var(--rose)}
    .task .priority.low{background:#edf7ef;color:var(--mint)}

    .footer-note{margin-top:14px;color:var(--muted);font-size:12px;line-height:1.7}

    @media (max-width: 1160px){
      body{padding-left:0}
      .sidebar{display:none}
      .hero,.grid{grid-template-columns:1fr}
      .hero-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media (max-width: 720px){
      .content{padding:12px}
      .topbar,.hero-main,.hero-side,.section{border-radius:16px;padding:14px}
      .hero-kpis,.cards{grid-template-columns:1fr}
      .topbar{flex-direction:column;align-items:flex-start}
      .bar-row{grid-template-columns:1fr}
      .bar-row .value{text-align:left}
      .section-head{flex-direction:column;align-items:flex-start}
    }
  </style>
</head>
<body>
  <aside class="sidebar" aria-label="小売メニュー">
    <div class="brand">
      <div class="eyebrow">Retail console</div>
      <h1>小売販売システム<br>本部トップ</h1>
      <p>会社単位の会計と、店舗単位の運営を分けて見る画面。</p>
    </div>
    <nav class="nav">
      <a href="#" class="active"><span>ダッシュボード</span><span>Home</span></a>
      <a href="#"><span>店舗一覧</span><span>8</span></a>
      <a href="#"><span>売上・レジ</span><span>Today</span></a>
      <a href="#"><span>補充発注</span><span>Auto</span></a>
      <a href="#"><span>店舗在庫</span><span>Stock</span></a>
      <a href="#"><span>商品同期</span><span>Sync</span></a>
      <a href="#"><span>締め・経理</span><span>Close</span></a>
      <div class="section">Tenant</div>
      <a href="#"><span>会社 A</span><span>本部</span></a>
      <a href="#"><span>会社 B</span><span>別会計</span></a>
    </nav>
  </aside>

  <main class="content">
    <header class="topbar">
      <div>
        <h2>小売本部トップ</h2>
        <div class="meta">2026年7月31日 / 会社 A / 3店舗運営中</div>
      </div>
      <div class="top-actions">
        <span class="pill">自動補充 <strong>ON</strong></span>
        <span class="pill">最終同期 <strong>08:45</strong></span>
        <button class="btn">店舗切替</button>
        <button class="btn primary">新規発注</button>
      </div>
    </header>

    <section class="hero" aria-label="トップ概要">
      <div class="panel hero-main">
        <span class="eyebrow">Today at a glance</span>
        <h3>今日の売上、在庫、発注がひと目でわかる</h3>
        <p>
          酒蔵側へ送る補充発注の判断材料を最初に集約する画面です。
          会社単位の数字と、店舗ごとの動きの両方を見せます。
        </p>

        <div class="hero-kpis">
          <div class="kpi">
            <div class="label">本日売上</div>
            <div class="value">¥1,284,600</div>
            <div class="sub">前日比 +8.4%</div>
          </div>
          <div class="kpi">
            <div class="label">店舗数</div>
            <div class="value">3</div>
            <div class="sub">営業中 3 / 休業 0</div>
          </div>
          <div class="kpi">
            <div class="label">要補充SKU</div>
            <div class="value">12</div>
            <div class="sub">蔵へ自動発注候補あり</div>
          </div>
          <div class="kpi">
            <div class="label">未締め伝票</div>
            <div class="value">27</div>
            <div class="sub">本部締め待ち</div>
          </div>
        </div>
      </div>

      <aside class="panel hero-side">
        <h4>店舗の状態</h4>
        <p class="side-note">会社配下の各店舗を、売上と在庫の気配で並べる。</p>
        <div class="store-list">
          <div class="store-card">
            <div class="row">
              <div class="name">銀座店</div>
              <div class="status">営業中</div>
            </div>
            <div class="meta">売上 ¥612,000 / 欠品 2 / 補充候補 5</div>
          </div>
          <div class="store-card">
            <div class="row">
              <div class="name">新宿店</div>
              <div class="status warn">注意</div>
            </div>
            <div class="meta">売上 ¥411,500 / 低在庫SKU 7 / 発注待ち 1</div>
          </div>
          <div class="store-card">
            <div class="row">
              <div class="name">横浜店</div>
              <div class="status alert">要確認</div>
            </div>
            <div class="meta">売上 ¥261,100 / 棚卸差異あり / 締め未完了</div>
          </div>
        </div>
      </aside>
    </section>

    <section class="grid">
      <article class="panel section">
        <div class="section-head">
          <div>
            <h4>売上推移</h4>
            <div class="meta">当月の店舗別売上。青い帯が強い店舗です。</div>
          </div>
          <span class="tag info">month to date</span>
        </div>
        <div class="bar-list">
          <div class="bar-row">
            <div class="label">銀座店</div>
            <div class="bar"><span style="width:86%"></span></div>
            <div class="value">86%</div>
          </div>
          <div class="bar-row">
            <div class="label">新宿店</div>
            <div class="bar"><span style="width:68%"></span></div>
            <div class="value">68%</div>
          </div>
          <div class="bar-row">
            <div class="label">横浜店</div>
            <div class="bar"><span style="width:49%"></span></div>
            <div class="value">49%</div>
          </div>
          <div class="bar-row">
            <div class="label">川崎店</div>
            <div class="bar"><span style="width:31%"></span></div>
            <div class="value">31%</div>
          </div>
        </div>

        <div style="margin-top:18px" class="cards">
          <div class="mini-card">
            <h5>自動発注ルール</h5>
            <p>低在庫が閾値を下回ると、酒蔵側の商品コードへ発注候補を送信。</p>
          </div>
          <div class="mini-card">
            <h5>本部締め</h5>
            <p>会社単位で未締め伝票を集約し、店舗ごとの差異だけを確認する。</p>
          </div>
        </div>
      </article>

      <aside class="panel section">
        <div class="section-head">
          <div>
            <h4>今日のアラート</h4>
            <div class="meta">現場が先に見るべきものを上に置く。</div>
          </div>
          <span class="tag warn">5件</span>
        </div>
        <div class="tasks">
          <div class="task">
            <div>
              <strong>新宿店の冷蔵棚が低在庫</strong>
              <span>清酒 720ml 2SKU が補充ラインを下回っています。</span>
            </div>
            <div class="priority high">高</div>
          </div>
          <div class="task">
            <div>
              <strong>横浜店の締めが未完了</strong>
              <span>本部締め前に売上伝票 8 件の確認が必要です。</span>
            </div>
            <div class="priority">中</div>
          </div>
          <div class="task">
            <div>
              <strong>銀座店の自動補充待ち</strong>
              <span>酒蔵へ送る発注候補を今日中に確定できます。</span>
            </div>
            <div class="priority low">低</div>
          </div>
        </div>
      </aside>
    </section>

    <section class="grid" style="margin-top:14px">
      <article class="panel section">
        <div class="section-head">
          <div>
            <h4>発注候補</h4>
            <div class="meta">在庫不足と売れ筋から、蔵へ送る内容をまとめる。</div>
          </div>
          <span class="tag ok">自動候補 12</span>
        </div>
        <table class="table" aria-label="発注候補一覧">
          <thead>
            <tr>
              <th>店舗</th>
              <th>商品</th>
              <th class="num">現有</th>
              <th class="num">推奨</th>
              <th>状態</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>銀座店</td>
              <td>純米吟醸 720ml</td>
              <td class="num">8</td>
              <td class="num">24</td>
              <td><span class="tag warn">要発注</span></td>
            </tr>
            <tr>
              <td>新宿店</td>
              <td>本醸造 300ml</td>
              <td class="num">5</td>
              <td class="num">18</td>
              <td><span class="tag info">候補</span></td>
            </tr>
            <tr>
              <td>横浜店</td>
              <td>梅酒 500ml</td>
              <td class="num">3</td>
              <td class="num">12</td>
              <td><span class="tag alert">欠品注意</span></td>
            </tr>
          </tbody>
        </table>
      </article>

      <aside class="panel section">
        <div class="section-head">
          <div>
            <h4>本部メモ</h4>
            <div class="meta">会社単位で見る要点だけを残す。</div>
          </div>
        </div>
        <div class="cards" style="grid-template-columns:1fr">
          <div class="mini-card">
            <h5>酒蔵連携</h5>
            <p>商品コードと店舗コードで発注を送る。DB直結はしない。</p>
          </div>
          <div class="mini-card">
            <h5>複数会社</h5>
            <p>会社が違えば経理も締めも独立。店舗はその下位で扱う。</p>
          </div>
          <div class="mini-card">
            <h5>次の画面</h5>
            <p>店舗一覧、発注画面、締め画面の順でつなぐと分かりやすい。</p>
          </div>
        </div>
      </aside>
    </section>

    <p class="footer-note">このトップ画面は、実際に開いたときの「何を見るか」がすぐ分かるように組んであります。必要なら次に店舗一覧か発注画面を続けます。</p>
  </main>
</body>
</html>
