<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>会社選択 | {{ $retailSystemName ?? '小売販売システム' }}</title>
  <style>
    :root{--bg:{{ $retailTheme['bg'] ?? '#f3f6fa' }};--panel:{{ $retailTheme['card'] ?? '#fff' }};--line:{{ $retailTheme['line'] ?? '#d9e2ee' }};--text:#172033;--muted:#65758c;--blue:{{ $retailTheme['primary'] ?? '#0b6ff6' }}}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--bg);color:var(--text);font-family:"Noto Sans JP",Meiryo,system-ui,sans-serif;display:grid;place-items:center;padding:24px}main{width:min(720px,100%);background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:24px;box-shadow:0 14px 38px rgba(24,38,60,.08)}.badge{display:inline-flex;align-items:center;min-height:26px;padding:0 10px;border-radius:999px;background:#e9f8ef;color:#137333;font-size:12px;font-weight:800}h1{margin:14px 0 6px;font-size:24px}.meta{color:var(--muted);font-size:13px;line-height:1.7}form{display:grid;gap:14px;margin-top:20px}label{display:grid;gap:8px;font-size:12px;font-weight:800;color:#4d6078}select{height:44px;border:1px solid #cbd7e6;border-radius:8px;background:#fff;color:var(--text);font:inherit;padding:0 12px}.actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}.btn{border:1px solid #c7d3e2;border-radius:8px;background:#fff;color:#1d3450;padding:10px 14px;font:inherit;font-size:13px;text-decoration:none;cursor:pointer}.btn.primary{border-color:var(--blue);background:var(--blue);color:#fff;font-weight:800}.empty{margin-top:16px;padding:12px;border-radius:8px;background:#fff8e6;color:#7a4b00;font-size:13px}
  </style>
</head>
<body>
  <main>
    <span class="badge">{{ $retailSystemName ?? '小売販売システム' }}</span>
    <h1>作業する会社を選択</h1>
    <p class="meta">会社を選ぶと、その会社の販売・設定メニューに入ります。会社の追加・削除は左メニューのシステム管理で行います。</p>

    @if ($companies === [])
      <div class="empty">有効な会社がありません。設定画面で会社を追加してください。</div>
    @else
      <form method="post" action="{{ route('retail.companies.select') }}">
        @csrf
        <label>会社
          <select name="company" required autofocus>
            @foreach ($companies as $key => $company)
              <option value="{{ $key }}" @selected($selectedCompanyKey === $key)>{{ $company['name'] }} / {{ $company['description'] }}</option>
            @endforeach
          </select>
        </label>
        <div class="actions">
          <button class="btn primary" type="submit">この会社で販売へ進む</button>
        </div>
      </form>
    @endif
  </main>
</body>
</html>

