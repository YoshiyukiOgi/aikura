<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  @php
    $documentTitle = $delivery->billing_method_snapshot === 'per_sale' ? '商品納品書・請求書' : '商品納品書';
  @endphp
  <title>{{ $documentTitle }}</title>
  <style>
    body{font-family:"Noto Sans JP",Meiryo,sans-serif;padding:24px;color:#172033;font-size:12px}.head{display:flex;align-items:center;justify-content:space-between;gap:28px}.head h1{margin:0 0 8px;font-size:21px}.document-meta p{margin:2px 0}.document-meta .recipient{display:inline-block;margin-top:5px;padding-bottom:2px;border-bottom:1px solid #172033;font-size:16px;font-weight:700}.purchase-total{min-width:300px;padding:10px 12px;border:.75px dashed #64748b;text-align:left;font-size:10px}.purchase-total h2{margin:0 0 5px;font-size:11px;font-weight:700}.purchase-total strong{display:block;font-size:13px}.table{width:100%;table-layout:fixed;border-collapse:collapse;margin-top:20px;font-size:11px}.table th,.table td{border-bottom:1px solid #ddd;padding:6px 8px;text-align:left}.table th:nth-child(n+2),.table td:nth-child(n+2){text-align:right}.tax-subtotal{display:flex;justify-content:flex-end;gap:16px;margin:7px 0 0;font-size:11px}.actions{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}.btn{border:1px solid #c7d3e2;border-radius:6px;background:#fff;color:#1d3450;padding:8px 12px;font:inherit;text-decoration:none;cursor:pointer}.btn.primary{border-color:#0b6ff6;background:#0b6ff6;color:#fff;font-weight:700}.message,.error,.cancelled-mark{margin-bottom:14px;padding:10px 12px;border-radius:5px}.message{background:#e9f8ef;color:#137333}.error{background:#fff1f0;color:#b42318}.cancelled-mark{border:2px solid #b42318;color:#b42318;text-align:center;font-size:18px;font-weight:800}.cancelled-mark span{display:block;margin-top:4px;font-size:10px;font-weight:400}@media print{.actions,.message,.error{display:none}body{padding:0}}
  </style>
</head>
<body>
  <div class="actions">
    @if ($delivery->status === 'issued')
      <button class="btn primary" type="button" onclick="window.print()">印刷</button>
    @endif
    <a class="btn" href="{{ route('retail.sales.show', $delivery->sale) }}">伝票詳細へ戻る</a>
    <a class="btn" href="{{ route('retail.pos') }}">販売入力へ</a>
  </div>
  @if (session('status'))<div class="message">{{ session('status') }}</div>@endif
  @if ($errors->any())<div class="error">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
  @if ($delivery->status === 'cancelled')
    <div class="cancelled-mark">
      取消済み・無効
      <span>{{ $delivery->cancelled_at?->format('Y-m-d H:i') }} / 理由: {{ $delivery->cancellation_reason }}</span>
    </div>
  @endif
  @php
    $taxGroups = $delivery->lines
      ->groupBy(fn ($line) => number_format((float) ($line->tax_rate ?? $line->saleItem?->tax_rate ?? 0), 4, '.', ''))
      ->sortKeys();
    $subtotal = (float) $delivery->lines->sum('line_amount');
    $tax = (float) $delivery->lines->sum('tax_amount');
    $total = $subtotal + $tax;
  @endphp
  <div class="head">
    <div class="document-meta">
      <h1>{{ $documentTitle }}</h1>
      <p>納品書番号：{{ $delivery->delivery_no }}</p>
      <p>納品日：{{ $delivery->delivery_date->format('Y-m-d') }}</p>
      <p class="recipient">{{ $delivery->retail_customer_id ? (($delivery->delivery_name ?: $delivery->customer?->name).' 御中') : 'お客様各位' }}</p>
    </div>
    <div class="purchase-total">
      <h2>お買い上げ金額</h2>
      <strong>税込合計 ¥{{ number_format($total) }}（内消費税 ¥{{ number_format($tax) }}）</strong>
    </div>
  </div>
  @foreach($taxGroups as $taxRate => $lines)
    @php
      $ratePercent = (float) $taxRate * 100;
      $rateTax = (float) $lines->sum('tax_amount');
      $rateTotal = (float) $lines->sum('line_amount') + $rateTax;
    @endphp
    <h2 style="font-size:13px;margin:18px 0 6px">消費税率 {{ rtrim(rtrim(number_format($ratePercent, 2, '.', ''), '0'), '.') }}% 対象</h2>
    <table class="table" style="margin-top:0">
      <colgroup><col style="width:40%"><col style="width:12%"><col style="width:16%"><col style="width:16%"><col style="width:16%"></colgroup>
      <thead><tr><th>品名</th><th>数量</th><th>単価（税抜）</th><th>消費税</th><th>金額（税抜）</th></tr></thead>
      <tbody>
        @foreach($lines as $line)
          <tr>
            <td>{{ $line->description }}</td>
            <td>{{ rtrim(rtrim(number_format((float) $line->quantity, 3, '.', ''), '0'), '.') }}</td>
            <td>¥{{ number_format((float)$line->unit_price) }}</td>
            <td>¥{{ number_format((float)$line->tax_amount) }}</td>
            <td>¥{{ number_format((float)$line->line_amount) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
    <p class="tax-subtotal"><span>{{ $ratePercent }}% 税込小計</span><strong>¥{{ number_format($rateTotal) }}（内消費税 ¥{{ number_format($rateTax) }}）</strong></p>
  @endforeach
</body>
</html>
