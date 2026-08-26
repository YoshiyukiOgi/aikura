<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>在庫移動履歴</title>
  <style>
    *{box-sizing:border-box}body{margin:0;background:#f5f7fb;color:#172033;font:12px system-ui,"Noto Sans JP",sans-serif}main{max-width:1200px;margin:0 auto;padding:24px}.actions{display:flex;justify-content:flex-end;gap:8px;margin-bottom:14px}button{border:1px solid #cad6e6;border-radius:4px;background:#fff;padding:8px 14px;font:inherit;cursor:pointer}.primary{border-color:#0b6ff6;background:#0b6ff6;color:#fff;font-weight:700}header{margin-bottom:14px}h1{margin:0 0 5px;font-size:20px}.condition{color:#526176}.sheet{background:#fff;border:1px solid #dce4ee;border-radius:6px;padding:18px}table{width:100%;border-collapse:collapse}th,td{padding:7px;border-bottom:1px solid #dfe5ed;text-align:left;vertical-align:top}th{background:#f5f7fa;color:#526176;font-size:11px}.num{text-align:right;font-variant-numeric:tabular-nums}.lot-name{font-weight:700}.lot-code{display:block;margin-top:2px;color:#64748b;font-size:10px}.empty{text-align:center;color:#64748b;padding:32px}
    @media print{body{background:#fff;font-size:9.5px}main{max-width:none;padding:0}.actions{display:none}.sheet{border:0;border-radius:0;padding:0}h1{font-size:16px}th,td{padding:4px 5px}@page{size:A4 landscape;margin:10mm}}
  </style>
</head>
<body>
@php
  $typeLabels = [
    'opening_stock' => '期首在庫', 'production_receipt' => '製造入庫', 'shipment' => '出荷',
    'shipment_cancellation' => '出荷取消', 'inventory_adjustment' => '在庫調整', 'transfer' => '在庫移動',
    'stock_correction' => '在庫移動訂正', 'sales_return' => '売上返品',
    'non_sales_inspection' => '検査・提出', 'non_sales_breakage' => '破損', 'non_sales_disposal' => '廃棄',
    'non_sales_loss' => '滅失', 'non_sales_return_to_manufacturing' => '戻入', 'non_sales_bottling' => '瓶詰',
    'non_sales_repackaging' => '詰替', 'non_sales_adjustment' => 'その他調整',
    'non_sales_revision_reversal' => '販売外変更打消', 'non_sales_cancellation' => '販売外取消打消',
  ];
@endphp
<main>
  <div class="actions"><button type="button" onclick="window.close()">閉じる</button><button type="button" class="primary" onclick="window.print()">印刷する</button></div>
  <section class="sheet">
    <header>
      <h1>在庫移動履歴</h1>
      <div class="condition">対象：{{ $year }}年{{ $month }}月 ／ 区分：{{ $movementType ? ($typeLabels[$movementType] ?? $movementType) : 'すべて' }} ／ 検索：{{ $search !== '' ? $search : 'なし' }} ／ {{ $movements->count() }}件</div>
    </header>
    <table>
      <thead><tr><th>日付</th><th>区分</th><th>ロット</th><th>場所</th><th class="num">数量</th><th>元伝票</th></tr></thead>
      <tbody>
      @forelse($movements as $movement)
        <tr>
          <td>{{ $movement->movement_date?->format('Y-m-d') }}</td>
          <td>{{ $typeLabels[$movement->movement_type] ?? $movement->movement_type }}</td>
          <td><span class="lot-name">{{ $movement->productionLot?->display_name ?: ($movement->lot_code ?: '-') }}</span>@if($movement->productionLot?->lot_code)<span class="lot-code">コード: {{ $movement->productionLot->lot_code }}</span>@endif</td>
          <td>{{ $movement->stockLocation?->name ?: '-' }}</td>
          <td class="num">{{ number_format((int) round((float) $movement->quantity)) }} {{ $movement->unit?->symbol ?: $movement->unit?->name }}</td>
          <td>{{ $movement->source_document_number ?: '-' }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="empty">該当する移動履歴はありません。</td></tr>
      @endforelse
      </tbody>
    </table>
  </section>
</main>
</body>
</html>
