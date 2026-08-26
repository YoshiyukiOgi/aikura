<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>伝票詳細 | {{ $retailSystemName ?? '小売販売システム' }}</title>
  <style>
    :root{--bg:{{ $retailTheme['bg'] ?? '#f3f6fa' }};--panel:{{ $retailTheme['card'] ?? '#fff' }};--line:{{ $retailTheme['line'] ?? '#d9e2ee' }};--text:#172033;--muted:#65758c;--blue:{{ $retailTheme['primary'] ?? '#0b6ff6' }};--blue-dark:{{ $retailTheme['primaryDark'] ?? '#075ecf' }}}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:"Noto Sans JP",Meiryo,system-ui,sans-serif}.content{max-width:980px;margin:0 auto;padding:18px}.panel{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:16px;margin-bottom:12px}.top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}.top h1{margin:0;font-size:20px}.meta{color:var(--muted);font-size:12px;line-height:1.65}.btn{border:1px solid #c7d3e2;border-radius:6px;background:#fff;color:#1d3450;padding:8px 12px;font:inherit;font-size:12px;cursor:pointer;text-decoration:none;display:inline-flex}.btn.primary{border-color:var(--blue);background:var(--blue);color:#fff;font-weight:800}.btn.danger{border-color:#f1b7b1;color:#b42318}.table{width:100%;border-collapse:collapse;margin-top:12px}.table th,.table td{padding:9px 8px;border-bottom:1px solid #edf1f6;text-align:left;font-size:12px}.table th{background:#f8faff;color:#64748b;font-size:11px}.totals{max-width:320px;margin-left:auto;margin-top:14px;display:grid;gap:8px}.row{display:flex;justify-content:space-between}.row strong{font-size:22px;color:var(--blue-dark)}.message{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#e9f8ef;color:#137333;font-size:12px}.error{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#fff1f0;color:#b42318;font-size:12px}label{display:grid;gap:6px;color:#4d6078;font-size:11px;font-weight:800}input,select,textarea{width:100%;min-height:36px;border:1px solid #cbd7e6;border-radius:6px;background:#fff;color:var(--text);font:inherit;padding:8px}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.full{grid-column:1/-1}.actions{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}.actions form{margin:0}.badge{display:inline-flex;align-items:center;min-height:24px;border-radius:999px;background:#eef1f5;color:#475569;font-size:11px;font-weight:800;padding:0 9px}.cancel-dialog{width:min(520px,calc(100% - 32px));padding:0;border:0;border-radius:10px;box-shadow:0 18px 55px rgba(15,23,42,.28)}.cancel-dialog::backdrop{background:rgba(15,23,42,.48)}.cancel-dialog form{padding:20px}.cancel-dialog h2{margin:0 0 8px;font-size:18px}.cancel-dialog p{margin:0 0 14px;line-height:1.7;color:#475569}.cancel-dialog textarea{min-height:100px;resize:vertical}.dialog-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:16px}@media(max-width:720px){.form-grid{grid-template-columns:1fr}.full{grid-column:auto}.table{display:block;overflow:auto}}
  </style>
</head>
<body>
  <main class="content">
    @php
      $issuedDelivery = $sale->deliveries->firstWhere('status', 'issued');
      $deliveryCancelLabel = $issuedDelivery?->billing_method_snapshot === 'per_sale'
        ? '納品書・請求書を取消・無効化'
        : '納品書を取消・無効化';
    @endphp
    @if (session('status'))<div class="message">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <section class="panel document-panel">
      <div class="top">
        <div>
          <h1>伝票詳細</h1>
          <div class="meta">{{ $sale->sale_no }} / {{ $sale->sale_date->format('Y-m-d') }} / {{ ['cash' => '現金', 'credit' => '掛売', 'card' => 'カード', 'qr' => 'QR'][$sale->sale_type] ?? $sale->sale_type }}</div>
          <div class="meta">顧客: {{ $sale->customer?->name ?: '店頭一般客' }}</div>
          <div class="meta">状態: <span class="badge">{{ $sale->correction_type === 'credit_note' ? '赤伝' : (['posted' => '登録済', 'revised' => '変更済', 'cancelled' => '取消済'][$sale->status] ?? $sale->status) }}</span></div>
          <div class="meta">
            蔵連携: {{ [
              'not_required' => '対象外',
              'ordered' => '発注済',
              'order_revised' => '蔵受注変更済',
              'order_cancelled' => '蔵受注取消済',
              'correction_sent' => '訂正依頼済',
              'revision_no_change' => '蔵商品差分なし',
              'failed' => '連携失敗',
            ][$sale->brewery_sync_status] ?? $sale->brewery_sync_status }}
            @if ($sale->brewery_order_number) / 蔵受注番号: {{ $sale->brewery_order_number }} @endif
            @if ($sale->brewery_correction_order_number) / 訂正受注番号: {{ $sale->brewery_correction_order_number }} @endif
          </div>
          @if ($sale->brewery_sync_error)<div class="error" style="margin-top:8px">蔵連携エラー: {{ $sale->brewery_sync_error }}</div>@endif
          @if ($sale->originalSale)<div class="meta">元伝票: {{ $sale->originalSale->sale_no }}</div>@endif
        </div>
        <div class="actions">
          <a class="btn" href="{{ route('retail.sales.index') }}">販売履歴へ</a>
          @if ($sale->status !== 'cancelled' && $sale->correction_type !== 'credit_note')
            @if ($issuedDelivery)
              <button class="btn danger" type="button" onclick="document.getElementById('delivery-cancel-dialog').showModal()">{{ $deliveryCancelLabel }}</button>
              <a class="btn primary" href="{{ route('retail.deliveries.show', $issuedDelivery) }}" target="_blank" rel="noopener">納品・請求書印刷</a>
            @else
              <form method="post" action="{{ route('retail.deliveries.store') }}" target="_blank">
                @csrf
                <input type="hidden" name="retail_sale_id" value="{{ $sale->id }}">
                <input type="hidden" name="delivery_date" value="{{ now()->toDateString() }}">
                <button class="btn primary" type="submit">納品・請求書印刷</button>
              </form>
            @endif
          @endif
        </div>
      </div>
      <table class="table">
        <thead><tr><th>商品</th><th>数量</th><th>単価（税抜）</th><th>消費税</th><th>金額（税抜）</th></tr></thead>
        <tbody>
          @foreach ($sale->items as $item)
            <tr>
              <td>{{ $item->description }}</td>
              <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}</td>
              <td>¥{{ number_format((float) $item->unit_price) }}</td>
              <td>¥{{ number_format((float) $item->tax_amount) }}</td>
              <td>¥{{ number_format((float) $item->line_amount) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <div class="totals">
        <div class="row"><span>小計（税抜）</span><b>¥{{ number_format((float) $sale->subtotal_amount) }}</b></div>
        <div class="row"><span>消費税</span><b>¥{{ number_format((float) $sale->tax_amount) }}</b></div>
        <div class="row"><span>合計（税込）</span><strong>¥{{ number_format((float) $sale->total_amount) }}</strong></div>
      </div>
    </section>

    @if ($issuedDelivery)
      <dialog class="cancel-dialog" id="delivery-cancel-dialog" @if($errors->has('reason')) open @endif>
        <form method="post" action="{{ route('retail.deliveries.cancel', $issuedDelivery) }}">
          @csrf
          <h2>{{ $deliveryCancelLabel }}</h2>
          <p>対象：{{ $issuedDelivery->delivery_no }}<br>取消後、この納品・請求書は印刷できなくなり、販売伝票の変更または取消が可能になります。</p>
          <label>
            取消理由（必須）
            <textarea name="reason" required maxlength="1000" autofocus>{{ old('reason') }}</textarea>
          </label>
          <div class="dialog-actions">
            <button class="btn" type="button" onclick="document.getElementById('delivery-cancel-dialog').close()">戻る</button>
            <button class="btn danger" type="submit">取消・無効化を実行</button>
          </div>
        </form>
      </dialog>
    @endif

    @php
      $isClosed = $sale->invoiceLines->isNotEmpty();
      $hasDelivery = $sale->deliveries->where('status', 'issued')->isNotEmpty();
      $canEdit = $sale->status !== 'cancelled' && ! $isClosed && ! $hasDelivery && $sale->correction_type !== 'credit_note';
      $canCreditNote = $sale->status !== 'cancelled' && $isClosed && $sale->correction_type !== 'credit_note' && $sale->correctionSales->where('correction_type', 'credit_note')->isEmpty();
    @endphp
    <section class="panel correction-panel">
      <h2 style="margin:0 0 8px;font-size:16px">伝票訂正</h2>
      @if ($canEdit)
        <form method="post" action="{{ route('retail.sales.revise', $sale) }}">
          @csrf
          @method('put')
          <div class="form-grid">
            <label>販売日<input type="date" name="sale_date" value="{{ old('sale_date', $sale->sale_date->format('Y-m-d')) }}" required></label>
            <label>販売区分
              <select name="sale_type">
                @foreach (['cash' => '現金', 'credit' => '掛売', 'card' => 'カード', 'qr' => 'QR'] as $key => $label)
                  <option value="{{ $key }}" @selected(old('sale_type', $sale->sale_type) === $key)>{{ $label }}</option>
                @endforeach
              </select>
            </label>
            <input type="hidden" name="retail_customer_id" value="{{ $sale->retail_customer_id }}">
            <label class="full">変更理由<textarea name="reason" required>{{ old('reason') }}</textarea></label>
          </div>
          <table class="table">
            <thead><tr><th>商品</th><th>数量</th></tr></thead>
            <tbody>
              @foreach ($sale->items as $index => $item)
                <tr>
                  <td>
                    <select name="items[{{ $index }}][retail_product_id]">
                      @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((int) old("items.{$index}.retail_product_id", $item->retail_product_id) === $product->id)>{{ $product->product_code }} / {{ $product->name }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" name="items[{{ $index }}][quantity]" min="1" step="1" inputmode="numeric" value="{{ old("items.{$index}.quantity", number_format((float) $item->quantity, 0, '.', '')) }}"></td>
                </tr>
              @endforeach
            </tbody>
          </table>
          <div class="actions" style="margin-top:10px">
            <button class="btn primary" type="submit">締め前変更</button>
          </div>
        </form>
        <form method="post" action="{{ route('retail.sales.cancel', $sale) }}" style="margin-top:12px">
          @csrf
          <label>取消理由<textarea name="reason" required></textarea></label>
          <div class="actions" style="margin-top:8px"><button class="btn danger" type="submit">締め前取消</button></div>
        </form>
      @elseif ($canCreditNote)
        <form method="post" action="{{ route('retail.sales.credit-note', $sale) }}">
          @csrf
          <label>赤伝理由<textarea name="reason" required></textarea></label>
          <div class="meta">この販売は締め後のため、元伝票は変更せず赤伝を作成します。</div>
          <div class="actions" style="margin-top:8px"><button class="btn danger" type="submit">赤伝を作成</button></div>
        </form>
      @else
        <div class="meta">
          @if ($sale->status === 'cancelled')
            取消済みのため訂正操作はできません。
          @elseif ($sale->correction_type === 'credit_note')
            赤伝のため訂正操作はできません。
          @elseif ($hasDelivery)
            納品書作成済みのため、この画面では変更・取消できません。
          @elseif ($sale->correctionSales->where('correction_type', 'credit_note')->isNotEmpty())
            赤伝作成済みです。
          @else
            訂正操作はありません。
          @endif
        </div>
      @endif
    </section>
  </main>
</body>
</html>

