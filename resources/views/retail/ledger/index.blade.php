<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>小売元帳・月次締め</title>
    <style>
        body { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; color: #222; font-family: system-ui, sans-serif; }
        h1 { margin-bottom: .25rem; } section { border: 1px solid #ddd; border-radius: 6px; padding: 1rem; margin: 1.25rem 0; }
        label { display: inline-block; margin: .35rem .7rem .35rem 0; } input, select, button { padding: .35rem; }
        table { border-collapse: collapse; width: 100%; margin-top: .7rem; } th, td { border: 1px solid #ddd; padding: .45rem; text-align: left; vertical-align: top; }
        th { background: #f6f6f6; } .amount { text-align: right; white-space: nowrap; } .notice { background: #e7f5e9; padding: .8rem; }
        .payment { color: #075; } .closed { color: #b20; font-weight: 600; } .hint { color: #555; font-size: .9rem; }
    </style>
</head>
<body>
    <h1>小売元帳・月次請求残高</h1>
    <p class="hint">玉川Accessの入金は商品ID <code>-1</code> の明細として記録します。入金明細は在庫・酒蔵出荷・通常売上には連携しません。</p>

    @if (session('status')) <p class="notice">{{ session('status') }}</p> @endif
    @if ($errors->any()) <section><strong>入力を確認してください。</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section> @endif

    <form method="get" action="{{ route('retail.ledger.index') }}">
        <label>小売会社
            <select name="retail_company_id" onchange="this.form.submit()">
                @foreach ($companies as $item)<option value="{{ $item->id }}" @selected($company?->id === $item->id)>{{ $item->name }}</option>@endforeach
            </select>
        </label>
    </form>

    @if ($company)
        <section>
            <h2>元帳伝票を登録</h2>
            <form method="post" action="{{ route('retail.ledger.store') }}">
                @csrf
                <input type="hidden" name="retail_company_id" value="{{ $company->id }}">
                <label>伝票番号 <input name="document_no" required></label>
                <label>取引日 <input type="date" name="business_date" value="{{ now()->toDateString() }}" required></label>
                <label>取引先
                    <select name="retail_customer_id" required><option value="">選択してください</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select>
                </label>
                <table>
                    <thead><tr><th>区分</th><th>商品ID</th><th>内容</th><th>数量</th><th>単価</th><th>金額</th></tr></thead>
                    <tbody><tr>
                        <td><select name="lines[0][line_kind]"><option value="sale">売上</option><option value="payment">入金</option><option value="adjustment">調整</option><option value="opening">開始残高</option></select></td>
                        <td><input name="lines[0][source_product_id]" type="number" placeholder="入金は -1"></td>
                        <td><input name="lines[0][description]"></td>
                        <td><input name="lines[0][quantity]" type="number" step="0.001"></td>
                        <td><input name="lines[0][unit_price]" type="number" step="0.01"></td>
                        <td><input name="lines[0][amount]" type="number" step="0.01" required></td>
                    </tr></tbody>
                </table>
                <p class="hint">入金は商品IDを <code>-1</code>、金額をAccess原本の符号のまま入力します。複数明細を含む伝票はJSON取込を使います。</p>
                <button type="submit">元帳へ登録</button>
            </form>
        </section>

        <section>
            <h2>玉川Access検証データを取込</h2>
            <form method="post" enctype="multipart/form-data" action="{{ route('retail.ledger.import') }}">
                @csrf <input type="hidden" name="retail_company_id" value="{{ $company->id }}">
                <label>JSONファイル <input type="file" name="source_file" accept="application/json,.json" required></label>
                <button type="submit">取込</button>
            </form>
            <p class="hint">同じファイルはハッシュで判定し、二重取込しません。対象月をまたぐ場合は月ごとのJSONに分け、古い月から順に月次締めしてください。</p>
        </section>

        <section>
            <h2>月次締め</h2>
            <form method="post" action="{{ route('retail.ledger.close') }}">
                @csrf <input type="hidden" name="retail_company_id" value="{{ $company->id }}">
                <label>対象月 <input type="month" name="period" required></label>
                <label>備考 <input name="note"></label>
                <button type="submit">月次締めを確定</button>
            </form>
            <table><thead><tr><th>対象月</th><th>状態</th><th>伝票</th><th>明細</th><th>顧客残高</th><th>確定日時</th></tr></thead><tbody>
            @forelse ($closings as $closing)<tr><td><a href="{{ route('retail.ledger.statement', ['closing' => $closing]) }}">{{ $closing->period->format('Y-m') }}</a></td><td class="closed">{{ $closing->status }}</td><td>{{ $closing->document_count }}</td><td>{{ $closing->line_count }}</td><td>{{ $closing->balances_count }}</td><td>{{ $closing->closed_at }}</td></tr>@empty<tr><td colspan="6">月次締めはまだありません。</td></tr>@endforelse
            </tbody></table>
        </section>

        <section>
            <h2>元帳履歴</h2>
            <table><thead><tr><th>日付</th><th>伝票番号</th><th>取引先</th><th>取込元</th><th>明細</th></tr></thead><tbody>
            @forelse ($documents as $document)<tr><td>{{ $document->business_date->format('Y-m-d') }}</td><td>{{ $document->document_no }}</td><td>{{ $document->customer->name }}</td><td>{{ $document->source }}</td><td>
                @foreach ($document->lines as $line)<div @class(['payment' => $line->line_kind === 'payment'])>{{ $line->line_kind }} / 商品ID {{ $line->source_product_id ?? '—' }} / {{ $line->description }} <span class="amount">{{ number_format((float) $line->amount, 2) }}</span></div>@endforeach
            </td></tr>@empty<tr><td colspan="5">元帳伝票はまだありません。</td></tr>@endforelse
            </tbody></table>
            @if (method_exists($documents, 'links')) {{ $documents->links() }} @endif
        </section>
    @endif
</body>
</html>
