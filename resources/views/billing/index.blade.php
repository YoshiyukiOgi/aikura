@php
  $titles = [
    'dashboard' => '請求・入金',
    'monthly-invoices' => '月次(締め)請求作成',
    'spot-invoices' => '都度請求作成',
    'invoices' => '請求一覧',
    'invoice-print' => '再請求書印刷',
    'payment-confirmation' => '入金確認',
    'payment-reviews' => '要確認入金',
    'receivables' => '売掛残高',
  ];
  $pageTitle = $titles[$section] ?? $titles['dashboard'];
@endphp
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $pageTitle }} | 販売管理</title>
  <style>
    body{margin:0;background:#f5f7fb;color:#172033;font:13px Inter,"Noto Sans JP",system-ui,sans-serif}
    [hidden]{display:none!important}
    header{height:52px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;background:#fff;border-bottom:1px solid #dce4ee}
    header h1{font-size:15px;font-weight:800;padding-left:10px;letter-spacing:0}
    main{padding:12px 18px}
    h1,h2,h3,p{margin:0}
    h2{font-size:15px}
    h3{font-size:13px}
    .stack{display:grid;gap:12px}
    .grid{display:grid;grid-template-columns:minmax(700px,1fr) 380px;gap:12px;align-items:start}
    .summary-grid{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:10px}
    .card{background:#fff;border:1px solid #dce4ee;border-radius:6px;overflow:hidden}
    .head{padding:11px 14px;border-bottom:1px solid #e7edf4;display:flex;justify-content:space-between;align-items:center;gap:10px}
    .panel{padding:12px 14px;display:grid;gap:10px}
    .filters{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:9px;padding:12px 14px;border-bottom:1px solid #e7edf4;background:#fbfdff}
    .filters .wide{grid-column:span 2}
    .review-filters{grid-template-columns:minmax(180px,1.5fr) minmax(116px,1fr) minmax(116px,1fr) minmax(116px,1fr) minmax(118px,.9fr)}
    .review-filters .wide{grid-column:auto}
    .muted{color:#64748b;font-size:11px}
    .notice{min-height:17px;color:#64748b;font-size:11px}
    .error{color:#b42318}
    .actions{display:flex;gap:8px;justify-content:flex-end;align-items:center;flex-wrap:wrap}
    .left-actions{justify-content:flex-start}
    table{width:100%;border-collapse:collapse;table-layout:fixed}
    th,td{padding:9px 10px;border-bottom:1px solid #edf1f6;text-align:left;font-size:11px;vertical-align:middle;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    th{background:#f8faff;color:#64748b;font-size:10px}
    tbody tr{cursor:pointer}
    tbody tr:hover,tbody tr.selected{background:#e7f0ff}
    tbody tr.selected{box-shadow:inset 3px 0 #0b6ff6}
    .num{text-align:right}
    .center{text-align:center}
    .badge{display:inline-block;max-width:100%;padding:2px 6px;border-radius:999px;background:#eaf3ff;color:#075ecf;font-size:10px;font-weight:800;overflow:hidden;text-overflow:ellipsis;vertical-align:middle}
    .badge.warn{background:#fff4dd;color:#9a6700}
    .badge.success{background:#e7f7ed;color:#137333}
    .badge.danger-badge{background:#fff1f2;color:#b42318}
    .badge.schedule-missing{background:#fff4dd;color:#9a6700}
    .status-stack{display:flex;align-items:center;gap:4px;min-width:0}
    .status-stack .badge{max-width:none}
    .invoice-date-col{width:74px}
    .invoice-total-col{width:74px}
    .invoice-status-col{width:172px}
    .badge.reason{margin-left:4px;background:#fff7ed;color:#c2410c}
    .badge.reason.short{background:#fff4dd;color:#9a6700}
    .badge.reason.target{background:#eef4fb;color:#475569}
    label{display:grid;gap:4px;color:#475569;font-size:10px;font-weight:700}
    input,select,textarea,button{font:inherit;font-size:11px}
    input,select,textarea{width:100%;min-height:30px;border:1px solid #ced8e5;border-radius:4px;padding:5px 8px;background:#fff;color:#172033;box-sizing:border-box}
    input[type="checkbox"]{width:auto;min-height:auto}
    textarea{min-height:56px;resize:vertical}
    button{cursor:pointer;border:1px solid #cad6e6;border-radius:4px;background:#fff;color:#25344a;padding:7px 9px}
    button:disabled{opacity:.45;cursor:not-allowed}
    .primary{background:#0b6ff6;color:#fff;border-color:#0b6ff6;font-weight:800}
    .danger{color:#b42318;border-color:#f0b7b1}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:9px}
    .full{grid-column:1/-1}
    .metric{display:grid;gap:4px;padding:12px 14px}
    .metric .value{font-size:20px;font-weight:800;color:#075ecf}
    .inline-check{display:flex;align-items:center;gap:6px;font-size:11px}
    .detail-list{display:grid;gap:6px}
    .detail-row{display:flex;justify-content:space-between;gap:10px;border-bottom:1px solid #edf1f6;padding-bottom:6px;font-size:11px}
    .detail-row strong{font-size:11px}
    .bulk-bar{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 14px;border-top:1px solid #edf1f6;background:#fbfdff}
    .invoice-group td{background:#eef4fb;color:#172033;font-weight:800}
    .invoice-row td{background:#fff}
    .shipment-row td{background:#fbfdff;color:#475569}
    .shipment-row.selected td{background:#d9e9ff!important;box-shadow:none}
    .shipment-row td:first-child,.shipment-row td:nth-child(2){background:#fbfdff}
    .line-row td{background:#fff;color:#172033;font-size:10px}
    .invoice-expanded td{background:#fbfdff;white-space:normal;overflow:visible}
    .invoice-expanded .detail-list{grid-template-columns:repeat(2,minmax(180px,1fr));margin-bottom:10px}
    .mini-table th,.mini-table td{font-size:10px;padding:7px 8px}
    .payment-review-table .amount-col{width:84px}
    .payment-review-table .unapplied-col{width:122px}
    .pager{padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;border-top:1px solid #edf1f6}
    .empty{padding:18px 14px;color:#64748b;font-size:12px}
    .screen-links{display:grid;grid-template-columns:repeat(6,minmax(130px,1fr));gap:10px}
    .screen-links a{display:grid;gap:6px;padding:13px;border:1px solid #dce4ee;border-radius:6px;background:#fff;text-decoration:none;color:#172033}
    .screen-links strong{font-size:13px}
    @media(max-width:1180px){.grid{grid-template-columns:1fr}.summary-grid,.screen-links{grid-template-columns:repeat(2,1fr)}.filters{grid-template-columns:repeat(2,1fr)}.filters .wide{grid-column:auto}}
    @media(max-width:720px){.summary-grid,.screen-links,.filters,.form-grid{grid-template-columns:1fr}.full{grid-column:auto}}
  </style>
</head>
<body>
  <header>
    <h1>{{ $pageTitle }}</h1>
    <div class="actions"><span class="muted">{{ $user->name }}</span><form method="post" action="{{ route('logout') }}">@csrf<button type="submit">ログアウト</button></form></div>
  </header>
  <main>
    @if ($section === 'dashboard')
      <div class="stack">
        <section class="screen-links">
          <a href="/billing/monthly-invoices"><strong>月次(締め)請求作成</strong><span class="muted">締日を指定し、取引先ごとの請求書を作成します。</span></a>
          <a href="/billing/spot-invoices"><strong>都度請求作成</strong><span class="muted">都度請求の出荷から、出荷ごとの請求書を作成します。</span></a>
          <a href="/billing/invoices"><strong>請求一覧</strong><span class="muted">請求書の検索、発行、取消、入金予定作成を行います。</span></a>
          <a href="/billing/invoice-print"><strong>再請求書印刷</strong><span class="muted">発行済み請求書を検索し、再印刷プレビューを開きます。</span></a>
          <a href="/billing/payment-confirmation"><strong>入金確認</strong><span class="muted">入金予定月を見ながら入金を登録・消込します。</span></a>
          <a href="/billing/payment-reviews"><strong>要確認入金</strong><span class="muted">過入金、取消済み、未消込を確認します。</span></a>
          <a href="/billing/receivables"><strong>売掛残高</strong><span class="muted">得意先別の未回収額と滞留を確認します。</span></a>
        </section>
        <section class="card">
          <div class="head"><h2>運用の流れ</h2></div>
          <div class="panel">
            <p class="muted">月末に月次請求を作成し、請求書を送付します。翌月は入金確認で入金を登録し、差額や過入金は要確認入金で処理方針を決め、売掛残高で未回収を追います。</p>
          </div>
        </section>
      </div>
    @endif

    @if ($section === 'monthly-invoices')
      <div class="grid" data-screen="monthly-invoices">
        <div class="stack">
          <section class="card">
            <div class="head"><h2>請求対象</h2><div class="actions"><button id="monthly-refresh" type="button">更新</button><span id="monthly-count" class="muted"></span></div></div>
            <div class="filters">
              <label class="wide">取引先検索<input id="monthly-customer" placeholder="取引先名"></label>
              <label>請求月<input id="monthly-billing-month" type="month"></label>
              <label>締日<select id="monthly-closing-day"><option value="">すべて</option><option value="15">15日</option><option value="20">20日</option><option value="end">月末</option></select></label>
              <label>支払期限<input id="monthly-due-date" type="date"></label>
            </div>
            <table>
              <thead><tr><th style="width:42px" class="center">選択</th><th>締日</th><th>取引先</th><th>請求対象</th><th class="num">前回未入金</th><th>請求書作成</th></tr></thead>
              <tbody id="monthly-list"></tbody>
            </table>
            <div class="pager"><span id="monthly-msg" class="notice"></span><div class="actions">@if($canInvoiceCreate)<button id="monthly-create-selected" class="primary" type="button">選択分の請求書作成</button>@endif</div></div>
          </section>
        </div>
        <aside class="stack">
          <section class="card">
            <div class="head"><h2>作成条件</h2></div>
            <div class="panel">
              <label>備考<textarea id="monthly-note" maxlength="1000" placeholder="請求書に残す備考"></textarea></label>
              <p class="muted">締め請求は取引先ごとにまとめ、都度請求は出荷1件ごとに請求書を作成します。都度請求の前回未入金は請求合計に混ぜず、確認用の残高として表示します。</p>
            </div>
          </section>
          <section class="card">
            <div class="head"><h2>直近の請求</h2><span id="monthly-recent-count" class="muted"></span></div>
            <table><thead><tr><th>請求番号</th><th>取引先</th><th>合計</th></tr></thead><tbody id="monthly-recent"></tbody></table>
          </section>
        </aside>
      </div>
    @endif

    @if ($section === 'spot-invoices')
      <div class="grid" data-screen="spot-invoices">
        <div class="stack">
          <section class="card">
            <div class="head"><h2>都度請求対象</h2><div class="actions"><button id="spot-refresh" type="button">検索</button><span id="spot-count" class="muted"></span></div></div>
            <div class="filters">
              <label class="wide">取引先検索<input id="spot-customer" placeholder="取引先名"></label>
              <label>出荷日From<input id="spot-from" type="date"></label>
              <label>出荷日To<input id="spot-to" type="date"></label>
            </div>
            <table>
              <thead><tr><th style="width:42px" class="center">選択</th><th>取引先 / 出荷</th><th>請求方式</th><th>出荷日</th><th>請求書作成</th></tr></thead>
              <tbody id="spot-list"></tbody>
            </table>
            <div class="bulk-bar">
              <span id="spot-selection-summary" class="muted">選択中: 0件</span>
              <div class="actions">@if($canInvoiceCreate)<button id="spot-create-selected" class="primary" type="button">選択分の請求書作成</button>@endif</div>
            </div>
            <div class="pager"><span id="spot-msg" class="notice"></span></div>
          </section>
        </div>
        <aside class="stack">
          <section class="card">
            <div class="head"><h2>出荷詳細</h2></div>
            <div id="spot-shipment-detail" class="panel"><p class="muted">出荷を選択してください。</p></div>
          </section>
          <section class="card">
            <div class="head"><h2>作成条件</h2></div>
            <div class="panel">
              <label>請求日<input id="spot-invoice-date" type="date"></label>
              <label>支払期限<input id="spot-due-date" type="date"></label>
              <label>備考<textarea id="spot-note" maxlength="1000" placeholder="請求書に残す備考"></textarea></label>
              <p class="muted">都度請求は、選択した出荷1件ごとに請求書を1枚作成します。</p>
            </div>
          </section>
        </aside>
      </div>
    @endif

    @if ($section === 'invoices')
      <div class="grid" data-screen="invoices">
        <div class="stack">
          <section class="card">
            <div class="head"><h2>請求一覧</h2><div class="actions"><button id="invoice-search" type="button">検索</button><span id="invoice-count" class="muted"></span></div></div>
            <div class="filters">
              <label class="wide">取引先<input id="invoice-customer" placeholder="取引先名"></label>
              <label>請求番号<input id="invoice-number" placeholder="請求番号"></label>
              <label>請求月<input id="invoice-month" type="month"></label>
              <label>状態<select id="invoice-status"><option value="">すべて</option><option value="draft">発行待ち</option><option value="confirmed">発行済</option><option value="cancelled">取消</option></select></label>
              <label>種別<select id="invoice-document-type"><option value="">すべて</option><option value="invoice">請求</option><option value="credit_memo">赤伝</option></select></label>
            </div>
            <table>
              <thead><tr><th style="width:64px" class="center">請求書 <input id="invoice-issue-select-all" type="checkbox" aria-label="表示中の発行待ち請求書をすべて選択"></th><th style="width:74px" class="center">入金予定 <input id="invoice-schedule-select-all" type="checkbox" aria-label="表示中の入金予定待ち請求書をすべて選択"></th><th>取引先</th><th>請求方式</th><th>請求番号</th><th>出荷番号</th><th class="invoice-date-col">日付</th><th class="num invoice-total-col">税込合計</th></tr></thead>
              <tbody id="invoice-list"></tbody>
            </table>
            <div class="bulk-bar">
              <span id="invoice-selection-summary" class="muted">請求書発行: 0件 / 入金予定作成: 0件</span>
              <div class="actions">@if($canInvoiceConfirm)<button id="invoice-confirm-selected" type="button">選択分の請求書発行</button>@endif @if($canScheduleCreate)<button id="invoice-create-schedules" type="button">選択分の入金予定作成</button>@endif</div>
            </div>
            <div class="pager"><span id="invoice-page" class="muted"></span><div class="actions"><button id="invoice-prev" type="button">前へ</button><button id="invoice-next" type="button">次へ</button></div></div>
          </section>
        </div>
        <aside class="stack">
          <section class="card">
            <div class="head"><h2>出荷詳細</h2></div>
            <div id="invoice-shipment-detail" class="panel"><p class="muted">出荷番号を選択してください。</p></div>
          </section>
        </aside>
      </div>
    @endif

    @if ($section === 'invoice-print')
      <div class="grid" data-screen="invoice-print">
        <div class="stack">
          <section class="card">
            <div class="head"><h2>再請求書印刷</h2><div class="actions"><button id="print-invoice-search" type="button">検索</button><span id="print-invoice-count" class="muted"></span></div></div>
            <div class="filters">
              <label class="wide">取引先<input id="print-invoice-customer" placeholder="取引先名"></label>
              <label>請求番号<input id="print-invoice-number" placeholder="請求番号"></label>
              <label>請求月<input id="print-invoice-month" type="month"></label>
              <label>種別<select id="print-invoice-type"><option value="invoice">請求</option><option value="">すべて</option><option value="credit_memo">赤伝</option></select></label>
            </div>
            <table>
              <thead><tr><th style="width:58px" class="center">選択 <input id="print-invoice-select-all" type="checkbox" aria-label="表示中の請求書をすべて選択"></th><th>請求番号</th><th>取引先</th><th>請求方式</th><th class="invoice-date-col">請求日</th><th class="num invoice-total-col">税込合計</th><th style="width:92px">印刷</th></tr></thead>
              <tbody id="print-invoice-list"></tbody>
            </table>
            <div class="bulk-bar">
              <span id="print-invoice-selection-summary" class="muted">再印刷選択: 0件</span>
              <div class="actions"><button id="print-invoice-selected" type="button">選択分をまとめて再印刷</button></div>
            </div>
            <div class="pager"><span id="print-invoice-page" class="muted"></span><div class="actions"><button id="print-invoice-prev" type="button">前へ</button><button id="print-invoice-next" type="button">次へ</button></div></div>
          </section>
        </div>
        <aside class="stack">
          <section class="card">
            <div class="head"><h2>印刷対象</h2></div>
            <div id="print-invoice-detail" class="panel"><p class="muted">請求書を選択してください。</p></div>
          </section>
        </aside>
      </div>
    @endif

    @if ($section === 'payment-confirmation')
      <div class="grid" data-screen="payment-confirmation">
        <div class="stack">
          <section class="card">
            <div class="head"><h2>入金予定</h2><div class="actions"><button id="schedule-search" type="button">検索</button><span id="schedule-count" class="muted"></span></div></div>
            <div class="filters">
              <label class="wide">取引先<input id="schedule-customer" placeholder="取引先名"></label>
              <label>入金予定月<input id="schedule-month" type="month"></label>
              <label>状態<select id="schedule-status"><option value="">すべて</option><option value="open">未入金</option><option value="partial">一部入金</option><option value="closed">完了</option></select></label>
              <label class="inline-check"><input id="schedule-outstanding-only" type="checkbox" checked>未入金のみ</label>
            </div>
            <table>
              <thead><tr><th>請求番号</th><th>取引先</th><th>入金予定日</th><th>状態</th><th class="num">予定額</th><th class="num">未入金</th></tr></thead>
              <tbody id="schedule-list"></tbody>
            </table>
            <div class="pager"><span id="schedule-page" class="muted"></span><div class="actions"><button id="schedule-prev" type="button">前へ</button><button id="schedule-next" type="button">次へ</button></div></div>
          </section>
        </div>
        <aside class="stack">
          @if($canPaymentCreate)
          <section class="card">
            <div class="head"><h2>入金登録</h2></div>
            <form id="payment-form" class="panel" hidden>
              <div id="payment-target" class="muted">入金予定を選択してください。</div>
              <div class="form-grid">
                <label>入金日<input id="payment-date" type="date" required></label>
                <label>入金額<input id="payment-amount" inputmode="numeric" pattern="[0-9]*" required></label>
                <label>消込方法<select id="payment-apply-mode"><option value="customer">得意先の古い請求から消込</option><option value="schedule">選択した請求だけに消込</option></select></label>
                <label>参照番号<input id="payment-reference" maxlength="100"></label>
              </div>
              <div id="allocation-preview" class="detail-list"></div>
              <p id="payment-msg" class="notice"></p>
              <div class="actions"><button class="primary" type="submit">入金を登録</button></div>
            </form>
          </section>
          @endif
        </aside>
      </div>
    @endif

    @if ($section === 'payment-reviews')
      <div class="grid" data-screen="payment-reviews">
        <div class="stack">
          <section class="card">
            <div class="head"><h2>入金一覧</h2><div class="actions"><button id="payment-review-search" type="button">検索</button><span id="payment-review-count" class="muted"></span></div></div>
            <div class="filters">
              <label class="wide">取引先<input id="review-customer" placeholder="取引先名"></label>
              <label>入金日From<input id="review-from" type="date"></label>
              <label>入金日To<input id="review-to" type="date"></label>
              <label>状態<select id="review-status"><option value="review_required">要確認</option><option value="allocated">消込済み</option><option value="cancelled">取消済み</option><option value="">すべて</option></select></label>
              <label class="inline-check"><input id="review-unapplied-only" type="checkbox" checked>未消込あり</label>
            </div>
            <table class="payment-review-table">
              <colgroup>
                <col style="width:72px">
                <col style="width:30%">
                <col style="width:132px">
                <col style="width:84px">
                <col style="width:122px">
                <col>
              </colgroup>
              <thead><tr><th>入金日</th><th>取引先</th><th>状態</th><th class="num">入金額</th><th class="num">未充当額</th><th>参照番号</th></tr></thead>
              <tbody id="payment-review-list"></tbody>
            </table>
            <div class="pager"><span id="payment-review-page" class="muted"></span><div class="actions"><button id="payment-review-prev" type="button">前へ</button><button id="payment-review-next" type="button">次へ</button></div></div>
          </section>
        </div>
        <aside class="stack">
          <section class="card">
            <div class="head"><h2>入金詳細</h2></div>
            <div id="payment-review-detail" class="panel"><p class="muted">入金を選択してください。</p></div>
            <div id="payment-review-actions" class="panel actions"></div>
          </section>
        </aside>
      </div>
    @endif

    @if ($section === 'receivables')
      <div class="stack" data-screen="receivables">
        <section class="summary-grid">
          <div class="card metric"><span class="muted">未入金合計</span><span id="ar-total" class="value">¥0</span></div>
          <div class="card metric"><span class="muted">未入金件数</span><span id="ar-open" class="value">0</span></div>
          <div class="card metric"><span class="muted">30日超</span><span id="ar-over30" class="value">¥0</span></div>
          <div class="card metric"><span class="muted">60日超</span><span id="ar-over60" class="value">¥0</span></div>
        </section>
        <section class="card">
          <div class="head"><h2>得意先別売掛</h2><div class="actions"><button id="ar-refresh" type="button">更新</button><span id="ar-count" class="muted"></span></div></div>
          <div class="filters">
            <label class="wide">取引先<input id="ar-customer" placeholder="取引先名"></label>
            <label>表示<select id="ar-kind"><option value="outstanding">未入金あり</option><option value="all">すべて</option></select></label>
          </div>
          <table>
            <thead><tr><th>取引先</th><th class="num">予定額</th><th class="num">入金済</th><th class="num">未入金</th><th class="num">未入金件数</th><th class="num">完了件数</th></tr></thead>
            <tbody id="ar-list"></tbody>
          </table>
        </section>
        <section class="card">
          <div class="head"><h2>滞留明細</h2><span id="aging-count" class="muted"></span></div>
          <table><thead><tr><th>請求番号</th><th>取引先</th><th>入金予定日</th><th>状態</th><th class="num">未入金</th><th class="num">経過</th></tr></thead><tbody id="aging-list"></tbody></table>
        </section>
      </div>
    @endif
  </main>
  <script>
    const screen = @json($section);
    const can = { invoiceCreate:@json($canInvoiceCreate), invoiceConfirm:@json($canInvoiceConfirm), invoiceCancel:@json($canInvoiceCancel), scheduleCreate:@json($canScheduleCreate), paymentCreate:@json($canPaymentCreate), paymentCancel:@json($canPaymentCancel) };
    const state = { page:1, lastPage:1, invoices:[], selectedInvoice:null, schedules:[], selectedSchedule:null, payments:[], selectedPayment:null, receivables:[] };
    const labels = { draft:'発行待ち', confirmed:'発行済', cancelled:'取消', open:'未入金', partial:'一部入金', closed:'完了', overdue:'期限超過', allocated:'消込済み', review_required:'要確認', invoice:'請求', credit_memo:'赤伝' };
    const yen = value => `¥${Number(value || 0).toLocaleString('ja-JP',{maximumFractionDigits:0})}`;
    const amountInputValue = value => String(Math.round(Number(value || 0)));
    const today = new Date();
    const iso = date => {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    };
    const monthStart = () => iso(new Date(today.getFullYear(), today.getMonth(), 1));
    const monthEnd = () => iso(new Date(today.getFullYear(), today.getMonth()+1, 0));
    const api = async (url, options={}) => {
      const response = await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json',...(options.body?{'Content-Type':'application/json'}:{})},...options});
      const body = await response.json();
      if(!response.ok) throw new Error(body.error?.message || body.message || '処理に失敗しました。');
      return body.data;
    };
    const qs = params => new URLSearchParams(Object.entries(params).filter(([,v]) => v !== '' && v !== null && v !== undefined && v !== false)).toString();
    const message = (selector,text,isError=false) => { const el=document.querySelector(selector); if(!el) return; el.textContent=text; el.classList.toggle('error',isError); };
    const badgeClass = status => ['cancelled','overdue','review_required'].includes(status) ? 'danger-badge' : ['draft','open','partial'].includes(status) ? 'warn' : ['confirmed','closed','allocated'].includes(status) ? 'success' : '';
    const badge = status => { const span=document.createElement('span'); span.className=`badge ${badgeClass(status)}`; span.textContent=labels[status] || status || ''; return span; };
    const invoiceBatchPrintUrl = ids => `/billing/invoices/print-batch?ids=${ids.join(',')}`;
    const invoiceCreated = invoice => invoice.status === 'confirmed';
    const invoiceScheduleText = invoice => invoice.payment_schedule_id ? '入金予定 済' : '入金予定 未';
    const invoiceStatusStack = invoice => {
      const wrap=document.createElement('div');
      wrap.className='status-stack';
      const invoiceBadge=document.createElement('span');
      invoiceBadge.className=`badge ${invoiceCreated(invoice) ? 'success' : 'schedule-missing'}`;
      invoiceBadge.textContent=invoiceCreated(invoice) ? '発行 済' : '発行 未';
      const scheduleBadge=document.createElement('span');
      const scheduleDone=Boolean(invoice.payment_schedule_id);
      scheduleBadge.className=`badge ${scheduleDone ? 'success' : 'schedule-missing'}`;
      scheduleBadge.textContent=scheduleDone ? '入金予定 済' : '入金予定 未';
      wrap.append(invoiceBadge, scheduleBadge);
      return wrap;
    };
    const invoiceGroupStatusStack = invoices => {
      const wrap=document.createElement('div');
      wrap.className='status-stack';
      const createdCount=invoices.filter(invoiceCreated).length;
      const missingInvoiceCount=invoices.length-createdCount;
      const scheduledCount=invoices.filter(invoice=>invoice.payment_schedule_id).length;
      const missingScheduleCount=invoices.filter(invoice=>!invoice.payment_schedule_id).length;
      const invoiceBadge=document.createElement('span');
      invoiceBadge.className=`badge ${missingInvoiceCount ? 'schedule-missing' : 'success'}`;
      invoiceBadge.textContent=missingInvoiceCount ? '発行 未' : '発行 済';
      const scheduleBadge=document.createElement('span');
      scheduleBadge.className=`badge ${missingScheduleCount ? 'schedule-missing' : 'success'}`;
      scheduleBadge.textContent=missingScheduleCount ? '入金予定 未' : '入金予定 済';
      wrap.append(invoiceBadge, scheduleBadge);
      return wrap;
    };
    const paymentAllocatedAmount = payment => (payment.allocations || []).reduce((sum, allocation) => sum + Number(allocation.allocated_amount || 0), 0);
    const paymentReviewReason = payment => {
      if(payment.status === 'cancelled') return { label:'取消済み', className:'target' };
      if(Number(payment.unapplied_amount || 0) > 0) return { label:'過入金', className:'over' };
      const allocated = paymentAllocatedAmount(payment);
      if(payment.status === 'review_required') return { label:allocated > 0 ? '不足金' : '対象確認', className:allocated > 0 ? 'short' : 'target' };
      return null;
    };
    const reasonBadge = reason => {
      const span=document.createElement('span');
      span.className=`badge reason ${reason?.className || ''}`;
      span.textContent=reason?.label || '';
      return span;
    };
    const cell = (value, cls='') => { const td=document.createElement('td'); td.textContent=value ?? ''; td.title=td.textContent; if(cls) td.className=cls; return td; };
    const elementCell = (element, cls='') => { const td=document.createElement('td'); if(cls) td.className=cls; td.append(element); return td; };
    const setPage = (selector, pagination) => {
      state.page = pagination?.page || 1; state.lastPage = pagination?.last_page || 1;
      const el = document.querySelector(selector);
      if(el) el.textContent = `${state.page} / ${state.lastPage}ページ・${pagination?.total || 0}件`;
    };
    const selectedClass = (row, selected, id) => row.classList.toggle('selected', selected?.id === id);
    const detailRow = (label, value) => { const div=document.createElement('div'); div.className='detail-row'; div.innerHTML=`<span>${label}</span><strong>${value ?? ''}</strong>`; return div; };
    const monthValue = () => document.querySelector('#monthly-billing-month')?.value || iso(today).slice(0, 7);
    const lastDayOfMonth = (year, month) => new Date(year, month, 0).getDate();
    const dateFromParts = (year, month, day) => iso(new Date(year, month - 1, day));
    const closingKey = value => Number(value || 31) >= 31 ? 'end' : String(Number(value));
    const closingLabel = value => closingKey(value) === 'end' ? '月末締め' : `${Number(value)}日締め`;
    const closingDateFor = (month, closingDay) => {
      const [year, monthNumber] = month.split('-').map(Number);
      const key = closingKey(closingDay);
      return key === 'end'
        ? dateFromParts(year, monthNumber, lastDayOfMonth(year, monthNumber))
        : dateFromParts(year, monthNumber, Number(key));
    };
    const periodForClosing = (month, closingDay) => {
      const [year, monthNumber] = month.split('-').map(Number);
      const key = closingKey(closingDay);
      if (key === 'end') {
        return {
          from: dateFromParts(year, monthNumber, 1),
          to: dateFromParts(year, monthNumber, lastDayOfMonth(year, monthNumber)),
        };
      }
      const previous = new Date(year, monthNumber - 2, 1);
      return {
        from: dateFromParts(previous.getFullYear(), previous.getMonth() + 1, Number(key) + 1),
        to: dateFromParts(year, monthNumber, Number(key)),
      };
    };
    const broadMonthlyPeriod = month => {
      const [year, monthNumber] = month.split('-').map(Number);
      const previous = new Date(year, monthNumber - 2, 1);
      return {
        from: dateFromParts(previous.getFullYear(), previous.getMonth() + 1, 1),
        to: dateFromParts(year, monthNumber, lastDayOfMonth(year, monthNumber)),
      };
    };

    async function loadMonthly() {
      const month = monthValue();
      const broadPeriod = broadMonthlyPeriod(month);
      const params = qs({ customer_id:'', billing_target_from:broadPeriod.from, billing_target_to:broadPeriod.to });
      const [billableData, invoiceData] = await Promise.all([
        api(`/api/v1/billing/billable-shipments?${params}`),
        api('/api/v1/billing/invoices?per_page=8')
      ]);
      const keyword = document.querySelector('#monthly-customer').value.trim();
      const selectedClosingDay = document.querySelector('#monthly-closing-day').value;
      const shipments = (billableData.shipments || []).filter(shipment => {
        if(keyword && !(shipment.customer_name || '').includes(keyword)) return false;
        const method = shipment.billing_method || 'monthly_closing';
        if(method !== 'monthly_closing') return false;
        const key = closingKey(shipment.closing_day);
        if(selectedClosingDay && key !== selectedClosingDay) return false;
        const period = periodForClosing(month, shipment.closing_day);
        return (shipment.billing_target_date || '') >= period.from && (shipment.billing_target_date || '') <= period.to;
      });
      const rows = Object.values(shipments.reduce((acc, shipment) => {
        const period = periodForClosing(month, shipment.closing_day);
        const row = acc[shipment.customer_id] ||= { kind:'monthly', customer_id:shipment.customer_id, customer_name:shipment.customer_name, count:0, dates:[], documents:[], previous_balance_amount:shipment.previous_balance_amount || '0.00', billing_cycle_name:shipment.billing_cycle_name, closing_day:shipment.closing_day, closing_date:closingDateFor(month, shipment.closing_day), period_start:period.from, period_end:period.to };
        row.count += 1; row.dates.push(shipment.billing_target_date); row.documents.push(shipment.document_number); return acc;
      }, {})).sort((a,b)=>(closingKey(a.closing_day)).localeCompare(closingKey(b.closing_day)) || (a.customer_name || '').localeCompare(b.customer_name || '', 'ja'));
      state.monthlyRows = rows;
      document.querySelector('#monthly-list').replaceChildren(...rows.map(row => {
        const tr=document.createElement('tr');
        const check=document.createElement('input'); check.type='checkbox'; check.dataset.customerId=row.customer_id; check.checked=true;
        const checkCell=document.createElement('td'); checkCell.className='center'; checkCell.append(check);
        const action=document.createElement('button'); action.type='button'; action.textContent='請求書作成'; action.disabled=!can.invoiceCreate; action.addEventListener('click',()=>createMonthlyInvoice(row));
        const actionCell=document.createElement('td'); actionCell.append(action);
        const target = `${row.count}件 / ${row.period_start} - ${row.period_end}`;
        tr.append(checkCell, cell(closingLabel(row.closing_day)), cell(row.customer_name), cell(target), cell(yen(row.previous_balance_amount),'num'), actionCell);
        return tr;
      }));
      document.querySelector('#monthly-count').textContent = `${rows.length}件`;
      document.querySelector('#monthly-recent').replaceChildren(...(invoiceData.invoices || []).map(invoice => {
        const tr=document.createElement('tr'); tr.append(cell(invoice.invoice_number), cell(invoice.customer_name), cell(yen(invoice.total_amount),'num')); return tr;
      }));
      document.querySelector('#monthly-recent-count').textContent = `${(invoiceData.invoices || []).length}件`;
      if(rows.length === 0) message('#monthly-msg','請求対象の出荷はありません。'); else message('#monthly-msg','作成対象を確認してください。');
    }
    async function createMonthlyInvoice(row) {
      if(!can.invoiceCreate) return;
      try {
        await api('/api/v1/billing/closing-invoices',{method:'POST',body:JSON.stringify({customer_id:Number(row.customer_id),closing_date:row.closing_date,due_date:document.querySelector('#monthly-due-date').value || null,note:document.querySelector('#monthly-note').value || null,reason:'月次請求作成画面から締め請求書を作成'})});
        message('#monthly-msg','請求書を作成しました。'); await loadMonthly();
      } catch(error) { message('#monthly-msg',error.message,true); }
    }
    async function loadSpotInvoices() {
      const params = qs({ customer_id:'', billing_target_from:document.querySelector('#spot-from').value, billing_target_to:document.querySelector('#spot-to').value });
      const data = await api(`/api/v1/billing/billable-shipments?${params}`);
      const keyword = document.querySelector('#spot-customer').value.trim();
      const shipments = (data.shipments || []).filter(shipment => {
        if((shipment.billing_method || '') !== 'per_shipment') return false;
        if(keyword && !(shipment.customer_name || '').includes(keyword)) return false;
        return true;
      });
      state.spotShipments = shipments;
      state.selectedSpotShipmentIds = state.selectedSpotShipmentIds || new Set();
      state.expandedSpotCustomer = null;
      clearSpotShipmentDetail();
      renderSpotInvoices();
      message('#spot-msg', shipments.length ? '都度請求対象を確認してください。' : '都度請求対象の出荷はありません。');
    }
    function renderSpotInvoices(){
      const groups = new Map();
      (state.spotShipments || []).forEach(shipment => {
        const key = String(shipment.customer_id);
        if(!groups.has(key)) groups.set(key, { key, customer_name:shipment.customer_name, shipments:[] });
        groups.get(key).shipments.push(shipment);
      });
      const nodes = [];
      groups.forEach(group => {
        const row=document.createElement('tr');
        row.className='invoice-group';
        const checked=group.shipments.length>0 && group.shipments.every(shipment=>state.selectedSpotShipmentIds.has(shipment.id));
        const check=document.createElement('input');
        check.type='checkbox';
        check.checked=checked;
        check.addEventListener('click',event=>event.stopPropagation());
        check.addEventListener('change',()=>{
          group.shipments.forEach(shipment=>{ check.checked ? state.selectedSpotShipmentIds.add(shipment.id) : state.selectedSpotShipmentIds.delete(shipment.id); });
          updateSpotSelectionSummary();
          renderSpotInvoices();
        });
        const checkCell=document.createElement('td'); checkCell.className='center'; checkCell.append(check);
        row.append(checkCell, cell(group.customer_name || ''), cell('都度請求'), cell(`${group.shipments.length}件`), cell(''));
        row.addEventListener('click',()=>{ const next = state.expandedSpotCustomer === group.key ? null : group.key; if(next !== state.expandedSpotCustomer) clearSpotShipmentDetail(); state.expandedSpotCustomer = next; renderSpotInvoices(); });
        nodes.push(row);
        if(state.expandedSpotCustomer === group.key){
          group.shipments.forEach(shipment => {
            const shipmentRow=document.createElement('tr');
            shipmentRow.className='shipment-row';
            shipmentRow.classList.toggle('selected', state.selectedSpotShipmentId === shipment.id);
            const childCheck=document.createElement('input');
            childCheck.type='checkbox';
            childCheck.checked=state.selectedSpotShipmentIds.has(shipment.id);
            childCheck.addEventListener('click',event=>event.stopPropagation());
            childCheck.addEventListener('change',()=>{
              childCheck.checked ? state.selectedSpotShipmentIds.add(shipment.id) : state.selectedSpotShipmentIds.delete(shipment.id);
              updateSpotSelectionSummary();
              renderSpotInvoices();
            });
            const childCheckCell=document.createElement('td'); childCheckCell.className='center'; childCheckCell.append(childCheck);
            const action=document.createElement('button');
            action.type='button';
            action.textContent='請求書作成';
            action.disabled=!can.invoiceCreate;
            action.addEventListener('click',event=>{ event.stopPropagation(); createSpotInvoice(shipment); });
            const actionCell=document.createElement('td'); actionCell.append(action);
            shipmentRow.append(childCheckCell, cell(shipment.document_number), cell(''), cell(shipment.billing_target_date || ''), actionCell);
            shipmentRow.addEventListener('click',()=>{ state.selectedSpotShipmentId = shipment.id; renderSpotInvoices(); renderSpotShipmentDetail(shipment); });
            nodes.push(shipmentRow);
          });
        }
      });
      document.querySelector('#spot-list').replaceChildren(...nodes);
      document.querySelector('#spot-count').textContent = `${(state.spotShipments || []).length}件`;
      updateSpotSelectionSummary();
    }
    function updateSpotSelectionSummary(){
      const count = (state.spotShipments || []).filter(shipment=>state.selectedSpotShipmentIds?.has(shipment.id)).length;
      const summary=document.querySelector('#spot-selection-summary');
      if(summary) summary.textContent=`選択中: ${count}件`;
      const button=document.querySelector('#spot-create-selected');
      if(button) button.disabled=count===0;
    }
    function renderSpotShipmentDetail(shipment){
      const box=document.querySelector('#spot-shipment-detail');
      if(!box) return;
      box.replaceChildren(
        detailRow('取引先', shipment.customer_name),
        detailRow('請求方式', '都度請求'),
        detailRow('出荷番号', shipment.document_number),
        detailRow('出荷日', shipment.billing_target_date || ''),
        detailRow('前回未入金', yen(shipment.previous_balance_amount || 0))
      );
    }
    function clearSpotShipmentDetail(){
      const box=document.querySelector('#spot-shipment-detail');
      if(box) box.innerHTML='<p class="muted">出荷を選択してください。</p>';
      state.selectedSpotShipmentId = null;
    }
    async function createSpotInvoice(shipment) {
      if(!can.invoiceCreate) return;
      try {
        const shipmentDate = shipment.billing_target_date || document.querySelector('#spot-invoice-date').value;
        await api('/api/v1/billing/invoices',{method:'POST',body:JSON.stringify({customer_id:Number(shipment.customer_id),invoice_date:document.querySelector('#spot-invoice-date').value,billing_period_start:shipmentDate,billing_period_end:shipmentDate,due_date:document.querySelector('#spot-due-date').value || null,note:document.querySelector('#spot-note').value || null,shipment_header_ids:[Number(shipment.id)],reason:'都度請求作成画面から請求書を作成'})});
        state.selectedSpotShipmentIds?.delete(shipment.id);
        message('#spot-msg','請求書を作成しました。');
        await loadSpotInvoices();
      } catch(error) { message('#spot-msg',error.message,true); }
    }
    async function createSelectedSpotInvoices(){
      const targets = (state.spotShipments || []).filter(shipment=>state.selectedSpotShipmentIds?.has(shipment.id));
      if(targets.length === 0) return message('#spot-msg','請求書を作成する出荷を選択してください。',true);
      for(const shipment of targets) {
        await createSpotInvoice(shipment);
      }
    }
    async function legacyLoadInvoices(page=1) {
      const params = qs({ customer:document.querySelector('#invoice-customer').value.trim(), invoice_number:document.querySelector('#invoice-number').value.trim(), invoice_date_from:document.querySelector('#invoice-date-from').value, invoice_date_to:document.querySelector('#invoice-date-to').value, status:document.querySelector('#invoice-status').value, document_type:document.querySelector('#invoice-document-type').value, per_page:30, page });
      const data = await api(`/api/v1/billing/invoices?${params}`);
      state.invoices = data.invoices || []; state.selectedInvoice = null;
      document.querySelector('#invoice-list').replaceChildren(...state.invoices.map(invoice => {
        const tr=document.createElement('tr'); selectedClass(tr,state.selectedInvoice,invoice.id); tr.addEventListener('click',()=>selectInvoice(invoice));
        const statusCell=document.createElement('td'); statusCell.append(badge(invoice.status));
        tr.append(cell(invoice.invoice_number), cell(invoice.customer_name), cell(invoice.invoice_date), cell([invoice.billing_period_start,invoice.billing_period_end].filter(Boolean).join(' - ')), statusCell, cell(yen(invoice.total_amount),'num'));
        return tr;
      }));
      document.querySelector('#invoice-count').textContent = `${data.pagination?.total || state.invoices.length}件`;
      setPage('#invoice-page', data.pagination);
      renderInvoiceDetail(null);
    }
    function selectInvoice(invoice) { state.selectedInvoice=invoice; document.querySelectorAll('#invoice-list tr').forEach(row=>row.classList.toggle('selected', row.children[0]?.textContent===invoice.invoice_number)); renderInvoiceDetail(invoice); }
    function renderInvoiceDetail(invoice) {
      const box=document.querySelector('#invoice-detail'); const lines=document.querySelector('#invoice-lines'); const actions=document.querySelector('#invoice-actions');
      box.replaceChildren(); lines.replaceChildren(); actions.replaceChildren();
      if(!invoice){ box.innerHTML='<p class="muted">請求を選択してください。</p>'; return; }
      const previousLabel = invoice.billing_method === 'per_shipment' ? '参考残高（請求額に含めない）' : '前回未入金';
      box.append(detailRow('請求番号',invoice.invoice_number),detailRow('取引先',invoice.customer_name),detailRow('状態',labels[invoice.status] || invoice.status),detailRow(previousLabel,yen(invoice.previous_balance_amount)),detailRow('請求へ繰越',yen(invoice.carried_forward_amount)),detailRow('期間内入金',yen(invoice.period_payment_amount)),detailRow('今回売上',yen(invoice.current_sales_amount)),detailRow('消費税',yen(invoice.current_tax_amount)),detailRow('今回請求額',yen(invoice.total_amount)),detailRow('支払依頼額',yen(invoice.payment_request_amount || invoice.total_amount)));
      lines.replaceChildren(...(invoice.lines || []).map(line => { const tr=document.createElement('tr'); tr.append(cell(line.product_name),cell(line.quantity_display || line.quantity,'num'),cell(yen(line.total_amount),'num')); return tr; }));
      if(invoice.status === 'draft' && can.invoiceConfirm){ const b=document.createElement('button'); b.className='primary'; b.textContent='請求書を発行'; b.onclick=()=>confirmInvoice(invoice); actions.append(b); }
      if(invoice.status === 'draft' && can.invoiceCancel){ const b=document.createElement('button'); b.className='danger'; b.textContent='請求を取消'; b.onclick=()=>cancelInvoice(invoice); actions.append(b); }
      if(invoice.status === 'confirmed' && invoice.payment_schedule_id){ actions.append(detailRow('入金予定', labels[invoice.payment_schedule_status] || invoice.payment_schedule_status || '作成済み')); }
      if(invoice.status === 'confirmed' && !invoice.payment_schedule_id && can.scheduleCreate){ const b=document.createElement('button'); b.textContent='入金予定を作成'; b.onclick=()=>createSchedule(invoice); actions.append(b); }
    }
    async function confirmInvoice(invoice){ try{ await api(`/api/v1/billing/invoices/${invoice.id}/confirm`,{method:'POST',body:JSON.stringify({reason:'請求一覧画面から請求書発行'})}); await loadInvoices(state.page); }catch(error){ alert(error.message); } }
    async function cancelInvoice(invoice){ const reason=prompt('請求を取り消す理由を入力してください。'); if(reason===null) return; if(!reason.trim()) return alert('取消理由を入力してください。'); try{ await api(`/api/v1/billing/invoices/${invoice.id}/cancel`,{method:'POST',body:JSON.stringify({reason:reason.trim()})}); await loadInvoices(state.page); }catch(error){ alert(error.message); } }
    async function createSchedule(invoice){ if(invoice.payment_schedule_id) return alert('この請求には入金予定が作成済みです。'); try{ await api('/api/v1/billing/payment-schedules',{method:'POST',body:JSON.stringify({invoice_header_id:invoice.id,reason:'請求一覧画面から入金予定を作成'})}); await loadInvoices(state.page); }catch(error){ alert(error.message); } }

    const monthEndForValue = value => {
      const [year, month] = value.split('-').map(Number);
      return dateFromParts(year, month, lastDayOfMonth(year, month));
    };
    const canIssueInvoiceFor = invoice => invoice.status === 'draft' && invoice.document_type !== 'credit_memo';
    const canCreateScheduleFor = invoice => invoice.status === 'confirmed' && invoice.document_type !== 'credit_memo' && !invoice.payment_schedule_id;
    const invoiceStepDone = (invoice, mode) => mode === 'issue' ? invoice.status === 'confirmed' : Boolean(invoice.payment_schedule_id);
    const invoiceSelectionSet = mode => mode === 'issue' ? state.selectedIssueInvoiceIds : state.selectedScheduleInvoiceIds;
    const canSelectInvoiceFor = (invoice, mode) => mode === 'issue' ? canIssueInvoiceFor(invoice) : canCreateScheduleFor(invoice);
    function emptyInvoiceCheckCell(){
      const td=document.createElement('td');
      td.className='center';
      return td;
    }
    function invoiceCheckCell(invoice, mode){
      const td=emptyInvoiceCheckCell();
      const check=document.createElement('input');
      const selectedSet=invoiceSelectionSet(mode);
      const done=invoiceStepDone(invoice, mode);
      check.type='checkbox';
      check.checked=done || selectedSet?.has(invoice.id) || false;
      check.disabled=done || !canSelectInvoiceFor(invoice, mode);
      check.addEventListener('click',event=>event.stopPropagation());
      check.addEventListener('change',()=>{
        check.checked ? selectedSet.add(invoice.id) : selectedSet.delete(invoice.id);
        updateInvoiceSelectionSummary();
        renderInvoiceList();
      });
      td.append(check);
      return td;
    }
    function invoiceGroupCheckCell(invoices, mode){
      const td=emptyInvoiceCheckCell();
      const selectedSet=invoiceSelectionSet(mode);
      const targets=invoices.filter(invoice=>canSelectInvoiceFor(invoice, mode));
      const check=document.createElement('input');
      check.type='checkbox';
      check.checked=invoices.length>0 && invoices.every(invoice=>invoiceStepDone(invoice, mode) || selectedSet?.has(invoice.id));
      check.disabled=targets.length===0;
      check.addEventListener('click',event=>event.stopPropagation());
      check.addEventListener('change',()=>{
        targets.forEach(invoice=>{ check.checked ? selectedSet.add(invoice.id) : selectedSet.delete(invoice.id); });
        updateInvoiceSelectionSummary();
        renderInvoiceList();
      });
      td.append(check);
      return td;
    }
    async function loadInvoices(page=1) {
      const invoiceMonth = document.querySelector('#invoice-month').value;
      const params = qs({ customer:document.querySelector('#invoice-customer').value.trim(), invoice_number:document.querySelector('#invoice-number').value.trim(), invoice_date_from:invoiceMonth ? `${invoiceMonth}-01` : '', invoice_date_to:invoiceMonth ? monthEndForValue(invoiceMonth) : '', status:document.querySelector('#invoice-status').value, document_type:document.querySelector('#invoice-document-type').value, per_page:30, page });
      const data = await api(`/api/v1/billing/invoices?${params}`);
      state.invoices = data.invoices || [];
      state.selectedInvoice = null;
      state.selectedIssueInvoiceIds = new Set();
      state.selectedScheduleInvoiceIds = new Set();
      state.expandedInvoiceGroup = null;
      state.selectedShipmentKey = null;
      clearInvoiceShipmentDetail();
      renderInvoiceList();
      document.querySelector('#invoice-count').textContent = `${data.pagination?.total || state.invoices.length}件`;
      setPage('#invoice-page', data.pagination);
      updateInvoiceSelectionSummary();
    }
    function renderInvoiceList(){
      const nodes = [];
      const groups = new Map();
      state.invoices.forEach(invoice => {
        const method = invoice.billing_method === 'per_shipment' ? '都度請求' : '締め請求';
        const key = `${invoice.customer_id}:${method}`;
        if(!groups.has(key)) groups.set(key, { key, customer_name:invoice.customer_name, method, invoices:[] });
        groups.get(key).invoices.push(invoice);
      });
      groups.forEach(group => {
        const groupRow=document.createElement('tr');
        groupRow.className='invoice-group';
        const invoices=group.invoices;
        const firstInvoice=invoices[0];
        const isMonthlyGroup=firstInvoice?.billing_method !== 'per_shipment';
        const invoiceNumbers=[...new Set(invoices.map(invoice=>invoice.invoice_number).filter(Boolean))].join(', ');
        const total=invoices.reduce((sum,invoice)=>sum+Number(invoice.total_amount || 0),0);
        groupRow.classList.toggle('selected', isMonthlyGroup && state.selectedShipmentKey === `invoice-group:${firstInvoice?.id}`);
        groupRow.append(invoiceGroupCheckCell(invoices, 'issue'), invoiceGroupCheckCell(invoices, 'schedule'), cell(group.customer_name || ''), cell(group.method), cell(isMonthlyGroup ? invoiceNumbers : `${invoices.length}件`), cell(''), cell(isMonthlyGroup && invoices.length === 1 ? firstInvoice.invoice_date : '', 'invoice-date-col'), cell(yen(total),'num invoice-total-col'));
        groupRow.addEventListener('click',()=>{
          const nextGroup = state.expandedInvoiceGroup === group.key ? null : group.key;
          if(nextGroup !== state.expandedInvoiceGroup){ clearInvoiceShipmentDetail(); }
          state.expandedInvoiceGroup = nextGroup;
          state.selectedShipmentKey = isMonthlyGroup && nextGroup ? `invoice-group:${firstInvoice.id}` : null;
          renderInvoiceList();
          if(isMonthlyGroup && nextGroup && invoices.length === 1) renderInvoiceSummaryDetail(firstInvoice);
        });
        nodes.push(groupRow);
        if(state.expandedInvoiceGroup === group.key) {
          group.invoices.forEach(invoice => renderInvoiceChildRows(invoice).forEach(row=>nodes.push(row)));
        }
      });
      document.querySelector('#invoice-list').replaceChildren(...nodes);
    }
    function renderInvoiceChildRows(invoice){
      const rows=[];
      const byShipment = new Map();
      (invoice.lines || []).forEach(line => {
        const key = line.shipment_document_number || '出荷番号なし';
        if(!byShipment.has(key)) byShipment.set(key, []);
        byShipment.get(key).push(line);
      });
      byShipment.forEach((lines, shipmentNumber) => {
        const shipmentRow=document.createElement('tr');
        shipmentRow.className='shipment-row';
        const shipmentKey=`${invoice.id}:${shipmentNumber}`;
        shipmentRow.classList.toggle('selected', state.selectedShipmentKey === shipmentKey);
        const total=lines.reduce((sum,line)=>sum+Number(line.total_amount || 0),0);
        const date=lines.find(line=>line.shipment_document_date)?.shipment_document_date || invoice.invoice_date || '';
        const invoiceNumber = invoice.billing_method === 'per_shipment' ? invoice.invoice_number : '';
        const shipmentLabel = invoice.billing_method === 'per_shipment' ? shipmentNumber : `　${shipmentNumber}`;
        const issueCell = invoice.billing_method === 'per_shipment' ? invoiceCheckCell(invoice, 'issue') : emptyInvoiceCheckCell();
        const scheduleCell = invoice.billing_method === 'per_shipment' ? invoiceCheckCell(invoice, 'schedule') : emptyInvoiceCheckCell();
        shipmentRow.append(issueCell, scheduleCell, cell(''), cell(''), cell(invoiceNumber), cell(shipmentLabel), cell(date, 'invoice-date-col'), cell(yen(total),'num invoice-total-col'));
        shipmentRow.addEventListener('click',()=>{ state.selectedShipmentKey = shipmentKey; renderInvoiceList(); renderShipmentDetail(invoice, shipmentNumber, lines); });
        rows.push(shipmentRow);
      });
      return rows;
    }
    function renderMonthlyInvoiceRow(invoice){
      const row=document.createElement('tr');
      row.className='invoice-row';
      const key=`invoice:${invoice.id}`;
      row.classList.toggle('selected', state.selectedShipmentKey === key);
      row.append(invoiceCheckCell(invoice, 'issue'), invoiceCheckCell(invoice, 'schedule'), cell(''), cell(''), cell(invoice.invoice_number), cell(''), cell(invoice.invoice_date, 'invoice-date-col'), cell(yen(invoice.total_amount),'num invoice-total-col'));
      row.addEventListener('click',()=>{ state.selectedShipmentKey = key; renderInvoiceList(); renderInvoiceSummaryDetail(invoice); });
      return row;
    }
    function renderInvoiceSummaryDetail(invoice){
      const box=document.querySelector('#invoice-shipment-detail');
      if(!box) return;
      box.replaceChildren();
      const detail=document.createElement('div');
      detail.className='detail-list';
      detail.append(detailRow('取引先',invoice.customer_name),detailRow('請求方式','締め請求'),detailRow('請求番号',invoice.invoice_number),detailRow('請求日',invoice.invoice_date),detailRow('状態',labels[invoice.status] || invoice.status),detailRow('税込合計',yen(invoice.total_amount)),detailRow('入金予定',invoiceScheduleText(invoice)));
      const actions=document.createElement('div');
      actions.className='actions left-actions';
      if(invoice.status === 'draft' && can.invoiceConfirm){ const b=document.createElement('button'); b.className='primary'; b.textContent='請求書を発行'; b.onclick=()=>confirmInvoice(invoice); actions.append(b); }
      if(invoice.status === 'draft' && can.invoiceCancel){ const b=document.createElement('button'); b.className='danger'; b.textContent='請求を取消'; b.onclick=()=>cancelInvoice(invoice); actions.append(b); }
      if(canCreateScheduleFor(invoice) && can.scheduleCreate){ const b=document.createElement('button'); b.textContent='入金予定を作成'; b.onclick=()=>createSchedule(invoice); actions.append(b); }
      box.append(detail, actions);
    }
    function renderShipmentDetail(invoice, shipmentNumber, lines){
      const box=document.querySelector('#invoice-shipment-detail');
      if(!box) return;
      box.replaceChildren();
      const detail=document.createElement('div');
      detail.className='detail-list';
      detail.append(detailRow('取引先',invoice.customer_name),detailRow('請求方式',invoice.billing_method === 'per_shipment' ? '都度請求' : '締め請求'),detailRow('請求番号',invoice.invoice_number),detailRow('出荷番号',shipmentNumber),detailRow('出荷日',lines.find(line=>line.shipment_document_date)?.shipment_document_date || ''),detailRow('状態',labels[invoice.status] || invoice.status),detailRow('税込合計',yen(lines.reduce((sum,line)=>sum+Number(line.total_amount || 0),0))),detailRow('入金予定',invoiceScheduleText(invoice)));
      if(invoice.billing_method === 'per_shipment' && Number(invoice.previous_balance_amount || 0) > 0){ detail.append(detailRow('参考残高（請求額に含めない）', yen(invoice.previous_balance_amount))); }
      const actions=document.createElement('div');
      actions.className='actions left-actions';
      if(invoice.billing_method === 'per_shipment' && invoice.status === 'draft' && can.invoiceConfirm){ const b=document.createElement('button'); b.className='primary'; b.textContent='請求書を発行'; b.onclick=()=>confirmInvoice(invoice); actions.append(b); }
      if(invoice.billing_method === 'per_shipment' && invoice.status === 'draft' && can.invoiceCancel){ const b=document.createElement('button'); b.className='danger'; b.textContent='請求を取消'; b.onclick=()=>cancelInvoice(invoice); actions.append(b); }
      if(invoice.billing_method === 'per_shipment' && canCreateScheduleFor(invoice) && can.scheduleCreate){ const b=document.createElement('button'); b.textContent='入金予定を作成'; b.onclick=()=>createSchedule(invoice); actions.append(b); }
      if(invoice.billing_method !== 'per_shipment'){ actions.append(detailRow('請求書操作','上の請求書行で行います')); }
      const table=document.createElement('table');
      table.className='mini-table';
      table.innerHTML='<thead><tr><th>商品</th><th class="num">数量</th><th class="num">税込</th></tr></thead>';
      const tbody=document.createElement('tbody');
      tbody.replaceChildren(...lines.map(line=>{ const row=document.createElement('tr'); row.append(cell(line.product_name), cell(line.quantity_display || line.quantity,'num'), cell(yen(line.total_amount),'num')); return row; }));
      table.append(tbody);
      box.append(detail, actions, table);
    }
    function clearInvoiceShipmentDetail(){
      const box=document.querySelector('#invoice-shipment-detail');
      if(box) box.innerHTML='<p class="muted">出荷番号を選択してください。</p>';
    }
    function updateInvoiceSelectionSummary(){
      const issueTargets = state.invoices.filter(invoice => state.selectedIssueInvoiceIds?.has(invoice.id)).filter(canIssueInvoiceFor);
      const scheduleTargets = state.invoices.filter(invoice => state.selectedScheduleInvoiceIds?.has(invoice.id)).filter(canCreateScheduleFor);
      const summary=document.querySelector('#invoice-selection-summary');
      if(summary) summary.textContent=`請求書発行: ${issueTargets.length}件 / 入金予定作成: ${scheduleTargets.length}件`;
      const issueButton=document.querySelector('#invoice-confirm-selected');
      if(issueButton) issueButton.disabled = issueTargets.length === 0;
      const scheduleButton=document.querySelector('#invoice-create-schedules');
      if(scheduleButton) scheduleButton.disabled = scheduleTargets.length === 0;
      updateInvoiceSelectAllControl('issue');
      updateInvoiceSelectAllControl('schedule');
    }
    function updateInvoiceSelectAllControl(mode){
      const control=document.querySelector(mode === 'issue' ? '#invoice-issue-select-all' : '#invoice-schedule-select-all');
      if(!control) return;
      const targets=(state.invoices || []).filter(invoice=>canSelectInvoiceFor(invoice, mode));
      const selectedSet=invoiceSelectionSet(mode);
      const selectedCount=targets.filter(invoice=>selectedSet?.has(invoice.id)).length;
      control.checked = targets.length > 0 && selectedCount === targets.length;
      control.indeterminate = selectedCount > 0 && selectedCount < targets.length;
      control.disabled = targets.length === 0;
    }
    function toggleInvoiceSelectAll(mode, checked){
      const selectedSet=invoiceSelectionSet(mode);
      (state.invoices || []).filter(invoice=>canSelectInvoiceFor(invoice, mode)).forEach(invoice=>{
        checked ? selectedSet.add(invoice.id) : selectedSet.delete(invoice.id);
      });
      renderInvoiceList();
      updateInvoiceSelectionSummary();
    }
    async function confirmSelectedInvoices(){
      const targets = state.invoices.filter(invoice => state.selectedIssueInvoiceIds?.has(invoice.id)).filter(canIssueInvoiceFor);
      if(targets.length === 0) return alert('請求書発行できる請求が選択されていません。');
      const issuedIds = new Set(targets.map(invoice=>invoice.id));
      const printWindow = window.open('about:blank', '_blank');
      try {
        for(const invoice of targets) {
          await api(`/api/v1/billing/invoices/${invoice.id}/confirm`,{method:'POST',body:JSON.stringify({reason:'請求一覧画面から選択分の請求書発行'})});
        }
        if(printWindow) {
          printWindow.location.href = invoiceBatchPrintUrl([...issuedIds]);
        } else {
          alert('請求書を発行しました。ブラウザでポップアップがブロックされたため、請求書印刷画面から再印刷してください。');
        }
        await loadInvoices(state.page);
        state.selectedScheduleInvoiceIds = new Set(state.invoices.filter(invoice=>issuedIds.has(invoice.id) && canCreateScheduleFor(invoice)).map(invoice=>invoice.id));
        renderInvoiceList();
        updateInvoiceSelectionSummary();
      } catch(error) {
        if(printWindow) printWindow.close();
        alert(error.message);
      }
    }
    async function createSelectedSchedules(){
      const targets = state.invoices.filter(invoice => state.selectedScheduleInvoiceIds?.has(invoice.id)).filter(canCreateScheduleFor);
      if(targets.length === 0) return alert('入金予定を作成できる請求が選択されていません。');
      try {
        for(const invoice of targets) {
          await api('/api/v1/billing/payment-schedules',{method:'POST',body:JSON.stringify({invoice_header_id:invoice.id,reason:'請求一覧画面から選択分の入金予定を作成'})});
        }
        await loadInvoices(state.page);
      } catch(error) {
        alert(error.message);
      }
    }

    async function loadPrintableInvoices(page=1) {
      const invoiceMonth = document.querySelector('#print-invoice-month').value;
      const params = qs({
        customer:document.querySelector('#print-invoice-customer').value.trim(),
        invoice_number:document.querySelector('#print-invoice-number').value.trim(),
        invoice_date_from:invoiceMonth ? `${invoiceMonth}-01` : '',
        invoice_date_to:invoiceMonth ? monthEndForValue(invoiceMonth) : '',
        status:'confirmed',
        document_type:document.querySelector('#print-invoice-type').value,
        per_page:30,
        page
      });
      const data = await api(`/api/v1/billing/invoices?${params}`);
      state.printInvoices = data.invoices || [];
      state.selectedPrintInvoice = null;
      state.selectedPrintInvoiceIds = new Set();
      document.querySelector('#print-invoice-list').replaceChildren(...state.printInvoices.map(invoice => {
        const tr=document.createElement('tr');
        tr.addEventListener('click',()=>selectPrintableInvoice(invoice));
        const method=invoice.billing_method === 'per_shipment' ? '都度請求' : '締め請求';
        const check=document.createElement('input');
        check.type='checkbox';
        check.checked=state.selectedPrintInvoiceIds.has(invoice.id);
        check.addEventListener('click',event=>event.stopPropagation());
        check.addEventListener('change',()=>{
          check.checked ? state.selectedPrintInvoiceIds.add(invoice.id) : state.selectedPrintInvoiceIds.delete(invoice.id);
          updatePrintableInvoiceSelectionSummary();
        });
        const checkCell=document.createElement('td');
        checkCell.className='center';
        checkCell.append(check);
        const action=document.createElement('button');
        action.type='button';
        action.textContent='印刷';
        action.addEventListener('click',event=>{ event.stopPropagation(); window.open(`/billing/invoices/${invoice.id}/print`, '_blank'); });
        const actionCell=document.createElement('td');
        actionCell.append(action);
        tr.append(checkCell, cell(invoice.invoice_number), cell(invoice.customer_name), cell(method), cell(invoice.invoice_date, 'invoice-date-col'), cell(yen(invoice.total_amount),'num invoice-total-col'), actionCell);
        return tr;
      }));
      document.querySelector('#print-invoice-count').textContent = `${data.pagination?.total || state.printInvoices.length}件`;
      setPage('#print-invoice-page', data.pagination);
      renderPrintableInvoiceDetail(null);
      updatePrintableInvoiceSelectionSummary();
    }
    function selectPrintableInvoice(invoice){
      state.selectedPrintInvoice=invoice;
      document.querySelectorAll('#print-invoice-list tr').forEach(row=>row.classList.toggle('selected', row.children[1]?.textContent===invoice.invoice_number));
      renderPrintableInvoiceDetail(invoice);
    }
    function renderPrintableInvoiceDetail(invoice){
      const box=document.querySelector('#print-invoice-detail');
      if(!box) return;
      box.replaceChildren();
      if(!invoice){ box.innerHTML='<p class="muted">請求書を選択してください。</p>'; return; }
      const detail=document.createElement('div');
      detail.className='detail-list';
      detail.append(
        detailRow('取引先',invoice.customer_name),
        detailRow('請求方式',invoice.billing_method === 'per_shipment' ? '都度請求' : '締め請求'),
        detailRow('請求番号',invoice.invoice_number),
        detailRow('請求日',invoice.invoice_date),
        detailRow('支払期日',invoice.due_date || ''),
        detailRow('税込合計',yen(invoice.total_amount))
      );
      const actions=document.createElement('div');
      actions.className='actions left-actions';
      const printButton=document.createElement('button');
      printButton.className='primary';
      printButton.type='button';
      printButton.textContent='印刷プレビュー';
      printButton.onclick=()=>window.open(`/billing/invoices/${invoice.id}/print`, '_blank');
      actions.append(printButton);
      box.append(detail, actions);
    }
    function updatePrintableInvoiceSelectionSummary(){
      const selectedCount = state.selectedPrintInvoiceIds?.size || 0;
      const summary=document.querySelector('#print-invoice-selection-summary');
      if(summary) summary.textContent=`再印刷選択: ${selectedCount}件`;
      const button=document.querySelector('#print-invoice-selected');
      if(button) button.disabled = selectedCount === 0;
      const selectAll=document.querySelector('#print-invoice-select-all');
      const visibleIds=(state.printInvoices || []).map(invoice=>invoice.id);
      const visibleSelected=visibleIds.filter(id=>state.selectedPrintInvoiceIds?.has(id)).length;
      if(selectAll){
        selectAll.checked = visibleIds.length > 0 && visibleSelected === visibleIds.length;
        selectAll.indeterminate = visibleSelected > 0 && visibleSelected < visibleIds.length;
        selectAll.disabled = visibleIds.length === 0;
      }
    }
    function togglePrintableInvoiceSelectAll(event){
      const checked=event.target.checked;
      (state.printInvoices || []).forEach(invoice=>{
        checked ? state.selectedPrintInvoiceIds.add(invoice.id) : state.selectedPrintInvoiceIds.delete(invoice.id);
      });
      document.querySelectorAll('#print-invoice-list input[type="checkbox"]').forEach(input=>{ input.checked = checked; });
      updatePrintableInvoiceSelectionSummary();
    }
    function printSelectedPrintableInvoices(){
      const ids=[...(state.selectedPrintInvoiceIds || new Set())];
      if(ids.length === 0) return alert('再印刷する請求書を選択してください。');
      window.open(invoiceBatchPrintUrl(ids), '_blank');
    }

    async function loadSchedules(page=1) {
      const scheduleMonth = document.querySelector('#schedule-month').value;
      const params = qs({ customer:document.querySelector('#schedule-customer').value.trim(), expected_payment_from:scheduleMonth ? `${scheduleMonth}-01` : '', expected_payment_to:scheduleMonth ? monthEndForValue(scheduleMonth) : '', status:document.querySelector('#schedule-status').value, only_outstanding:document.querySelector('#schedule-outstanding-only').checked ? 1 : '', per_page:30, page });
      const data = await api(`/api/v1/billing/payment-schedules?${params}`);
      state.schedules = data.payment_schedules || []; state.selectedSchedule = null;
      document.querySelector('#schedule-list').replaceChildren(...state.schedules.map(schedule => {
        const tr=document.createElement('tr'); tr.addEventListener('click',()=>selectSchedule(schedule));
        const statusCell=document.createElement('td'); statusCell.append(badge(schedule.status));
        tr.append(cell(schedule.invoice_number),cell(schedule.customer_name),cell(schedule.expected_payment_date),statusCell,cell(yen(schedule.scheduled_amount),'num'),cell(yen(schedule.outstanding_amount),'num'));
        return tr;
      }));
      document.querySelector('#schedule-count').textContent = `${data.pagination?.total || state.schedules.length}件`;
      setPage('#schedule-page', data.pagination);
      renderPaymentTarget(null);
    }
    function selectSchedule(schedule){ state.selectedSchedule=schedule; document.querySelectorAll('#schedule-list tr').forEach(row=>row.classList.toggle('selected', row.children[0]?.textContent===schedule.invoice_number)); renderPaymentTarget(schedule); }
    function renderPaymentTarget(schedule){
      const target=document.querySelector('#payment-target'); if(!target) return;
      const form=document.querySelector('#payment-form');
      if(!schedule){ target.textContent='入金予定を選択してください。'; if(form) form.hidden=true; document.querySelector('#allocation-preview').replaceChildren(); return; }
      if(form) form.hidden=false;
      target.textContent=`${schedule.customer_name} / ${schedule.invoice_number} / 未入金 ${yen(schedule.outstanding_amount)}`;
      document.querySelector('#payment-amount').value = amountInputValue(schedule.outstanding_amount);
      renderAllocationPreview();
    }
    function renderAllocationPreview(){
      const box=document.querySelector('#allocation-preview'); if(!box || !state.selectedSchedule) return;
      const amount=Number(document.querySelector('#payment-amount').value || 0);
      const mode=document.querySelector('#payment-apply-mode').value;
      let rest=amount;
      const targets = mode === 'schedule' ? [state.selectedSchedule] : state.schedules.filter(row => row.customer_id === state.selectedSchedule.customer_id && Number(row.outstanding_amount) > 0).sort((a,b)=>(a.expected_payment_date || '').localeCompare(b.expected_payment_date || '') || a.id-b.id);
      const rows = targets.map(row => { const alloc=Math.min(rest, Number(row.outstanding_amount || 0)); rest-=alloc; return detailRow(row.invoice_number, yen(alloc)); }).filter((_,i)=>i < 8);
      if(rest > 0) rows.push(detailRow('未消込予定', yen(rest)));
      box.replaceChildren(...rows);
    }
    async function registerPayment(event){
      event.preventDefault();
      if(!state.selectedSchedule) return message('#payment-msg','入金予定を選択してください。',true);
      const mode=document.querySelector('#payment-apply-mode').value;
      const payload={ amount:amountInputValue(document.querySelector('#payment-amount').value), payment_date:document.querySelector('#payment-date').value, payment_method:'bank_transfer', reference_number:document.querySelector('#payment-reference').value || null, reason:'入金確認画面から入金登録' };
      if(mode === 'schedule') payload.payment_schedule_id = state.selectedSchedule.id; else payload.customer_id = state.selectedSchedule.customer_id;
      try{ await api('/api/v1/billing/payments',{method:'POST',body:JSON.stringify(payload)}); message('#payment-msg','入金を登録しました。'); await loadSchedules(state.page); }catch(error){ message('#payment-msg',error.message,true); }
    }

    async function loadPaymentReviews(page=1){
      const params = qs({ customer:document.querySelector('#review-customer').value.trim(), payment_date_from:document.querySelector('#review-from').value, payment_date_to:document.querySelector('#review-to').value, status:document.querySelector('#review-status').value, has_unapplied:document.querySelector('#review-unapplied-only').checked ? 1 : '', per_page:30, page });
      const data=await api(`/api/v1/billing/payments?${params}`);
      state.payments=data.payments || []; state.selectedPayment=null;
      document.querySelector('#payment-review-list').replaceChildren(...state.payments.map(payment => {
        const tr=document.createElement('tr'); tr.addEventListener('click',()=>selectPayment(payment));
        const statusCell=document.createElement('td'); statusCell.append(badge(payment.status)); const reason=paymentReviewReason(payment); if(reason) statusCell.append(reasonBadge(reason));
        tr.append(cell(payment.payment_date),cell(payment.customer_name),statusCell,cell(yen(payment.amount),'num'),cell(yen(payment.unapplied_amount),'num'),cell(payment.reference_number || ''));
        return tr;
      }));
      document.querySelector('#payment-review-count').textContent=`${data.pagination?.total || state.payments.length}件`;
      setPage('#payment-review-page', data.pagination);
      renderPaymentDetail(null);
    }
    function selectPayment(payment){ state.selectedPayment=payment; document.querySelectorAll('#payment-review-list tr').forEach(row=>row.classList.toggle('selected', row.children[0]?.textContent===payment.payment_date && row.children[1]?.textContent===payment.customer_name)); renderPaymentDetail(payment); }
    function renderPaymentDetail(payment){
      const box=document.querySelector('#payment-review-detail'); const actions=document.querySelector('#payment-review-actions'); box.replaceChildren(); actions.replaceChildren();
      if(!payment){ box.innerHTML='<p class="muted">入金を選択してください。</p>'; return; }
      const reason=paymentReviewReason(payment);
      box.append(detailRow('取引先',payment.customer_name),detailRow('入金日',payment.payment_date),detailRow('状態',labels[payment.status] || payment.status),detailRow('確認理由',reason?.label || ''),detailRow('入金額',yen(payment.amount)),detailRow('消込済額',yen(paymentAllocatedAmount(payment))),detailRow('未充当額',yen(payment.unapplied_amount)),detailRow('参照番号',payment.reference_number || ''));
      (payment.allocations || []).forEach(allocation => box.append(detailRow(`消込 ${allocation.invoice_number || allocation.invoice_header_id}`, yen(allocation.allocated_amount))));
      if(payment.cancelled_reason) box.append(detailRow('取消理由', payment.cancelled_reason));
      if(payment.status !== 'cancelled' && !payment.is_legacy_history && can.paymentCancel){ const b=document.createElement('button'); b.className='danger'; b.textContent='入金を取消'; b.onclick=()=>cancelPayment(payment); actions.append(b); }
    }
    async function cancelPayment(payment){ const reason=prompt('入金を取り消す理由を入力してください。'); if(reason===null) return; if(!reason.trim()) return alert('取消理由を入力してください。'); try{ await api(`/api/v1/billing/payments/${payment.id}/cancel`,{method:'POST',body:JSON.stringify({reason:reason.trim()})}); await loadPaymentReviews(state.page); }catch(error){ alert(error.message); } }

    async function loadReceivables(){
      const [receivableData, scheduleData] = await Promise.all([api('/api/v1/billing/receivables'), api('/api/v1/billing/payment-schedules?only_outstanding=1&per_page=200')]);
      const keyword=document.querySelector('#ar-customer').value.trim(); const kind=document.querySelector('#ar-kind').value;
      state.receivables=(receivableData.receivable_balances || []).filter(row => (!keyword || (row.customer_name || '').includes(keyword)) && (kind !== 'outstanding' || Number(row.outstanding_amount) > 0));
      document.querySelector('#ar-list').replaceChildren(...state.receivables.map(row => { const tr=document.createElement('tr'); tr.append(cell(row.customer_name),cell(yen(row.scheduled_amount),'num'),cell(yen(row.received_amount),'num'),cell(yen(row.outstanding_amount),'num'),cell(String(row.open_schedule_count + row.partial_schedule_count),'num'),cell(String(row.closed_schedule_count),'num')); return tr; }));
      const schedules=(scheduleData.payment_schedules || []).filter(row => !keyword || (row.customer_name || '').includes(keyword));
      const total=state.receivables.reduce((sum,row)=>sum+Number(row.outstanding_amount || 0),0);
      const openCount=state.receivables.reduce((sum,row)=>sum+Number(row.open_schedule_count || 0)+Number(row.partial_schedule_count || 0),0);
      const now=new Date(iso(today)); let over30=0, over60=0;
      schedules.forEach(row => { const days=Math.floor((now - new Date(row.expected_payment_date)) / 86400000); if(days > 30) over30 += Number(row.outstanding_amount || 0); if(days > 60) over60 += Number(row.outstanding_amount || 0); });
      document.querySelector('#ar-total').textContent=yen(total); document.querySelector('#ar-open').textContent=String(openCount); document.querySelector('#ar-over30').textContent=yen(over30); document.querySelector('#ar-over60').textContent=yen(over60); document.querySelector('#ar-count').textContent=`${state.receivables.length}件`;
      document.querySelector('#aging-list').replaceChildren(...schedules.map(row => { const days=Math.max(0, Math.floor((now - new Date(row.expected_payment_date)) / 86400000)); const tr=document.createElement('tr'); const statusCell=document.createElement('td'); statusCell.append(badge(row.status)); tr.append(cell(row.invoice_number),cell(row.customer_name),cell(row.expected_payment_date),statusCell,cell(yen(row.outstanding_amount),'num'),cell(`${days}日`,'num')); return tr; }));
      document.querySelector('#aging-count').textContent=`${schedules.length}件`;
    }

    function init(){
      if(screen === 'monthly-invoices'){
        document.querySelector('#monthly-billing-month').value=iso(today).slice(0,7);
        document.querySelector('#monthly-refresh').onclick=loadMonthly; document.querySelector('#monthly-customer').addEventListener('input',loadMonthly);
        document.querySelector('#monthly-billing-month').addEventListener('change',loadMonthly);
        document.querySelector('#monthly-closing-day').addEventListener('change',loadMonthly);
        document.querySelector('#monthly-create-selected')?.addEventListener('click',async()=>{
          const selections=[...document.querySelectorAll('#monthly-list input[type="checkbox"]:checked')].map(input=>input.dataset.customerId);
          for(const item of selections) {
            const row = (state.monthlyRows || []).find(candidate => String(candidate.customer_id) === String(item));
            if(row) await createMonthlyInvoice(row);
          }
        });
        loadMonthly();
      }
      if(screen === 'spot-invoices'){
        state.selectedSpotShipmentIds = new Set();
        document.querySelector('#spot-from').value=monthStart();
        document.querySelector('#spot-to').value=monthEnd();
        document.querySelector('#spot-invoice-date').value=iso(today);
        document.querySelector('#spot-refresh').onclick=loadSpotInvoices;
        document.querySelector('#spot-customer').addEventListener('input',loadSpotInvoices);
        document.querySelector('#spot-from').addEventListener('change',loadSpotInvoices);
        document.querySelector('#spot-to').addEventListener('change',loadSpotInvoices);
        document.querySelector('#spot-create-selected')?.addEventListener('click',createSelectedSpotInvoices);
        loadSpotInvoices();
      }
      if(screen === 'invoices'){
        document.querySelector('#invoice-month').value=iso(today).slice(0,7);
        document.querySelector('#invoice-search').onclick=()=>loadInvoices(1); document.querySelector('#invoice-prev').onclick=()=>state.page>1&&loadInvoices(state.page-1); document.querySelector('#invoice-next').onclick=()=>state.page<state.lastPage&&loadInvoices(state.page+1); document.querySelector('#invoice-issue-select-all')?.addEventListener('change',event=>toggleInvoiceSelectAll('issue', event.target.checked)); document.querySelector('#invoice-schedule-select-all')?.addEventListener('change',event=>toggleInvoiceSelectAll('schedule', event.target.checked)); document.querySelector('#invoice-confirm-selected')?.addEventListener('click',confirmSelectedInvoices); document.querySelector('#invoice-create-schedules')?.addEventListener('click',createSelectedSchedules); loadInvoices();
      }
      if(screen === 'invoice-print'){
        document.querySelector('#print-invoice-month').value=iso(today).slice(0,7);
        document.querySelector('#print-invoice-search').onclick=()=>loadPrintableInvoices(1);
        document.querySelector('#print-invoice-prev').onclick=()=>state.page>1&&loadPrintableInvoices(state.page-1);
        document.querySelector('#print-invoice-next').onclick=()=>state.page<state.lastPage&&loadPrintableInvoices(state.page+1);
        document.querySelector('#print-invoice-select-all')?.addEventListener('change',togglePrintableInvoiceSelectAll);
        document.querySelector('#print-invoice-selected')?.addEventListener('click',printSelectedPrintableInvoices);
        loadPrintableInvoices();
      }
      if(screen === 'payment-confirmation'){
        document.querySelector('#schedule-month').value=iso(today).slice(0,7); document.querySelector('#payment-date').value=iso(today);
        document.querySelector('#schedule-search').onclick=()=>loadSchedules(1); document.querySelector('#schedule-prev').onclick=()=>state.page>1&&loadSchedules(state.page-1); document.querySelector('#schedule-next').onclick=()=>state.page<state.lastPage&&loadSchedules(state.page+1);
        document.querySelector('#payment-form')?.addEventListener('submit',registerPayment); document.querySelector('#payment-amount')?.addEventListener('input',renderAllocationPreview); document.querySelector('#payment-amount')?.addEventListener('blur',event=>{ event.target.value = amountInputValue(event.target.value); renderAllocationPreview(); }); document.querySelector('#payment-apply-mode')?.addEventListener('change',renderAllocationPreview); loadSchedules();
      }
      if(screen === 'payment-reviews'){
        document.querySelector('#review-from').value=monthStart(); document.querySelector('#review-to').value=monthEnd();
        document.querySelector('#payment-review-search').onclick=()=>loadPaymentReviews(1); document.querySelector('#payment-review-prev').onclick=()=>state.page>1&&loadPaymentReviews(state.page-1); document.querySelector('#payment-review-next').onclick=()=>state.page<state.lastPage&&loadPaymentReviews(state.page+1); loadPaymentReviews();
      }
      if(screen === 'receivables'){
        document.querySelector('#ar-refresh').onclick=loadReceivables; document.querySelector('#ar-customer').addEventListener('input',loadReceivables); document.querySelector('#ar-kind').addEventListener('change',loadReceivables); loadReceivables();
      }
    }
    init();
  </script>
</body>
</html>
