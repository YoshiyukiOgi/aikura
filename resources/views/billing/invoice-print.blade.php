@php
  $customer = $invoice->customer;
  $formatQuantity = function ($value): string {
      return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
  };
  $formatMoney = fn ($value): string => '¥'.number_format((float) $value);
  $formatRate = fn ($value): string => $value === null ? '-' : rtrim(rtrim(number_format((float) $value * 100, 2, '.', ''), '0'), '.').'%';
  $shipmentNumbers = $invoice->lines
      ->map(fn ($line) => $line->shipmentHeader?->document_number)
      ->filter()
      ->unique()
      ->values();
@endphp
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>請求書 {{ $invoice->invoice_number }}</title>
  <style>
    @page{size:A4 landscape;margin:10mm}
    body{margin:32px;color:#172033;font:13px "Noto Sans JP",Meiryo,sans-serif}
    .no-print{margin-bottom:14px}
    button{font:inherit;border:1px solid #94a3b8;border-radius:4px;background:#fff;padding:7px 10px;cursor:pointer}
    h1{font-size:25px;margin:0 0 18px;text-align:center;letter-spacing:.12em}
    .top{display:grid;grid-template-columns:1fr 280px;gap:26px;margin-bottom:18px}
    .customer{border-bottom:2px solid #172033;padding:6px 0 10px}
    .customer-name{font-size:18px;font-weight:800;margin-bottom:8px}
    .muted{color:#64748b;font-size:11px}
    .notice{margin:0 0 12px;color:#9a6700;font-size:12px}
    .meta{border:1px solid #94a3b8;border-bottom:0}
    .meta div{display:grid;grid-template-columns:96px 1fr;border-bottom:1px solid #94a3b8}
    .meta span{padding:6px 8px}.meta span:first-child{background:#f1f5f9;color:#475569;font-size:11px;font-weight:800}
    .message{font-size:14px;line-height:1.7;margin:0 0 14px}
    table{border-collapse:collapse;width:100%}
    th,td{border:1px solid #94a3b8;padding:7px 8px;text-align:left;vertical-align:top}
    th{background:#f1f5f9;font-size:11px;color:#334155}
    .num{text-align:right;white-space:nowrap}
    .code{width:92px}.qty{width:72px}.unit{width:58px}.money{width:94px}.tax{width:60px}.date{width:92px}
    .summary{display:grid;grid-template-columns:1fr 330px;gap:18px;margin-top:14px;align-items:start}
    .summary-table td:first-child{background:#f8fafc;font-weight:800;color:#475569}.summary-table td{padding:8px 10px}
    .note{border:1px solid #cbd5e1;min-height:76px;padding:8px;font-size:12px;color:#475569}
    .footnote{margin-top:8px;color:#64748b;font-size:11px;line-height:1.5}
    @media print{body{margin:0}.no-print{display:none}a{color:inherit;text-decoration:none}}
  </style>
</head>
<body>
  <button class="no-print" onclick="window.print()">印刷 / PDF保存</button>
  <h1>請求書</h1>
  @if($invoice->document_type === 'credit_memo')
    <p class="notice">この帳票は赤伝です。</p>
  @endif
  <div class="top">
    <div>
      <div class="customer">
        <div class="customer-name">{{ $customer?->billing_name ?: $customer?->name }} 御中</div>
        <div>{{ trim(($customer?->postal_code ? '〒'.$customer->postal_code.' ' : '').($customer?->address1 ?? '').' '.($customer?->address2 ?? '')) }}</div>
        <div class="muted">{{ $customer?->phone ? 'TEL '.$customer->phone : '' }} {{ $customer?->fax ? ' / FAX '.$customer->fax : '' }}</div>
      </div>
      <p class="message">下記の通りご請求申し上げます。</p>
    </div>
    <div class="meta">
      <div><span>請求番号</span><span>{{ $invoice->invoice_number }}</span></div>
      <div><span>請求日</span><span>{{ $invoice->invoice_date?->format('Y/m/d') ?? '-' }}</span></div>
      <div><span>支払期日</span><span>{{ $invoice->due_date?->format('Y/m/d') ?? '-' }}</span></div>
      <div><span>請求方式</span><span>{{ $invoice->billingCycle?->billing_method === 'per_shipment' ? '都度請求' : '締め請求' }}</span></div>
      <div><span>対象期間</span><span>{{ $invoice->billing_period_start?->format('Y/m/d') ?? '-' }} - {{ $invoice->billing_period_end?->format('Y/m/d') ?? '-' }}</span></div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th class="date">出荷日</th>
        <th>出荷番号</th>
        <th class="code">商品コード</th>
        <th>商品名</th>
        <th class="qty num">数量</th>
        <th class="unit">単位</th>
        <th class="money num">単価</th>
        <th class="money num">金額</th>
        <th class="tax num">税率</th>
        <th class="money num">消費税</th>
        <th class="money num">税込</th>
      </tr>
    </thead>
    <tbody>
      @foreach($invoice->lines as $line)
        <tr>
          <td>{{ $line->shipmentHeader?->document_date?->format('Y/m/d') ?? '-' }}</td>
          <td>{{ $line->shipmentHeader?->document_number ?? '-' }}</td>
          <td>{{ $line->product_code }}</td>
          <td>{{ $line->display_name ?: $line->product_name }}</td>
          <td class="num">{{ $formatQuantity($line->quantity) }}</td>
          <td>{{ $line->unit_name }}</td>
          <td class="num">{{ $formatMoney($line->unit_price) }}</td>
          <td class="num">{{ $formatMoney($line->amount) }}</td>
          <td class="num">{{ $formatRate($line->tax_rate) }}</td>
          <td class="num">{{ $formatMoney($line->tax_amount) }}</td>
          <td class="num">{{ $formatMoney($line->total_amount) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <div class="summary">
    <div class="note">
      <strong>備考</strong><br>
      {{ $invoice->note ?: ' ' }}
      <div class="footnote">
        対象出荷: {{ $shipmentNumbers->isEmpty() ? '-' : $shipmentNumbers->join('、') }}
      </div>
    </div>
    <table class="summary-table">
      <tbody>
        <tr><td>前回未入金</td><td class="num">{{ $formatMoney($invoice->previous_balance_amount) }}</td></tr>
        <tr><td>期間内入金</td><td class="num">{{ $formatMoney($invoice->period_payment_amount) }}</td></tr>
        <tr><td>繰越額</td><td class="num">{{ $formatMoney($invoice->carried_forward_amount) }}</td></tr>
        <tr><td>税抜合計</td><td class="num">{{ $formatMoney($invoice->subtotal_amount) }}</td></tr>
        <tr><td>消費税</td><td class="num">{{ $formatMoney($invoice->tax_amount) }}</td></tr>
        <tr><td>今回請求額</td><td class="num">{{ $formatMoney($invoice->total_amount) }}</td></tr>
      </tbody>
    </table>
  </div>
</body>
</html>
