<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>酒蔵×小売 アーキテクチャ案</title>
  <style>
    :root {
      --bg: #0e1117;
      --panel: rgba(17, 23, 35, 0.84);
      --panel-strong: rgba(24, 32, 49, 0.92);
      --line: rgba(255, 255, 255, 0.12);
      --text: #f5f7fb;
      --muted: #a8b3c7;
      --cream: #f3e6cf;
      --amber: #f0b35f;
      --gold: #d8a84f;
      --rose: #d68171;
      --green: #6dc7a1;
      --blue: #76a9ff;
      --shadow: 0 26px 80px rgba(0, 0, 0, 0.42);
    }

    * { box-sizing: border-box; }

    html, body {
      margin: 0;
      min-height: 100%;
      background:
        radial-gradient(circle at top left, rgba(109, 199, 161, 0.18), transparent 25%),
        radial-gradient(circle at top right, rgba(240, 179, 95, 0.16), transparent 22%),
        radial-gradient(circle at 50% 20%, rgba(118, 169, 255, 0.16), transparent 20%),
        linear-gradient(180deg, #0b0e13 0%, #0f131b 45%, #0d1117 100%);
      color: var(--text);
      font-family: "Hiragino Kaku Gothic ProN", "Yu Gothic", "Noto Sans JP", system-ui, sans-serif;
    }

    body::before {
      content: "";
      position: fixed;
      inset: 0;
      background-image:
        linear-gradient(rgba(255,255,255,0.035) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.035) 1px, transparent 1px);
      background-size: 42px 42px;
      mask-image: linear-gradient(180deg, rgba(0,0,0,0.55), transparent 85%);
      pointer-events: none;
    }

    .page {
      position: relative;
      max-width: 1440px;
      margin: 0 auto;
      padding: 28px 24px 40px;
    }

    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 22px;
      color: var(--muted);
      font-size: 14px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      color: var(--text);
      font-weight: 700;
      letter-spacing: 0.02em;
      text-transform: none;
    }

    .brand-mark {
      width: 38px;
      height: 38px;
      border-radius: 12px;
      background:
        linear-gradient(135deg, rgba(240, 179, 95, 0.96), rgba(109, 199, 161, 0.84));
      box-shadow: 0 10px 30px rgba(240, 179, 95, 0.22);
      position: relative;
      overflow: hidden;
    }

    .brand-mark::after {
      content: "";
      position: absolute;
      inset: 9px 11px 10px;
      border-radius: 999px 999px 12px 12px;
      border: 2px solid rgba(14, 17, 23, 0.5);
      background: rgba(255,255,255,0.16);
    }

    .hero {
      display: grid;
      grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr);
      gap: 20px;
      margin-bottom: 20px;
    }

    .hero-main,
    .hero-side,
    .panel,
    .summary,
    .flow-item,
    .node,
    .store-card,
    .company-card,
    .legend {
      border: 1px solid var(--line);
      background: var(--panel);
      backdrop-filter: blur(14px);
      box-shadow: var(--shadow);
    }

    .hero-main {
      border-radius: 30px;
      padding: 34px;
      position: relative;
      overflow: hidden;
      min-height: 360px;
      background:
        linear-gradient(135deg, rgba(22, 28, 41, 0.98), rgba(11, 15, 22, 0.88)),
        radial-gradient(circle at right 20% bottom 10%, rgba(240, 179, 95, 0.2), transparent 24%),
        radial-gradient(circle at left top, rgba(109, 199, 161, 0.2), transparent 26%);
    }

    .hero-main::before,
    .hero-main::after,
    .hero-side::before {
      content: "";
      position: absolute;
      border-radius: 999px;
      filter: blur(2px);
      pointer-events: none;
    }

    .hero-main::before {
      width: 220px;
      height: 220px;
      right: -48px;
      top: -56px;
      background: radial-gradient(circle, rgba(240, 179, 95, 0.25), transparent 65%);
    }

    .hero-main::after {
      width: 280px;
      height: 280px;
      left: 42%;
      bottom: -145px;
      background: radial-gradient(circle, rgba(118, 169, 255, 0.2), transparent 68%);
    }

    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 8px 14px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.06);
      color: var(--cream);
      font-size: 13px;
      letter-spacing: 0.08em;
    }

    .eyebrow::before {
      content: "";
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--green);
      box-shadow: 0 0 0 4px rgba(109, 199, 161, 0.15);
    }

    h1 {
      margin: 18px 0 12px;
      max-width: 10ch;
      font-family: "Yu Mincho", "Hiragino Mincho ProN", serif;
      font-size: clamp(42px, 6vw, 78px);
      line-height: 0.96;
      letter-spacing: 0.02em;
    }

    .lead {
      max-width: 54ch;
      margin: 0;
      color: var(--muted);
      font-size: 16px;
      line-height: 1.8;
    }

    .hero-stats {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 12px;
      margin-top: 26px;
      position: relative;
      z-index: 1;
    }

    .stat {
      border-radius: 18px;
      padding: 14px 16px;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.08);
    }

    .stat .label {
      color: var(--muted);
      font-size: 12px;
      letter-spacing: 0.08em;
    }

    .stat .value {
      margin-top: 6px;
      font-size: 20px;
      font-weight: 700;
    }

    .hero-side {
      border-radius: 30px;
      padding: 24px;
      position: relative;
      overflow: hidden;
      background:
        linear-gradient(180deg, rgba(31, 39, 57, 0.98), rgba(14, 18, 28, 0.92));
    }

    .hero-side::before {
      width: 170px;
      height: 170px;
      right: -40px;
      top: -50px;
      background: radial-gradient(circle, rgba(216, 168, 79, 0.22), transparent 66%);
    }

    .side-title {
      margin: 0 0 14px;
      font-size: 18px;
      letter-spacing: 0.04em;
    }

    .system-map {
      display: grid;
      gap: 12px;
    }

    .map-row {
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      gap: 10px;
      align-items: center;
    }

    .tag {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border-radius: 14px;
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.08);
      font-size: 13px;
      white-space: nowrap;
    }

    .tag strong {
      font-size: 14px;
    }

    .arrow {
      width: 30px;
      height: 2px;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.7), transparent);
      position: relative;
    }

    .arrow::after {
      content: "";
      position: absolute;
      right: -1px;
      top: -3px;
      border-left: 8px solid rgba(255,255,255,0.7);
      border-top: 4px solid transparent;
      border-bottom: 4px solid transparent;
    }

    .tag.gold { background: rgba(240, 179, 95, 0.12); }
    .tag.green { background: rgba(109, 199, 161, 0.12); }
    .tag.blue { background: rgba(118, 169, 255, 0.12); }
    .tag.rose { background: rgba(214, 129, 113, 0.12); }

    .content {
      display: grid;
      gap: 20px;
    }

    .grid-3 {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 18px;
    }

    .panel {
      border-radius: 24px;
      padding: 22px;
      min-height: 100%;
    }

    .panel h2 {
      margin: 0 0 14px;
      font-size: 18px;
      letter-spacing: 0.03em;
    }

    .panel p {
      margin: 0;
      color: var(--muted);
      line-height: 1.8;
      font-size: 14px;
    }

    .node-list {
      display: grid;
      gap: 10px;
      margin-top: 16px;
    }

    .node {
      border-radius: 18px;
      padding: 14px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      background: var(--panel-strong);
    }

    .node .name {
      font-weight: 700;
    }

    .node .meta {
      color: var(--muted);
      font-size: 13px;
      text-align: right;
    }

    .flow {
      display: grid;
      gap: 12px;
      margin-top: 18px;
    }

    .flow-item {
      border-radius: 18px;
      padding: 14px 16px;
      display: flex;
      justify-content: space-between;
      gap: 16px;
      background: rgba(255,255,255,0.04);
    }

    .flow-item strong {
      display: block;
      margin-bottom: 4px;
    }

    .flow-item span {
      color: var(--muted);
      font-size: 13px;
      line-height: 1.6;
    }

    .summary {
      border-radius: 28px;
      padding: 22px;
      background:
        linear-gradient(135deg, rgba(22, 28, 41, 0.97), rgba(18, 22, 31, 0.94)),
        radial-gradient(circle at top right, rgba(118, 169, 255, 0.16), transparent 22%);
    }

    .summary-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 16px;
    }

    .summary-head h2 {
      margin: 0;
      font-size: 18px;
    }

    .badge {
      padding: 8px 12px;
      border-radius: 999px;
      background: rgba(240, 179, 95, 0.12);
      color: #ffdca9;
      border: 1px solid rgba(240, 179, 95, 0.22);
      font-size: 12px;
      letter-spacing: 0.08em;
    }

    .two-col {
      display: grid;
      grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
      gap: 18px;
    }

    .store-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
      margin-top: 14px;
    }

    .store-card,
    .company-card,
    .legend {
      border-radius: 18px;
      padding: 14px;
      background: rgba(255,255,255,0.04);
    }

    .store-card h3,
    .company-card h3,
    .legend h3 {
      margin: 0 0 8px;
      font-size: 15px;
    }

    .store-card p,
    .company-card p,
    .legend p {
      margin: 0;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.7;
    }

    .legend {
      margin-top: 14px;
      background: rgba(255,255,255,0.03);
    }

    .legend-row {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 10px;
    }

    .chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border-radius: 999px;
      padding: 8px 12px;
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.08);
      font-size: 12px;
      color: var(--text);
    }

    .chip::before {
      content: "";
      width: 8px;
      height: 8px;
      border-radius: 999px;
      background: var(--amber);
    }

    .chip.green::before { background: var(--green); }
    .chip.blue::before { background: var(--blue); }
    .chip.rose::before { background: var(--rose); }

    .footer-note {
      margin-top: 18px;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.8;
    }

    @media (max-width: 1100px) {
      .hero,
      .two-col,
      .grid-3 {
        grid-template-columns: 1fr;
      }

      .hero-main {
        min-height: auto;
      }
    }

    @media (max-width: 720px) {
      .page { padding: 18px 14px 24px; }
      .topbar { flex-direction: column; align-items: flex-start; }
      .hero-main, .hero-side, .panel, .summary { border-radius: 22px; padding: 18px; }
      .hero-stats, .store-grid { grid-template-columns: 1fr; }
      h1 { max-width: none; }
      .map-row { grid-template-columns: 1fr; }
      .arrow { justify-self: center; width: 2px; height: 20px; }
      .arrow::after { right: -3px; top: auto; bottom: -1px; border-left: 4px solid transparent; border-right: 4px solid transparent; border-top: 8px solid rgba(255,255,255,0.7); border-bottom: 0; }
    }
  </style>
</head>
<body>
  <main class="page">
    <header class="topbar">
      <div class="brand">
        <div class="brand-mark" aria-hidden="true"></div>
        <div>酒蔵販売業務システム / 小売販売システム 構成案</div>
      </div>
      <div>Subdomain / Multi-tenant / API-linked</div>
    </header>

    <section class="hero" aria-label="システム構成の要約">
      <div class="hero-main">
        <span class="eyebrow">Brewery as source of truth</span>
        <h1>蔵を正本に、小売は店舗運営に集中する</h1>
        <p class="lead">
          酒蔵側は商品・製造・出荷・受注を担い、小売側は複数会社・複数店舗を前提に売上と在庫を扱う。
          両者はサブドメインとDBを分け、商品連携と自動発注だけをAPIでつなぐ。
        </p>

        <div class="hero-stats">
          <div class="stat">
            <div class="label">蔵側の役割</div>
            <div class="value">商品 / 出荷 / 税務</div>
          </div>
          <div class="stat">
            <div class="label">小売側の役割</div>
            <div class="value">店舗 / 売上 / 締め</div>
          </div>
          <div class="stat">
            <div class="label">連携方式</div>
            <div class="value">API + Webhook</div>
          </div>
        </div>
      </div>

      <aside class="hero-side">
        <h2 class="side-title">サブドメインの見え方</h2>
        <div class="system-map">
          <div class="map-row">
            <div class="tag gold"><strong>brew.</strong> example.com</div>
            <div class="arrow" aria-hidden="true"></div>
            <div class="tag green"><strong>brewery_db</strong> 正本</div>
          </div>
          <div class="map-row">
            <div class="tag blue"><strong>retail.</strong> example.com</div>
            <div class="arrow" aria-hidden="true"></div>
            <div class="tag rose"><strong>retail_db</strong> テナント別</div>
          </div>
          <div class="map-row">
            <div class="tag green"><strong>api-brew.</strong> example.com</div>
            <div class="arrow" aria-hidden="true"></div>
            <div class="tag gold"><strong>発注 / 参照</strong> 接続口</div>
          </div>
        </div>
      </aside>
    </section>

    <section class="content">
      <div class="grid-3">
        <article class="panel">
          <h2>蔵側の責務</h2>
          <p>商品マスター、価格、製造計画、出荷、税務、受注引当を一元管理する。小売からの発注はここで確定する。</p>
          <div class="node-list">
            <div class="node">
              <div>
                <div class="name">商品正本</div>
                <div class="meta">コード、規格、税区分、廃番</div>
              </div>
              <div class="meta">BREW</div>
            </div>
            <div class="node">
              <div>
                <div class="name">発注受付</div>
                <div class="meta">冪等性キー付きの受注</div>
              </div>
              <div class="meta">ORDER</div>
            </div>
            <div class="node">
              <div>
                <div class="name">イベント配信</div>
                <div class="meta">出荷確定、欠品、取消を通知</div>
              </div>
              <div class="meta">EVENT</div>
            </div>
          </div>
        </article>

        <article class="panel">
          <h2>小売側の責務</h2>
          <p>小売会社ごとにテナントを分け、会社配下に複数店舗を持つ。会計や締めは会社単位で閉じる。</p>
          <div class="node-list">
            <div class="node">
              <div>
                <div class="name">複数会社対応</div>
                <div class="meta">経理単位ごとに独立</div>
              </div>
              <div class="meta">TENANT</div>
            </div>
            <div class="node">
              <div>
                <div class="name">店舗運営</div>
                <div class="meta">店舗在庫、売上、補充発注</div>
              </div>
              <div class="meta">STORE</div>
            </div>
            <div class="node">
              <div>
                <div class="name">自動発注</div>
                <div class="meta">在庫閾値で蔵へ送信</div>
              </div>
              <div class="meta">AUTO</div>
            </div>
          </div>
        </article>

        <article class="panel">
          <h2>連携の境界</h2>
          <p>DBは共有しない。共通化するのはコード体系だけにして、更新の起点を明確に保つ。</p>
          <div class="flow">
            <div class="flow-item">
              <div>
                <strong>商品同期</strong>
                <span>蔵 → 小売の定期取得。商品、価格、出荷可否を配信。</span>
              </div>
              <div class="meta">SYNC</div>
            </div>
            <div class="flow-item">
              <div>
                <strong>発注連携</strong>
                <span>小売 → 蔵の同期API。店舗単位の発注を送信。</span>
              </div>
              <div class="meta">API</div>
            </div>
            <div class="flow-item">
              <div>
                <strong>結果通知</strong>
                <span>蔵 → 小売のWebhook。引当、出荷、欠品を通知。</span>
              </div>
              <div class="meta">HOOK</div>
            </div>
          </div>
        </article>
      </div>

      <section class="summary">
        <div class="summary-head">
          <h2>小売は会社単位で分離、店舗はその下にぶら下げる</h2>
          <div class="badge">Multi-tenant retail</div>
        </div>

        <div class="two-col">
          <div>
            <p class="lead" style="max-width:none;">
              この案では、経理が別の小売会社が増えても、同じ小売基盤の中で会社ごとにテナントを切れる。
              そのため、将来は店舗数の増加よりも「会社数の増加」に強い構造になる。
            </p>

            <div class="store-grid">
              <div class="store-card">
                <h3>小売会社 A</h3>
                <p>本部経理あり。3店舗を運営。酒蔵へは自動補充発注。</p>
              </div>
              <div class="store-card">
                <h3>小売会社 B</h3>
                <p>別会計で独立。POS運用のみ共通テンプレートを利用。</p>
              </div>
              <div class="store-card">
                <h3>店舗 01 / 02 / 03</h3>
                <p>店舗単位の在庫と価格を保持。売上は会社単位に集計。</p>
              </div>
              <div class="store-card">
                <h3>酒蔵との接続</h3>
                <p>商品コード・店舗コード・発注番号を共通キーとして使用。</p>
              </div>
            </div>
          </div>

          <div>
            <div class="company-card">
              <h3>実装の要点</h3>
              <p>
                画面は `brew.` と `retail.` で完全分離し、DBも別にする。
                連携は API と Webhook に限定し、商品マスターだけは蔵側を正本にする。
              </p>
            </div>

            <div class="legend">
              <h3>キーの考え方</h3>
              <p>システム間では数値IDを共有せず、コードベースで統一する。</p>
              <div class="legend-row">
                <span class="chip">product_code</span>
                <span class="chip green">store_code</span>
                <span class="chip blue">retail_company_code</span>
                <span class="chip rose">purchase_order_no</span>
              </div>
            </div>
          </div>
        </div>

        <p class="footer-note">
          この画面はコンセプト確認用の1枚絵です。必要なら次に、同じ方向性で「蔵側トップ」「小売トップ」「店舗管理画面」まで分けた
          3枚構成に展開できます。
        </p>
      </section>
    </section>
  </main>
</body>
</html>
