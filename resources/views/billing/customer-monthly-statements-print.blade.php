@php
  $fmt = fn($v) => (float) $v == 0.0 ? '' : number_format((float) $v);
  $fmtTotal = fn($v) => number_format((float) $v);
  $monthLabel = sprintf('%04d年%02d月', $year, $month);
  $amountProps = [
    'invoiceAmount',
    'sakeShipmentAmount',
    'sakeReturnAmount',
    'kasuShipmentAmount',
    'otherShipmentAmount',
    'discountAmount',
    'taxAmount',
    'paymentAmount',
    'bankFeeAmount',
    'previousInvoiceAmount',
    'receivableBalanceAmount',
  ];
  $totalKeys = [
    'invoice_amount',
    'sake_shipment_amount',
    'sake_return_amount',
    'kasu_shipment_amount',
    'other_shipment_amount',
    'discount_amount',
    'tax_amount',
    'payment_amount',
    'bank_fee_amount',
    'previous_invoice_amount',
    'receivable_balance_amount',
  ];
  $categoryTotalsByName = collect($categoryTotals)->keyBy('settlement_receivable_category');
  $groupedRows = $rows->groupBy(fn($row) => $row->settlementReceivableCategory ?: '未設定');
@endphp
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>取引先別月計／売掛残高一覧 {{ $monthLabel }}</title>
  <style>
    @page{size:A4 landscape;margin:8mm}
    *{box-sizing:border-box}
    body{margin:0;color:#111827;font:9px "Yu Gothic","Meiryo",sans-serif}
    .actions{display:flex;justify-content:flex-end;margin-bottom:8px}
    button{border:1px solid #cbd5e1;background:#fff;border-radius:4px;padding:6px 10px;font:inherit;cursor:pointer}
    h1{margin:0 0 3px;font-size:16px}
    .meta{display:flex;justify-content:space-between;align-items:end;margin-bottom:8px;color:#475467}
    .warning{margin:18px 0;padding:18px 20px;border:2px solid #f59e0b;background:#fffbeb;color:#92400e;font-size:15px;font-weight:700;line-height:1.8}
    table{width:100%;border-collapse:collapse;table-layout:fixed}
    th,td{border:1px solid #d0d7de;padding:3px 4px;vertical-align:middle;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    th{background:#eef2f7;font-weight:700;text-align:center}
    td.num{text-align:right;font-variant-numeric:tabular-nums}
    .customer{width:118px}
    .small{width:36px}
    .medium{width:54px}
    .money{width:64px}
    .negative{color:#b42318}
    .group-header td{background:#e0f2fe;font-weight:700;color:#0f172a}
    .group-total td{background:#fef3c7;font-weight:700}
    .grand-total td{background:#e2e8f0;font-weight:700}
    @media print{.actions{display:none} body{font-size:8px} th,td{padding:2px 3px} .warning{font-size:13px}}
  </style>
</head>
<body>
  <div class="actions"><button onclick="window.print()">印刷 / PDF保存</button></div>
  <h1>取引先別月計／売掛残高一覧</h1>
  <div class="meta">
    <div>対象月：{{ $monthLabel }}</div>
    <div>出力日時：{{ now()->format('Y/m/d H:i') }}　件数：{{ $rows->count() }}</div>
  </div>

  @if(!empty($warning))
    <div class="warning">{{ $warning }}</div>
  @else
    <table>
      <thead>
        <tr>
          <th class="small">順序</th>
          <th class="medium">決算売掛区分</th>
          <th class="small">取引先ID</th>
          <th class="medium">都道府県１</th>
          <th class="medium">業種区分</th>
          <th class="customer">取引先名</th>
          <th class="money">請求額</th>
          <th class="money">酒出荷</th>
          <th class="money">酒戻入</th>
          <th class="money">酒粕出荷</th>
          <th class="money">その他出荷</th>
          <th class="money">値引</th>
          <th class="money">消費税</th>
          <th class="money">入金</th>
          <th class="money">振込料</th>
          <th class="money">前月請求</th>
          <th class="money">請求残</th>
        </tr>
      </thead>
      <tbody>
        @forelse($groupedRows as $categoryName => $categoryRows)
          @php
            $categoryTotal = $categoryTotalsByName->get($categoryName);
          @endphp
          <tr class="group-header">
            <td colspan="17">決算売掛区分：{{ $categoryName }}　{{ $categoryRows->count() }}件</td>
          </tr>
          @foreach($categoryRows as $row)
            <tr>
              <td class="num">{{ $row->sortOrder }}</td>
              <td>{{ $row->settlementReceivableCategory }}</td>
              <td class="num">{{ $row->legacyCustomerId }}</td>
              <td>{{ $row->prefecture }}</td>
              <td>{{ $row->businessType }}</td>
              <td>{{ $row->customerName }}</td>
              @foreach($amountProps as $prop)
                <td class="num {{ (float) $row->{$prop} < 0 ? 'negative' : '' }}">{{ $fmt($row->{$prop}) }}</td>
              @endforeach
            </tr>
          @endforeach
          <tr class="group-total">
            <td colspan="6">{{ $categoryName }} 小計</td>
            @foreach($totalKeys as $key)
              <td class="num {{ (float) ($categoryTotal['totals'][$key] ?? 0) < 0 ? 'negative' : '' }}">{{ $fmtTotal($categoryTotal['totals'][$key] ?? 0) }}</td>
            @endforeach
          </tr>
        @empty
          <tr><td colspan="17" style="text-align:center;padding:24px;color:#64748b">該当データはありません。</td></tr>
        @endforelse
      </tbody>
      <tbody>
        <tr class="grand-total">
          <td colspan="6">総合計</td>
          @foreach($totalKeys as $key)
            <td class="num {{ (float) ($totals[$key] ?? 0) < 0 ? 'negative' : '' }}">{{ $fmtTotal($totals[$key] ?? 0) }}</td>
          @endforeach
        </tr>
      </tbody>
    </table>
  @endif
</body>
</html>
