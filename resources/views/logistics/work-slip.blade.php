<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>出荷作業伝票 {{ $instruction->lines->first()?->salesOrder?->order_number }}</title>
    <style>
        body{margin:32px;color:#172033;font:14px "Noto Sans JP",sans-serif}h1{font-size:22px;margin:0 0 18px}.meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 24px;margin-bottom:16px}.meta div{border-bottom:1px solid #cbd5e1;padding:5px 0}.label{display:inline-block;min-width:96px;color:#64748b;font-size:12px}.work-note{border:1px solid #cbd5e1;min-height:56px;margin:0 0 18px;padding:8px;white-space:pre-wrap}.work-note strong{display:block;margin-bottom:4px}table{border-collapse:collapse;width:100%;table-layout:fixed}th,td{border:1px solid #94a3b8;padding:9px;text-align:left}th{background:#f1f5f9;font-size:12px}.check{width:44px;text-align:center}.code{width:130px}.name{width:32%}.quantity{text-align:right;width:90px}.lot{width:260px}@media print{body{margin:14mm}.no-print{display:none}}
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">印刷する</button>
    <h1>出荷作業伝票</h1>
    <div class="meta">
        <div><span class="label">受注番号</span>{{ $instruction->lines->first()?->salesOrder?->order_number ?? '-' }}</div>
        <div><span class="label">取引先</span>{{ $instruction->customer?->name ?? '-' }}</div>
        <div><span class="label">出荷予定日</span>{{ $instruction->scheduled_shipment_date?->format('Y/m/d') ?? '未設定' }}</div>
        <div><span class="label">出荷拠点</span>{{ $instruction->stockLocation?->name ?? '未設定' }}</div>
    </div>
    <div class="work-note"><strong>作業連絡欄</strong>{{ $instruction->lines->first()?->salesOrder?->work_note ?: ' ' }}</div>
    <table>
        <thead><tr><th class="check">確認</th><th class="code">商品コード</th><th class="name">商品名</th><th class="lot">ロット</th><th class="quantity">数量</th></tr></thead>
        <tbody>
        @foreach($instruction->lines as $line)
            <tr><td class="check">□</td><td class="code">{{ $line->product?->product_code }}</td><td class="name">{{ $line->product?->name }}</td><td class="lot">{{ $lotNamesByLine[$line->id] ?? '未指定' }}</td><td class="quantity">{{ rtrim(rtrim(number_format((float) $line->quantity, 4, '.', ''), '0'), '.') }}</td></tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
