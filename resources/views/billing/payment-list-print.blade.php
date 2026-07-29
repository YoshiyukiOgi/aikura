@php
  $formatMoney = fn ($value): string => '¥'.number_format((float) $value);
  $statusLabels = [
      'allocated' => '消込済み',
      'review_required' => '要確認',
      'cancelled' => '取消済み',
  ];
  $totalAmount = $payments->sum(fn ($payment) => (float) $payment->amount);
  $totalUnapplied = $payments->sum(fn ($payment) => (float) $payment->unapplied_amount);
  $filterLabels = array_values(array_filter([
      ($filters['payment_date_from'] ?? null) || ($filters['payment_date_to'] ?? null)
          ? '入金日: '.($filters['payment_date_from'] ?? '指定なし').' ～ '.($filters['payment_date_to'] ?? '指定なし')
          : null,
      ($filters['customer'] ?? null) ? '取引先: '.$filters['customer'] : null,
      ($filters['status'] ?? null) ? '状態: '.($statusLabels[$filters['status']] ?? $filters['status']) : null,
      (bool) ($filters['has_unapplied'] ?? false) ? '未消込ありのみ' : null,
  ]));
@endphp
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>入金一覧</title>
  <style>
    @page{size:A4 portrait;margin:12mm}
    *{box-sizing:border-box}
    body{margin:20px;color:#172033;font:9px "Noto Sans JP",Meiryo,sans-serif}
    .no-print{margin-bottom:10px;border:1px solid #94a3b8;border-radius:4px;background:#fff;padding:6px 10px;cursor:pointer;font:inherit}
    .title-row{display:grid;grid-template-columns:1fr auto 1fr;align-items:end;margin-bottom:12px}
    h1{grid-column:2;margin:0;font-size:18px;letter-spacing:.12em}
    .company{grid-column:3;text-align:right;font-size:10px;font-weight:800}
    .filters{margin-bottom:8px;color:#475569;line-height:1.6}
    .summary{display:flex;justify-content:flex-end;gap:18px;margin-bottom:8px;font-weight:800}
    table{width:100%;border-collapse:collapse;table-layout:fixed}
    th,td{border:1px solid #94a3b8;padding:4px 5px;text-align:left;vertical-align:top}
    th{background:#f1f5f9;color:#334155;font-size:8px}
    tbody tr{break-inside:avoid;page-break-inside:avoid}
    .date{width:74px}
    .customer-col{width:28%}
    .status{width:68px}
    .money{width:82px}
    .reference{width:22%}
    .num{text-align:right;white-space:nowrap}
    .empty{text-align:center;color:#64748b;padding:18px}
    @media print{body{margin:0}.no-print{display:none}}
  </style>
</head>
<body>
  <button class="no-print" type="button" onclick="window.print()">印刷 / PDF出力</button>
  <div class="title-row">
    <h1>入金一覧</h1>
    <div class="company">{{ $companyInformation['name'] }}</div>
  </div>
  @if(count($filterLabels) > 0)
    <div class="filters">{{ implode('　／　', $filterLabels) }}</div>
  @endif
  <div class="summary">
    <span>{{ number_format($payments->count()) }}件</span>
    <span>入金額合計 {{ $formatMoney($totalAmount) }}</span>
    <span>未充当額合計 {{ $formatMoney($totalUnapplied) }}</span>
  </div>
  <table>
    <thead>
      <tr>
        <th class="date">入金日</th>
        <th class="customer-col">取引先</th>
        <th class="status">状態</th>
        <th class="money num">入金額</th>
        <th class="money num">未充当額</th>
        <th class="reference">参考番号</th>
      </tr>
    </thead>
    <tbody>
      @forelse($payments as $payment)
        <tr>
          <td>{{ $payment->payment_date?->format('Y/m/d') ?? '-' }}</td>
          <td>{{ $payment->customer?->name ?? '-' }}</td>
          <td>{{ $statusLabels[$payment->status] ?? $payment->status }}</td>
          <td class="num">{{ $formatMoney($payment->amount) }}</td>
          <td class="num">{{ $formatMoney($payment->unapplied_amount) }}</td>
          <td>{{ $payment->reference_number ?: '-' }}</td>
        </tr>
      @empty
        <tr><td class="empty" colspan="6">条件に該当する入金はありません。</td></tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
