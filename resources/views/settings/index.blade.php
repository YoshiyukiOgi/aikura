<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>設定 | 販売管理</title>
  <style>
    :root{font-family:Inter,"Noto Sans JP",system-ui,sans-serif;color:#172033;background:#f5f7fb;font-size:13px}
    *{box-sizing:border-box}
    body{margin:0}
    header{height:52px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;background:#fff;border-bottom:1px solid #dce4ee}
    header h1{font-size:15px;font-weight:800;padding-left:10px;letter-spacing:.02em}
    main{padding:12px 18px}
    h1,h2,p{margin:0}
    .card{max-width:760px;background:#fff;border:1px solid #dce4ee;border-radius:6px;overflow:hidden}
    .head{padding:11px 14px;border-bottom:1px solid #e7edf4}
    .form{display:grid;gap:13px;padding:16px}
    label{display:grid;gap:5px;color:#475569;font-size:11px;font-weight:800}
    input,select,button{font:inherit;border:1px solid #cad6e6;border-radius:4px;background:#fff;color:#172033;padding:8px 10px}
    button{cursor:pointer}
    .primary{background:#0b6ff6;color:#fff;border-color:#0b6ff6;font-weight:800}
    .actions{display:flex;justify-content:flex-end;gap:8px}
    .notice{padding:10px 12px;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534;border-radius:4px;font-size:12px}
    .muted{color:#64748b;font-size:11px;line-height:1.6}
  </style>
</head>
<body>
  <header>
    <h1>設定</h1>
    <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">ログアウト</button></form>
  </header>
  <main>
    <section class="card">
      <div class="head">
        <h2>表示設定</h2>
        <p class="muted">左メニューの会社名・システム名、全体の基本色を変更します。</p>
      </div>
      <form class="form" method="post" action="{{ route('settings.update') }}">
        @csrf
        @method('put')
        @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
        <label>会社名
          <input name="company_name" value="{{ old('company_name', $settings['company_name']) }}" required maxlength="80">
        </label>
        <label>システム名
          <input name="system_name" value="{{ old('system_name', $settings['system_name']) }}" required maxlength="80">
        </label>
        <label>全体の色合い
          <select name="theme" required>
            @foreach($themes as $key => $label)
              <option value="{{ $key }}" @selected(old('theme', $settings['theme']) === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label style="display:flex;align-items:center;gap:8px;font-weight:800">
          <input type="checkbox" name="auto_refresh_enabled" value="1" style="width:auto;min-height:auto" @checked(old('auto_refresh_enabled', $settings['auto_refresh_enabled']) === '1')>
          自動更新する
        </label>
        <label>更新間隔（秒）
          <input type="number" name="auto_refresh_interval_seconds" value="{{ old('auto_refresh_interval_seconds', $settings['auto_refresh_interval_seconds']) }}" min="5" max="3600" required>
        </label>
        <div class="head" style="margin:4px -16px 0"><h2>在庫ロット表示</h2></div>
        <p class="muted">在庫数が0の過去ロットは削除せず、通常の在庫表示と出荷候補だけを整理します。マイナス在庫は常に表示されます。</p>
        <label style="display:flex;align-items:center;gap:8px;font-weight:800">
          <input type="checkbox" name="hide_zero_stock_lots" value="1" style="width:auto;min-height:auto" @checked(old('hide_zero_stock_lots', $settings['hide_zero_stock_lots']) === '1')>
          在庫0のロットを通常は表示しない
        </label>
        <div class="head" style="margin:4px -16px 0"><h2>酒類ロット判定</h2></div>
        <p class="muted">酒類商品の標準アルコール度数に対する、全商品共通の許容差です。</p>
        <label>許容下限差
          <input type="number" name="alcohol_tolerance_lower" value="{{ old('alcohol_tolerance_lower', $settings['alcohol_tolerance_lower']) }}" min="0" max="10" step="0.01" required>
        </label>
        <label>許容上限差
          <input type="number" name="alcohol_tolerance_upper" value="{{ old('alcohol_tolerance_upper', $settings['alcohol_tolerance_upper']) }}" min="0" max="10" step="0.01" required>
        </label>
        <label style="display:flex;align-items:center;gap:8px;font-weight:800">
          <input type="checkbox" name="alcohol_out_of_range_approval_required" value="1" style="width:auto;min-height:auto" @checked(old('alcohol_out_of_range_approval_required', $settings['alcohol_out_of_range_approval_required']) === '1')>
          許容範囲外ロットは管理者承認を必須にする
        </label>
        @if($errors->any())
          <div class="muted">
            @foreach($errors->all() as $error)
              <div>{{ $error }}</div>
            @endforeach
          </div>
        @endif
        <div class="actions"><button class="primary" type="submit">設定を保存</button></div>
      </form>
    </section>
  </main>
</body>
</html>
