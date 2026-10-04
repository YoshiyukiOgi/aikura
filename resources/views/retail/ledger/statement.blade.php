<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>{{ $closing->period->format('Y年m月') }} 請求・残高一覧</title>
    <style>
        body { margin: 2rem auto; max-width: 1000px; color: #222; font-family: system-ui, sans-serif; }
        table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #999; padding: .45rem; } th { background: #eee; }
        .amount { text-align: right; } @media print { a { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <p><a href="{{ route('retail.ledger.index', ['retail_company_id' => $closing->retail_company_id]) }}">元帳へ戻る</a></p>
    <h1>{{ $closing->company->name }}　{{ $closing->period->format('Y年m月') }} 請求・残高一覧</h1>
    <p>月次締め日時: {{ $closing->closed_at }} ／ チェックサム: {{ $closing->checksum }}</p>
    <table>
        <thead><tr><th>取引先</th><th>前月残高</th><th>当月売上</th><th>当月入金</th><th>調整・開始残高</th><th>当月残高</th></tr></thead>
        <tbody>
            @foreach ($closing->balances->sortBy(fn ($balance) => $balance->customer->name) as $balance)
                <tr>
                    <td>{{ $balance->customer->name }}</td>
                    <td class="amount">{{ number_format((float) $balance->opening_amount, 2) }}</td>
                    <td class="amount">{{ number_format((float) $balance->sales_amount, 2) }}</td>
                    <td class="amount">{{ number_format((float) $balance->payment_amount, 2) }}</td>
                    <td class="amount">{{ number_format((float) $balance->adjustment_amount, 2) }}</td>
                    <td class="amount">{{ number_format((float) $balance->closing_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
