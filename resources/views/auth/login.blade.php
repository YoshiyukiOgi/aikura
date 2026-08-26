@php
  $intended = (string) session('url.intended', '');
  $isRetailLogin = ($loginMode ?? null) === 'retail' || str_contains($intended, '/retail');
  $systemName = $isRetailLogin ? '小売販売システム' : '酒蔵販売業務システム';
  $lead = $isRetailLogin ? '小売販売業務へログインします。' : '受注管理へログインします。';
@endphp
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ログイン | {{ $systemName }}</title>
  <style>
    :root { font-family: "Noto Sans JP", Meiryo, system-ui, sans-serif; color: #172033; background: #f4f7fb; }
    body { min-height: 100vh; margin: 0; display: grid; place-items: center; }
    main { width: min(380px, calc(100vw - 40px)); border: 1px solid #dce4ee; border-radius: 10px; background: #fff; padding: 32px; box-shadow: 0 16px 42px rgba(22, 44, 75, .10); }
    h1 { margin: 0; font-size: 22px; }
    p { color: #64748b; font-size: 13px; }
    label { display: grid; gap: 6px; margin-top: 18px; font-size: 12px; font-weight: 700; }
    input { height: 38px; border: 1px solid #cfd9e7; border-radius: 5px; padding: 0 10px; font: inherit; }
    button { width: 100%; height: 42px; margin-top: 24px; border: 0; border-radius: 5px; background: #0b6ff6; color: #fff; font: inherit; font-weight: 800; }
    .error { margin: 14px 0 0; color: #c93a3a; font-size: 12px; }
    .mode { display: inline-flex; align-items: center; min-height: 24px; margin-bottom: 12px; padding: 0 9px; border-radius: 999px; background: {{ $isRetailLogin ? '#e9f8ef' : '#e8f2ff' }}; color: {{ $isRetailLogin ? '#137333' : '#075ecf' }}; font-size: 11px; font-weight: 800; }
  </style>
</head>
<body>
  <main>
    <div class="mode">{{ $isRetailLogin ? '小売' : '蔵販売' }}</div>
    <h1>{{ $systemName }}</h1>
    <p>{{ $lead }}</p>
    <form method="post" action="{{ route('login.store', absolute: false) }}">
      @csrf
      @isset($redirectTo)
        <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">
      @endisset
      <label>メールアドレス<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label>
      <label>パスワード<input type="password" name="password" autocomplete="current-password" required></label>
      @error('email')<p class="error">{{ $message }}</p>@enderror
      <button type="submit">ログイン</button>
    </form>
  </main>
</body>
</html>
