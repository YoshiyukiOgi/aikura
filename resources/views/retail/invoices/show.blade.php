<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>請求書</title>
  <style>
    body{font-family:"Noto Sans JP",Meiryo,sans-serif;padding:24px}.head{display:flex;justify-content:space-between}.table{width:100%;border-collapse:collapse;margin-top:20px}.table th,.table td{border-bottom:1px solid #ddd;padding:8px;text-align:left}.total{text-align:right;font-size:22px}.msg{padding:10px 12px;border-radius:6px;background:#e9f8ef;color:#137333;margin-bottom:12px}.err{background:#fff1f0;color:#b42318}.panel{border:1px solid #ddd;border-radius:8px;padding:14px;margin-top:18px}textarea,button{font:inherit;padding:8px;border:1px solid #ccc;border-radius:6px}textarea{width:100%;min-height:70px}button{background:#b42318;color:#fff;border-color:#b42318}
  </style>
</head>
<body>
  @if(session('status'))<div class="msg">{{ session('status') }}</div>@endif
  @if($errors->any())<div class="msg err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
  <div class="head">
    <div>
      <h1>請求書</h1>
      <p>{{ $invoice->invoice_no }} / {{ $invoice->status }}</p>
      <p>{{ $invoice->customer?->name }} 御中</p>
    </div>
    <div>
      <p>請求日: {{ $invoice->invoice_date->format('Y-m-d') }}</p>
      <p>締日: {{ $invoice->closing_date->format('Y-m-d') }}</p>
      <p>期日: {{ $invoice->due_date?->format('Y-m-d') ?: '-' }}</p>
    </div>
  </div>
  <table class="table">
    <thead><tr><th>品名</th><th>数量</th><th>単価（税抜）</th><th>消費税</th><th>金額（税抜）</th></tr></thead>
    <tbody>
      @foreach($invoice->lines as $line)
        <tr><td>{{ $line->description }}</td><td>{{ $line->quantity }}</td><td>¥{{ number_format((float)$line->unit_price) }}</td><td>¥{{ number_format((float)$line->tax_amount) }}</td><td>¥{{ number_format((float)$line->line_amount) }}</td></tr>
      @endforeach
    </tbody>
  </table>
  <div style="max-width:360px;margin-left:auto;margin-top:16px">
    <p style="display:flex;justify-content:space-between"><span>小計（税抜）</span><strong>¥{{ number_format((float)$invoice->subtotal_amount) }}</strong></p>
    <p style="display:flex;justify-content:space-between"><span>消費税</span><strong>¥{{ number_format((float)$invoice->tax_amount) }}</strong></p>
    <p class="total">合計（税込） ¥{{ number_format((float)$invoice->total_amount) }}</p>
    <p style="text-align:right">残高 ¥{{ number_format((float)$invoice->balance_amount) }}</p>
  </div>
  @if($invoice->status !== 'cancelled' && (float)$invoice->paid_amount <= 0)
    <section class="panel">
      <h2>請求書取消</h2>
      <p>入金前の請求書だけ取消できます。取消後、対象販売は再締めできます。</p>
      <form method="post" action="{{ route('retail.invoices.cancel', $invoice) }}">
        @csrf
        <textarea name="reason" required placeholder="取消理由"></textarea>
        <p><button type="submit">請求書を取消</button></p>
      </form>
    </section>
  @elseif($invoice->status !== 'cancelled')
    <section class="panel">
      <h2>請求書取消</h2>
      <p>入金済みの請求書は取消できません。赤伝で相殺、または入金返金で処理してください。</p>
    </section>
  @endif
  <p><a href="{{ route('retail.invoices.index') }}">一覧へ</a></p>
</body>
</html>
