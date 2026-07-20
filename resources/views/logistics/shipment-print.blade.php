@php
  $instruction = $shipment->sourceShipmentInstruction ?? $shipment->sourceShipmentPick?->shipmentInstruction;
  $salesOrder = $instruction?->lines->first()?->salesOrder;
  $customer = $shipment->customer;
  $taxCalculationUnit = $customer?->tax_calculation_unit ?: 'line';
  $taxRoundingMethod = $customer?->tax_rounding_method ?: 'floor';
  $shipmentNote = trim((string) $shipment->note);
  if ($shipmentNote === '受注画面から即時出荷指示') {
      $shipmentNote = '';
  }

  $formatQuantity = function ($value): string {
      return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
  };
  $formatMoney = fn ($value): string => '¥'.number_format((float) $value);
  $formatRate = fn ($value): string => $value === null ? '-' : rtrim(rtrim(number_format((float) $value * 100, 2, '.', ''), '0'), '.').'%';
  $bc = fn ($value, int $scale = 2): string => bcadd((string) ($value ?? '0'), '0', $scale);
  $roundTax = function (string $amount, string $method, int $scale = 2): string {
      $amount = bcadd($amount, '0', $scale + 1);
      $truncated = bcadd($amount, '0', $scale);
      if ($method === 'ceil' && bccomp($amount, $truncated, $scale + 1) === 1) {
          return bcadd($truncated, '0.'.str_repeat('0', max(0, $scale - 1)).'1', $scale);
      }
      if ($method === 'round') {
          return bcadd($amount, '0.'.str_repeat('0', $scale).'5', $scale);
      }
      return $truncated;
  };

  $subtotal = '0.00';
  $taxTotal = '0.00';
  $taxGroups = [];
  $sectionTaxTotals = [];
  $rows = [];
  $taxDate = ($shipment->document_date ?? now())->toDateString();
  $resolveTaxRate = function ($line) use ($taxDate) {
      if ($line->confirmed_consumption_tax_rate !== null) {
          return [
              'rate' => $line->confirmed_consumption_tax_rate,
              'taxability' => $line->confirmed_consumption_taxability,
          ];
      }

      $category = $line->product?->consumptionTaxCategory;
      if ($category === null || ! $category->requires_tax_rate) {
          return [
              'rate' => null,
              'taxability' => $category?->taxability,
          ];
      }

      $rate = \App\Models\ConsumptionTaxRate::query()
          ->where('consumption_tax_category_id', $category->id)
          ->where('is_active', true)
          ->whereDate('effective_from', '<=', $taxDate)
          ->where(function ($query) use ($taxDate): void {
              $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $taxDate);
          })
          ->orderByDesc('effective_from')
          ->first();

      return [
          'rate' => $rate?->rate,
          'taxability' => $category->taxability,
      ];
  };

  foreach ($shipment->lines as $line) {
      $quantity = $bc($line->confirmed_quantity ?? $line->quantity, 4);
      $unitPrice = $bc($line->confirmed_unit_price ?? $line->draft_unit_price ?? '0', 4);
      $amount = bcmul($quantity, $unitPrice, 2);
      $taxInfo = $resolveTaxRate($line);
      $taxRate = $taxInfo['rate'];
      $taxable = $taxInfo['taxability'] === 'taxable' && $taxRate !== null;
      $taxKey = $taxable ? (string) $taxRate : 'non_taxable';
      $lineTax = '0.00';

      if ($taxable) {
          if ($taxCalculationUnit === 'line') {
              $lineTax = $roundTax(bcmul($amount, (string) $taxRate, 4), $taxRoundingMethod, 2);
              $taxTotal = bcadd($taxTotal, $lineTax, 2);
              $sectionTaxTotals[$taxKey] = bcadd($sectionTaxTotals[$taxKey] ?? '0.00', $lineTax, 2);
          } else {
              $groupKey = (string) $taxRate;
              $taxGroups[$groupKey] = bcadd($taxGroups[$groupKey] ?? '0.00', $amount, 2);
          }
      } else {
          $sectionTaxTotals[$taxKey] = $sectionTaxTotals[$taxKey] ?? '0.00';
      }

      $lineTotal = bcadd($amount, $taxCalculationUnit === 'line' ? $lineTax : '0.00', 2);
      $subtotal = bcadd($subtotal, $amount, 2);

      $rows[] = [
          'code' => $line->confirmed_product_code ?: $line->product?->product_code,
          'name' => $line->confirmed_display_name ?: $line->confirmed_product_name ?: $line->product?->name,
          'quantity' => $quantity,
          'unit' => $line->confirmed_unit_name ?: $line->unit?->name,
          'unit_price' => $unitPrice,
          'amount' => $amount,
          'lots' => $line->lotAllocations->whereIn('status', ['allocated', 'confirmed'])->whereNull('cancelled_at')->map(fn ($allocation) => ($allocation->productionLot?->lot_code ?? '-').' / '.$allocation->quantity.'本 / '.($allocation->actual_alcohol_percentage ?? $allocation->productionLot?->alcohol_percentage ?? '-').'%')->implode('、'),
          'tax_rate' => $taxRate,
          'tax_key' => $taxKey,
          'tax' => $taxCalculationUnit === 'line' ? $lineTax : null,
          'total' => $lineTotal,
      ];
  }

  if ($taxCalculationUnit !== 'line') {
      foreach ($taxGroups as $taxRate => $amount) {
          $groupTax = $roundTax(bcmul($amount, $taxRate, 4), $taxRoundingMethod, 2);
          $sectionTaxTotals[(string) $taxRate] = $groupTax;
          $taxTotal = bcadd($taxTotal, $groupTax, 2);
      }
  }

  $grandTotal = bcadd($subtotal, $taxTotal, 2);
  $taxSections = collect($rows)
      ->groupBy('tax_key')
      ->sortByDesc(fn ($sectionRows, $key) => $key === 'non_taxable' ? -1 : (float) $key)
      ->map(function ($sectionRows, $key) use ($sectionTaxTotals, $formatRate) {
          $sectionSubtotal = $sectionRows->reduce(fn ($carry, $row) => bcadd($carry, $row['amount'], 2), '0.00');
          $sectionTax = $sectionTaxTotals[$key] ?? '0.00';

          return [
              'label' => $key === 'non_taxable' ? '非課税' : $formatRate($key),
              'rows' => $sectionRows,
              'subtotal' => $sectionSubtotal,
              'tax' => $sectionTax,
              'total' => bcadd($sectionSubtotal, $sectionTax, 2),
          ];
      });
@endphp
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>出荷伝票</title>
  <style>
    body{margin:32px;color:#172033;font:13px "Noto Sans JP",Meiryo,sans-serif}
    .toolbar{display:flex;align-items:center;justify-content:flex-start;margin-bottom:8px}
    button{font:inherit;border:1px solid #94a3b8;border-radius:4px;background:#fff;padding:7px 10px;cursor:pointer}
    h1{font-size:24px;margin:0 0 16px;text-align:center;letter-spacing:.08em}
    .top{display:grid;grid-template-columns:1fr 320px;gap:24px;margin-bottom:18px}
    .customer{border-bottom:2px solid #172033;padding:6px 0 10px}
    .customer-name{font-size:18px;font-weight:800;margin-bottom:8px}
    .muted{color:#64748b;font-size:11px}.notice{margin:0 0 12px;color:#9a6700;font-size:12px}
    .meta{border:1px solid #94a3b8;border-bottom:0}
    .meta div{display:grid;grid-template-columns:116px 1fr;border-bottom:1px solid #94a3b8}
    .meta span{padding:6px 8px;font-size:12px}.meta span:first-child{background:#f1f5f9;color:#475569;font-size:10px;font-weight:800;border-right:1px solid #94a3b8}
    table{border-collapse:collapse;width:100%}
    th,td{border:1px solid #94a3b8;padding:7px 8px;text-align:left;vertical-align:top}
    th{background:#f1f5f9;font-size:11px;color:#334155}
    .num{text-align:right;white-space:nowrap}.code{width:130px}.qty{width:72px}.unit{width:58px}.money{width:92px}.tax{width:74px}
    .tax-section{margin-top:14px}.tax-heading{font-size:11px;font-weight:400;margin:0 0 4px;color:#475569}
    .section-total td{border:0;background:#fff;font-weight:400;padding:10px 0 4px;line-height:1.3}.section-total-box{display:grid;grid-template-columns:max-content 190px;gap:12px;width:max-content;margin-left:auto;border-bottom:1px solid #172033}.section-total-label{text-align:right;white-space:nowrap}
    .summary{display:grid;grid-template-columns:1fr 320px;gap:18px;margin-top:14px;align-items:start}
    .summary-table td:first-child{background:#f8fafc;font-weight:800;color:#475569}.summary-table td{padding:8px 10px}
    .note{border:1px solid #cbd5e1;min-height:72px;padding:8px;font-size:12px;color:#475569}
    .footnote{margin-top:8px;color:#64748b;font-size:11px;line-height:1.5}
    @media print{body{margin:12mm}.no-print{display:none}.toolbar{display:none}a{color:inherit;text-decoration:none}}
  </style>
</head>
<body>
  <div class="toolbar"><button class="no-print" onclick="printShipmentSlip()">印刷する</button></div>
  <h1>出荷伝票</h1>
  <div class="top">
    <div class="customer">
      <div class="customer-name">{{ $customer?->billing_name ?: $customer?->name }} 御中</div>
      <div>{{ trim(($customer?->postal_code ? '〒'.$customer->postal_code.' ' : '').($customer?->address1 ?? '').' '.($customer?->address2 ?? '')) }}</div>
      <div class="muted">{{ $customer?->phone ? 'TEL '.$customer->phone : '' }} {{ $customer?->fax ? ' / FAX '.$customer->fax : '' }}</div>
      <div class="muted">取引先注文番号 {{ $salesOrder?->customer_order_number ?: '-' }}</div>
    </div>
    <div>
      <div class="meta">
        <div class="shipment-date"><span>出荷日</span><span>{{ $shipment->document_date?->format('Y/m/d') ?? '-' }}</span></div>
        <div><span>出荷番号</span><span>{{ $shipment->document_number }}</span></div>
        <div><span>受注番号</span><span>{{ $salesOrder?->order_number ?? '-' }}</span></div>
      </div>
    </div>
  </div>

  @foreach($taxSections as $section)
    <section class="tax-section">
      <h2 class="tax-heading">消費税 {{ $section['label'] }}</h2>
      <table>
        <thead>
          <tr>
            <th class="code">商品コード</th>
            <th>商品名</th>
            <th>ロット・実測度数</th>
            <th class="qty num">数量</th>
            <th class="unit">単位</th>
            <th class="money num">単価</th>
            <th class="money num">金額</th>
          </tr>
        </thead>
        <tbody>
          @foreach($section['rows'] as $row)
            <tr>
              <td>{{ $row['code'] }}</td>
              <td>{{ $row['name'] }}</td>
              <td>{{ $row['lots'] ?: '-' }}</td>
              <td class="num">{{ $formatQuantity($row['quantity']) }}</td>
              <td>{{ $row['unit'] }}</td>
              <td class="num">{{ $formatMoney($row['unit_price']) }}</td>
              <td class="num">{{ $formatMoney($row['amount']) }}</td>
            </tr>
          @endforeach
          <tr class="section-total">
            <td colspan="7"><div class="section-total-box"><span class="section-total-label">税込小計（内消費税{{ $section['label'] }}）</span><span class="num">{{ $formatMoney($section['total']) }}（{{ $formatMoney($section['tax']) }}）</span></div></td>
          </tr>
        </tbody>
      </table>
    </section>
  @endforeach

  <div class="summary">
    <div class="note">
      <strong>備考</strong><br>
      {{ $shipmentNote ?: ' ' }}
    </div>
    <table class="summary-table">
      <tbody>
        <tr><td>税抜合計</td><td class="num">{{ $formatMoney($subtotal) }}</td></tr>
        <tr><td>消費税</td><td class="num">{{ $formatMoney($taxTotal) }}</td></tr>
        <tr><td>税込合計</td><td class="num">{{ $formatMoney($grandTotal) }}</td></tr>
      </tbody>
    </table>
  </div>
<script>
  const shipmentPrintTitle = document.title;
  const blankPrintTitle = '　　　　　　　　　　　　　　　　';
  const printShipmentSlip = () => {
    document.title = blankPrintTitle;
    window.print();
  };
  window.addEventListener('beforeprint', () => {
    document.title = blankPrintTitle;
  });
  window.addEventListener('afterprint', () => {
    document.title = shipmentPrintTitle;
  });
</script>
</body>
</html>
