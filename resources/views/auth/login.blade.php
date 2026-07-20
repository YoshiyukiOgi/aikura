<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ログイン | 酒蔵販売業務システム</title>
  <style>
    :root { font-family: Inter, "Noto Sans JP", system-ui, sans-serif; color: #172033; background: #f4f7fb; }
    body { min-height: 100vh; margin: 0; display: grid; place-items: center; }
    main { width: min(380px, calc(100vw - 40px)); border: 1px solid #dce4ee; border-radius: 10px; background: #fff; padding: 32px; box-shadow: 0 16px 42px rgba(22, 44, 75, .10); }
    h1 { margin: 0; font-size: 22px; } p { color: #64748b; font-size: 13px; }
    label { display: grid; gap: 6px; margin-top: 18px; font-size: 12px; font-weight: 700; }
    input { height: 38px; border: 1px solid #cfd9e7; border-radius: 5px; padding: 0 10px; font: inherit; }
    button { width: 100%; height: 42px; margin-top: 24px; border: 0; border-radius: 5px; background: #0b6ff6; color: #fff; font: inherit; font-weight: 800; }
    .error { margin: 14px 0 0; color: #c93a3a; font-size: 12px; }
  </style>
</head>
<body>
  <main>
    <h1>酒蔵販売業務システム</h1>
    <p>受注管理へログインします。</p>
    <form method="post" action="{{ route('login.store', absolute: false) }}">
      @csrf
      <label>メールアドレス<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label>
      <label>パスワード<input type="password" name="password" autocomplete="current-password" required></label>
      @error('email')<p class="error">{{ $message }}</p>@enderror
      <button type="submit">ログイン</button>
    </form>
  </main>
</body>
</html>
