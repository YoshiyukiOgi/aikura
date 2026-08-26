<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>システム管理 | {{ $retailSystemName ?? '小売販売システム' }}</title>
  <style>
    :root{--bg:{{ $retailTheme['bg'] ?? '#f3f6fa' }};--panel:{{ $retailTheme['card'] ?? '#fff' }};--line:{{ $retailTheme['line'] ?? '#d9e2ee' }};--text:#172033;--muted:#65758c;--blue:{{ $retailTheme['primary'] ?? '#0b6ff6' }};--red:#b42318;--sidebar:{{ $retailTheme['sidebar'] ?? '#10243b' }}}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--bg);color:var(--text);font-family:"Noto Sans JP",Meiryo,system-ui,sans-serif;padding-left:230px}.sidebar{position:fixed;inset:0 auto 0 0;width:230px;background:var(--sidebar);color:#eaf1fb;padding:18px 12px}.brand{padding:6px 10px 16px;border-bottom:1px solid rgba(255,255,255,.14)}.brand h1{margin:0;font-size:16px}.brand p{margin:8px 0 0;color:#a8b8cc;font-size:12px;line-height:1.6}.nav{display:grid;gap:4px;margin-top:16px}.nav a{display:flex;align-items:center;justify-content:space-between;min-height:38px;padding:0 12px;border-radius:6px;color:#dbe7f5;text-decoration:none;font-size:12px}.nav a.active{background:var(--blue);color:#fff;font-weight:800}.nav a span:last-child{color:#b8c6d9;font-size:11px}.content{padding:18px}.topbar{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px 16px;margin-bottom:12px}.topbar h2{margin:0;font-size:17px}.meta{color:var(--muted);font-size:12px;line-height:1.65}.card{border:1px solid var(--line);border-radius:8px;background:#f8fbff;padding:12px;margin-bottom:12px}.section-block{padding-top:12px;margin-top:12px;border-top:1px solid var(--line)}.section-block:first-child{padding-top:0;margin-top:0;border-top:0}.setting-form{display:grid;gap:12px;max-width:680px}.field-row{display:grid;grid-template-columns:minmax(220px,1fr) auto;gap:8px;align-items:end}.field-help{color:var(--muted);font-size:11px;line-height:1.65;margin-top:6px}label{display:grid;gap:6px;color:#4d6078;font-size:11px;font-weight:800}input,select{width:100%;min-height:36px;border:1px solid #cbd7e6;border-radius:6px;background:#fff;color:var(--text);font:inherit;padding:8px}.inline-form{display:flex;gap:8px;align-items:end;flex-wrap:wrap}.btn{border:1px solid #c7d3e2;border-radius:6px;background:#fff;color:#1d3450;padding:8px 12px;font:inherit;font-size:12px;cursor:pointer;text-decoration:none}.btn.primary{border-color:var(--blue);background:var(--blue);color:#fff;font-weight:800}.btn.danger{border-color:#f1b7b1;color:var(--red)}.message{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#e9f8ef;color:#137333;font-size:12px}.error{margin-bottom:12px;padding:10px 12px;border-radius:6px;background:#fff1f0;color:var(--red);font-size:12px}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:9px 8px;border-bottom:1px solid #edf1f6;text-align:left;font-size:12px;vertical-align:top}.table th{background:#f8faff;color:#64748b;font-size:11px}.badge{display:inline-flex;align-items:center;min-height:24px;border-radius:999px;background:#e9f8ef;color:#137333;font-size:11px;font-weight:800;padding:0 9px}.badge.off{background:#eef1f5;color:#64748b}@media(max-width:980px){body{padding-left:0}.sidebar{position:static;width:auto}.table{display:block;overflow:auto}.field-row{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <span hidden>Retail System Management Shell</span>
  <aside class="sidebar">
    <div class="brand"><h1>{{ $retailSystemName ?? '小売販売システム' }}</h1><p>システム全体の設定を管理します。</p></div>
    <nav class="nav">
      <a href="{{ route('retail.sales.index') }}"><span>販売履歴</span><span>履歴</span></a>
      <a href="{{ route('retail.pos') }}"><span>販売入力</span><span>POS</span></a>
      <a href="{{ route('retail.customers.index') }}"><span>小売顧客</span><span>顧客</span></a>
      <a href="{{ route('retail.products.index') }}"><span>外部商品</span><span>商品</span></a>
      <a href="{{ route('retail.products.import') }}"><span>取扱商品選択</span><span>選択</span></a>
      <a href="{{ route('retail.settings') }}"><span>設定</span><span>設定</span></a>
      <a class="active" href="{{ route('retail.system.manage') }}"><span>システム管理</span><span>管理</span></a>
    </nav>
  </aside>

  <main class="content">
    <header class="topbar">
      <h2>システム管理</h2>
      <div class="meta">システム名、蔵価格差分検出、会社の追加・停止を管理します。</div>
    </header>

    @if (session('status'))<div class="message">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <section class="card">
      <h3>システム設定</h3>
      <form class="setting-form" method="post" action="{{ route('retail.system.update') }}">
        @csrf
        @method('put')
        <label>システム名
          <input name="system_name" value="{{ old('system_name', $systemSetting->system_name) }}" required>
        </label>
        <div>
          <label>全体の色合い
            <select name="theme" required>
              @foreach ($retailThemeLabels as $key => $label)
                <option value="{{ $key }}" @selected(old('theme', $systemSetting->theme ?? 'blue') === $key)>{{ $label }}</option>
              @endforeach
            </select>
          </label>
          <div class="field-help">蔵販売業務システムと同じテーマ方式で、画面全体の配色を切り替えます。</div>
        </div>
        <div>
          <label>蔵商品情報の自動確認
            <select name="detection_mode">
              <option value="manual" @selected(old('detection_mode', $priceSyncSetting->detection_mode) === 'manual')>手動</option>
              <option value="daily" @selected(old('detection_mode', $priceSyncSetting->detection_mode) === 'daily')>毎日0時</option>
              <option value="interval" @selected(old('detection_mode', $priceSyncSetting->detection_mode) === 'interval')>指定分間隔</option>
            </select>
          </label>
          <label style="margin-top:10px">確認間隔（分）
            <input type="number" name="interval_minutes" min="15" max="1440" value="{{ old('interval_minutes', $priceSyncSetting->interval_minutes ?? 60) }}">
          </label>
          <div class="field-help">指定分間隔は15～1,440分です。最終確認: {{ $priceSyncSetting->last_detected_at?->format('Y-m-d H:i') ?? '未実行' }} / 次回予定: {{ $priceSyncSetting->nextDetectionAt()?->format('Y-m-d H:i') ?? '手動実行' }}</div>
          @if ($priceSyncSetting->last_detection_summary)
            <div class="field-help">最終結果: 新規 {{ $priceSyncSetting->last_detection_summary['new'] ?? 0 }}件 / 基本情報変更 {{ $priceSyncSetting->last_detection_summary['changed'] ?? 0 }}件 / 価格変更 {{ $priceSyncSetting->last_detection_summary['price'] ?? 0 }}件 / 販売停止 {{ $priceSyncSetting->last_detection_summary['inactive'] ?? 0 }}件 / 削除済み {{ $priceSyncSetting->last_detection_summary['deleted'] ?? 0 }}件</div>
          @endif
          @if ($priceSyncSetting->last_detection_error)<div class="field-help" style="color:#b42318">最終エラー: {{ $priceSyncSetting->last_detection_error }}</div>@endif
        </div>
        <div><button class="btn primary" type="submit">システム設定を保存</button></div>
      </form>
    </section>

    <section class="card">
      <div class="section-block">
        <h3>会社を追加</h3>
        <form class="inline-form" method="post" action="{{ route('retail.companies.store') }}">
          @csrf
          <label>会社キー<input name="company_key" placeholder="例: newco" required></label>
          <label>会社名<input name="name" placeholder="例: □社" required></label>
          <label>説明<input name="description" placeholder="例: 駅前店を運営"></label>
          <button class="btn primary" type="submit">会社を追加</button>
        </form>
      </div>

      <div class="section-block">
        <h3>会社一覧</h3>
      <table class="table">
        <thead><tr><th>会社キー</th><th>会社名</th><th>説明</th><th>状態</th><th>操作</th></tr></thead>
        <tbody>
          @foreach ($companyRecords as $company)
            <tr>
              <td>{{ $company->company_key }}</td>
              <td>{{ $company->name }}</td>
              <td>{{ $company->description }}</td>
              <td><span class="badge @class(['off' => ! $company->is_active])">{{ $company->is_active ? '有効' : '停止' }}</span></td>
              <td>
                @if ($company->is_active)
                  <form method="post" action="{{ route('retail.companies.destroy', $company) }}">
                    @csrf
                    @method('delete')
                    <button class="btn danger" type="submit">削除</button>
                  </form>
                @else
                  <span class="meta">削除済み</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
      </div>
    </section>
  </main>
</body>
</html>
