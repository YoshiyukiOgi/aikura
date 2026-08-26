<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>入金控え</title>
  <style>
    body{font-family:"Noto Sans JP",Meiryo,sans-serif;padding:24px}.table{width:100%;border-collapse:collapse;margin-top:20px}.table th,.table td{border-bottom:1px solid #ddd;padding:8px;text-align:left}.msg{padding:10px 12px;border-radius:6px;background:#e9f8ef;color:#137333;margin-bottom:12px}.err{background:#fff1f0;color:#b42318}.panel{border:1px solid #ddd;border-radius:8px;padding:14px;margin-top:18px}.row{display:grid;grid-template-columns:160px 160px 1fr auto;gap:8px;align-items:end}input,select,textarea,button{font:inherit;padding:8px;border:1px solid #ccc;border-radius:6px}textarea{min-height:38px}button{background:#b42318;color:#fff;border-color:#b42318}
  </style>
</head>
<body>
  @if(session('status'))<div class="msg">{{ session('status') }}</div>@endif
  @if($errors->any())<div class="msg err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
  <h1>{{ $payment->adjustment_type === 'refund' ? '返金控え' : '入金控え' }}</h1>
  <p>{{ $payment->payment_no }} / {{ $payment->payment_date->format('Y-m-d') }} / {{ $payment->customer?->name }} / {{ $payment->status }}</p>
  <p>金額 ¥{{ number_format((float)$payment->amount) }} / 未充当 ¥{{ number_format((float)$payment->unapplied_amount) }}</p>
  @if($payment->originalPayment)<p>元入金: {{ $payment->originalPayment->payment_no }}</p>@endif
  <table class="table">
    <thead><tr><th>請求書</th><th>充当額</th></tr></thead>
    <tbody>
      @foreach($payment->allocations as $allocation)
        <tr><td>{{ $allocation->invoice?->invoice_no }}</td><td>¥{{ number_format((float)$allocation->allocated_amount) }}</td></tr>
      @endforeach
    </tbody>
  </table>
  @if($payment->adjustment_type !== 'refund' && (float)$payment->amount > 0)
    <section class="panel">
      <h2>返金登録</h2>
      <p>入金に対する返金を記録します。請求残高は自動では戻しません。赤伝・相殺または再請求と組み合わせて処理してください。</p>
      <form method="post" action="{{ route('retail.payments.refund', $payment) }}">
        @csrf
        <div class="row">
          <input type="date" name="payment_date" value="{{ now()->toDateString() }}" required>
          <select name="payment_method">
            <option value="cash">現金</option>
            <option value="bank_transfer">振込</option>
            <option value="card">カード</option>
            <option value="qr">QR</option>
            <option value="other">その他</option>
          </select>
          <input type="number" name="amount" min="1" step="1" max="{{ max(0, (float)$payment->amount + (float)$payment->adjustmentPayments->where('adjustment_type', 'refund')->sum('amount')) }}" placeholder="返金額" required>
          <button type="submit">返金登録</button>
        </div>
        <p><textarea name="reason" required placeholder="返金理由"></textarea></p>
      </form>
    </section>
  @endif
  <p><a href="{{ route('retail.payments.index') }}">一覧へ</a></p>
</body>
</html>
