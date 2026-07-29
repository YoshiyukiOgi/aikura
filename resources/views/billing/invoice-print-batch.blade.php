@php
  $formatQuantity = function ($value): string {
      return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
  };
  $formatMoney = fn ($value): string => '¥'.number_format((float) $value);
  $formatRate = fn ($value): string => $value === null ? '-' : rtrim(rtrim(number_format((float) $value * 100, 2, '.', ''), '0'), '.').'%';
  $formatCapacity = function ($value, $unit): string {
      if ($value === null || $value === '') {
          return '-';
      }

      $number = rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
      $unitLabel = $unit?->symbol ?: $unit?->name;

      return $unitLabel ? $number.$unitLabel : $number;
  };
  $stripCapacity = function (?string $name, ?string $capacityLabel): string {
      $name = trim((string) $name);

      if ($name === '') {
          return '-';
      }

      if ($capacityLabel !== null && $capacityLabel !== '' && str_ends_with($name, $capacityLabel)) {
          $base = trim(substr($name, 0, strlen($name) - strlen($capacityLabel)));
          if ($base !== '') {
              return $base;
          }
      }

      $fallback = preg_replace('/\s*(?:\d+(?:\.\d+)?(?:ml|mL|l|L|cc|g|kg|本|缶|袋|箱|個|入|ケース))$/u', '', $name);
      $fallback = trim($fallback ?? '');

      return $fallback !== '' ? $fallback : $name;
  };
  $sortInvoiceLines = fn ($lines) => $lines
      ->sort(function ($a, $b): int {
          $aKey = [
              $a->shipmentHeader?->document_date?->format('Y-m-d') ?? '9999-12-31',
              (string) ($a->shipmentHeader?->document_number ?? ''),
              (int) ($a->shipment_line_id ?? $a->id),
          ];
          $bKey = [
              $b->shipmentHeader?->document_date?->format('Y-m-d') ?? '9999-12-31',
              (string) ($b->shipmentHeader?->document_number ?? ''),
              (int) ($b->shipment_line_id ?? $b->id),
          ];

          return $aKey <=> $bKey;
      })
      ->values();
  $groupedInvoiceLines = fn ($lines) => $lines
      ->groupBy(fn ($line) => $line->tax_rate === null ? 'none' : (string) $line->tax_rate)
      ->sortByDesc(fn ($lines, $key) => $key === 'none' ? -1 : (float) $key);
@endphp
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title></title>
  <style>
    @page{size:A4 portrait;margin:10mm}
    body{margin:22px;color:#172033;font:9px "Noto Sans JP",Meiryo,sans-serif}
    .no-print{margin-bottom:10px}
    button{font:inherit;border:1px solid #94a3b8;border-radius:4px;background:#fff;padding:6px 10px;cursor:pointer}
    .invoice-page{page-break-after:always;break-after:page}
    .invoice-page:last-child{page-break-after:auto;break-after:auto}
    h1{font-size:18px;margin:0 0 12px;text-align:center;letter-spacing:.12em}
    .top{display:grid;grid-template-columns:1fr 210px;gap:14px;margin-bottom:12px}
    .customer{border-bottom:2px solid #172033;padding:4px 0 8px}
    .customer-name{font-size:15px;font-weight:800;margin-bottom:6px}
    .muted{color:#64748b;font-size:10px}
    .notice{margin:0 0 10px;color:#9a6700;font-size:10px}
    .issuer{align-self:start}
    .issuer-name{margin:0 0 5px;text-align:right;font-size:12px;font-weight:800}
    .meta{border:1px solid #94a3b8;align-self:start}
    .meta div{display:grid;grid-template-columns:82px 1fr;border-bottom:1px solid #94a3b8}
    .meta div:last-child{border-bottom:0}
    .meta span{padding:3px 5px;font-size:9px;line-height:1.2}
    .meta span:first-child{background:#f1f5f9;color:#475569;font-weight:800}
    .message{font-size:11px;line-height:1.6;margin:0 0 10px}
    table{border-collapse:collapse;width:100%;table-layout:fixed}
    th,td{border:1px solid #94a3b8;padding:4px 5px;text-align:left;vertical-align:top}
    th{background:#f1f5f9;font-size:8px;color:#334155}
    tbody tr{break-inside:avoid;page-break-inside:avoid}
    .num{text-align:right;white-space:nowrap}
    .date{width:54px}
    .shipment-no{width:64px;font-size:8px}
    .product-name{width:162px;font-size:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .capacity{width:36px;white-space:nowrap}
    .qty{width:17px}
    .unit{width:21px}
    .unit-price{width:57px}
    .money{width:64px}
    .note-col{width:138px;font-size:7px;white-space:normal;word-break:break-word}
    .tax-group-title{margin:10px 0 4px;font-size:9px;font-weight:800;color:#334155}
    .detail-table{margin-bottom:10px}
    .group-summary td{background:#f8fafc;font-weight:800;padding-top:7px;padding-bottom:7px;border-top:3px double #94a3b8}
    .group-summary .group-label{text-align:left;color:#475569}
    .group-summary .group-label{text-align:right}
    .summary-head{width:100%;margin:0 0 10px}
    .summary-table{table-layout:auto}
    .summary-table td{padding:5px 6px;font-size:9px}
    .summary-table .label{background:#f8fafc;font-weight:800;color:#475569;white-space:nowrap}
    .summary-table .grand-label{background:#f1f5f9;font-weight:900;color:#172033;white-space:nowrap}
    .summary-table .grand-value{font-size:12px;font-weight:900}
    .summary-table .blank{border:0;background:#fff}
    .note{border:1px solid #cbd5e1;min-height:64px;padding:7px;font-size:10px;color:#475569;margin-top:10px}
    .footnote{margin-top:7px;color:#64748b;font-size:9px;line-height:1.5}
    .invoice-footer-details{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:10px;font-size:8px;color:#334155}
    .invoice-footer-details.company-only{grid-template-columns:1fr}
    .company-details{line-height:1.5}
    .company-details strong,.bank-details strong{display:block;margin-bottom:4px;font-size:9px}
    .bank-account-text{white-space:pre-line;line-height:1.4;padding:0 0 3px;border-bottom:1px dashed #94a3b8}
    .bank-account-text + .bank-account-text{margin-top:4px}
    @media print{body{margin:0}.no-print{display:none}a{color:inherit;text-decoration:none}.invoice-page{break-after:page;page-break-after:always}.invoice-page:last-child{break-after:auto;page-break-after:auto}}
  </style>
</head>
<body>
  <button class="no-print" onclick="window.print()">印刷 / PDF出力</button>
  @foreach($invoices as $invoice)
    @php
      $customer = $invoice->customer;
      $sortedLines = $sortInvoiceLines($invoice->lines);
      $shipmentNumbers = $sortedLines
          ->map(fn ($line) => $line->shipmentHeader?->document_number)
          ->filter()
          ->unique()
          ->values();
      $taxGroups = $groupedInvoiceLines($sortedLines);
    @endphp
    <section class="invoice-page">
      <h1>請求書</h1>
      @if($invoice->document_type === 'credit_memo')
        <p class="notice">この伝票は赤伝です。</p>
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
        <div class="issuer">
          <div class="issuer-name">{{ $companyInformation['name'] }}</div>
          <div class="meta">
            <div><span>請求番号</span><span>{{ $invoice->invoice_number }}</span></div>
            <div><span>請求日</span><span>{{ $invoice->invoice_date?->format('Y/m/d') ?? '-' }}</span></div>
            <div><span>支払期限</span><span>{{ $invoice->due_date?->format('Y/m/d') ?? '-' }}</span></div>
            <div><span>請求方法</span><span>{{ $invoice->billingCycle?->billing_method === 'per_shipment' ? '都度請求' : '月締め請求' }}</span></div>
            <div><span>対象期間</span><span>{{ $invoice->billing_period_start?->format('Y/m/d') ?? '-' }} - {{ $invoice->billing_period_end?->format('Y/m/d') ?? '-' }}</span></div>
          </div>
        </div>
      </div>

      <div class="summary-head">
        <table class="summary-table">
          <tbody>
            <tr>
          <td class="label">前回ご請求額</td><td class="num">{{ $formatMoney($invoice->previous_balance_amount) }}</td>
          <td class="label">ご入金額</td><td class="num">{{ $formatMoney($invoice->period_payment_amount) }}</td>
          <td class="label">繰越金額</td><td class="num">{{ $formatMoney($invoice->carried_forward_amount) }}</td>
        </tr>
        <tr>
          <td class="label">当月お買上額</td><td class="num">{{ $formatMoney($invoice->subtotal_amount) }}</td>
          <td class="label">消費税額</td><td class="num">{{ $formatMoney($invoice->tax_amount) }}</td>
          <td class="label">当月税込お買上額</td><td class="num">{{ $formatMoney($invoice->current_invoice_amount) }}</td>
        </tr>
        <tr>
          <td class="blank" colspan="4"></td>
          <td class="grand-label">今回ご請求額</td><td class="num grand-value">{{ $formatMoney($invoice->total_amount) }}</td>
        </tr>
      </tbody>
    </table>
  </div>

      @foreach($taxGroups as $taxRate => $lines)
        @php
          $groupRate = $taxRate === 'none' ? null : (float) $taxRate;
          $groupTaxTotal = $lines->sum(fn ($line) => (float) $line->tax_amount);
          $groupSubtotal = $lines->sum(fn ($line) => (float) $line->amount);
          $groupTotal = $lines->sum(fn ($line) => (float) $line->total_amount);
          $previousShipmentDate = null;
          $previousShipmentNumber = null;
        @endphp
    <div class="tax-group-title">消費税率 {{ $formatRate($groupRate) }}</div>
    <table class="detail-table">
      <thead>
        <tr>
          <th class="date">出荷日</th>
          <th class="shipment-no">出荷番号</th>
          <th class="product-name">商品名</th>
          <th class="capacity">容量</th>
          <th class="qty">数量</th>
          <th class="unit">単位</th>
          <th class="unit-price">単価</th>
          <th class="money">金額</th>
          <th class="note-col">備考</th>
        </tr>
      </thead>
          <tbody>
            @foreach($lines as $line)
          @php
            $shipmentDate = $line->shipmentHeader?->document_date?->format('Y/m/d');
            $shipmentNumber = $line->shipmentHeader?->document_number;
            $hideShipmentInfo = $shipmentDate === $previousShipmentDate && $shipmentNumber === $previousShipmentNumber;
            $previousShipmentDate = $shipmentDate;
            $previousShipmentNumber = $shipmentNumber;
            $capacityLabel = $formatCapacity(
                $line->shipmentLine?->confirmed_capacity_value,
                $line->shipmentLine?->confirmedCapacityUnit,
            );
            $productName = $stripCapacity($line->display_name ?: $line->product_name, $capacityLabel);
            $lineNote = trim((string) ($line->note ?: $line->shipmentLine?->note ?: ''));
          @endphp
          <tr>
            <td>{{ $hideShipmentInfo ? '' : ($shipmentDate ?? '-') }}</td>
            <td class="shipment-no">{{ $hideShipmentInfo ? '' : ($shipmentNumber ?? '-') }}</td>
            <td class="product-name">{{ $productName }}</td>
            <td class="capacity">{{ $capacityLabel }}</td>
            <td class="num">{{ $formatQuantity($line->quantity) }}</td>
            <td>{{ $line->unit_name }}</td>
            <td class="num">{{ $formatMoney($line->unit_price) }}</td>
            <td class="num">{{ $formatMoney($line->amount) }}</td>
                <td class="note-col">{{ $lineNote !== '' ? $lineNote : '-' }}</td>
              </tr>
            @endforeach
            <tr class="group-summary">
              <td class="group-label" colspan="6">{{ $formatRate($groupRate) }} 税合計・商品合計・税込合計</td>
              <td class="num">{{ $formatMoney($groupTaxTotal) }}</td>
              <td class="num">{{ $formatMoney($groupSubtotal) }}</td>
              <td class="num">{{ $formatMoney($groupTotal) }}</td>
            </tr>
          </tbody>
        </table>
      @endforeach

      @include('billing.partials.invoice-bank-accounts')

      <div class="note">
        <strong>備考</strong><br>
        {{ $invoice->note ?: ' ' }}
        <div class="footnote">
          出荷番号: {{ $shipmentNumbers->isEmpty() ? '-' : $shipmentNumbers->join('、') }}
        </div>
      </div>
    </section>
  @endforeach
</body>
</html>
