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
    .card{max-width:980px;background:#fff;border:1px solid #dce4ee;border-radius:6px;overflow:hidden}
    .head{padding:11px 14px;border-bottom:1px solid #e7edf4}
    .form{display:grid;gap:13px;padding:16px}
    label{display:grid;gap:5px;color:#475569;font-size:11px;font-weight:800}
    input,select,button{font:inherit;border:1px solid #cad6e6;border-radius:4px;background:#fff;color:#172033;padding:8px 10px}
    button{cursor:pointer}
    .tabs{display:flex;gap:4px;padding:0 16px;border-bottom:1px solid #dce4ee;background:#f8fafc;overflow-x:auto}
    .tab{border:0;border-bottom:3px solid transparent;border-radius:0;background:transparent;color:#64748b;font-weight:800;white-space:nowrap;padding:11px 14px 9px}
    .tab:hover{color:#0b6ff6}
    .tab.active{border-bottom-color:#0b6ff6;color:#0b6ff6;background:#fff}
    .tab-panel{display:none;gap:13px}
    .tab-panel.active{display:grid}
    .panel-head{padding-bottom:10px;border-bottom:1px solid #e7edf4}
    .panel-head h2{font-size:14px;margin-bottom:3px}
    .primary{background:#0b6ff6;color:#fff;border-color:#0b6ff6;font-weight:800}
    .actions{display:flex;justify-content:flex-end;gap:8px}
    .notice{padding:10px 12px;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534;border-radius:4px;font-size:12px}
    .muted{color:#64748b;font-size:11px;line-height:1.6}
    .bank-accounts{display:grid;gap:8px}
    .bank-account{display:grid;grid-template-columns:42px minmax(0,1fr) 170px;gap:10px;align-items:center;padding:8px;border:1px solid #e2e8f0;border-radius:5px;background:#f8fafc}
    .bank-account-number{font-size:11px;font-weight:800;color:#64748b;text-align:center}
    .bank-account textarea{width:100%;min-height:50px;resize:vertical;font:inherit;border:1px solid #cad6e6;border-radius:4px;background:#fff;color:#172033;padding:8px 10px}
    .bank-account .visibility{display:flex;grid-template-columns:auto 1fr;align-items:center;gap:7px;margin:0}
    .bank-account .visibility input{width:auto;min-height:auto}
    @media(max-width:680px){.bank-account{grid-template-columns:34px minmax(0,1fr)}.bank-account .visibility{grid-column:2}}
  </style>
</head>
<body>
  <header>
    <h1>設定</h1>
    <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">ログアウト</button></form>
  </header>
  <main>
    @php
      $errorKeys = $errors->keys();
      $initialTab = collect($errorKeys)->contains(fn ($key) => str_starts_with($key, 'invoice_bank_accounts'))
          ? 'billing'
          : (collect($errorKeys)->contains(fn ($key) => str_starts_with($key, 'company_'))
              ? 'company'
              : (collect($errorKeys)->contains(fn ($key) => str_starts_with($key, 'alcohol_'))
                  ? 'alcohol'
                  : (in_array('hide_zero_stock_lots', $errorKeys, true) ? 'inventory' : 'display')));
    @endphp
    <section class="card">
      <div class="head">
        <h2>システム設定</h2>
        <p class="muted">設定内容を種類ごとに切り替えて編集します。</p>
      </div>
      <div class="tabs" role="tablist" aria-label="設定グループ">
        <button class="tab active" type="button" role="tab" aria-selected="true" aria-controls="settings-company" data-tab="company">会社基礎情報</button>
        <button class="tab" type="button" role="tab" aria-selected="false" aria-controls="settings-display" data-tab="display">基本表示</button>
        <button class="tab" type="button" role="tab" aria-selected="false" aria-controls="settings-inventory" data-tab="inventory">在庫</button>
        <button class="tab" type="button" role="tab" aria-selected="false" aria-controls="settings-alcohol" data-tab="alcohol">酒類ロット</button>
        <button class="tab" type="button" role="tab" aria-selected="false" aria-controls="settings-billing" data-tab="billing">請求書</button>
      </div>
      <form class="form" method="post" action="{{ route('settings.update') }}">
        @csrf
        @method('put')
        @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
        <section class="tab-panel active" id="settings-company" role="tabpanel" data-panel="company">
          <div class="panel-head">
            <h2>会社基礎情報</h2>
            <p class="muted">会社の所在地・連絡先・適格請求書発行事業者登録番号を管理します。</p>
          </div>
          <label>郵便番号
            <input name="company_postal_code" value="{{ old('company_postal_code', $settings['company_postal_code']) }}" required maxlength="10" inputmode="numeric" placeholder="784-0033">
          </label>
          <label>住所
            <input name="company_address" value="{{ old('company_address', $settings['company_address']) }}" required maxlength="200">
          </label>
          <label>会社名
            <input name="company_name" value="{{ old('company_name', $settings['company_name']) }}" required maxlength="80">
          </label>
          <label>TEL
            <input name="company_phone" value="{{ old('company_phone', $settings['company_phone']) }}" required maxlength="30" inputmode="tel">
          </label>
          <label>FAX
            <input name="company_fax" value="{{ old('company_fax', $settings['company_fax']) }}" maxlength="30" inputmode="tel">
          </label>
          <label>登録番号
            <input name="company_registration_number" value="{{ old('company_registration_number', $settings['company_registration_number']) }}" required maxlength="30" placeholder="T2-4900-0201-2728">
          </label>
        </section>

        <section class="tab-panel" id="settings-display" role="tabpanel" data-panel="display">
          <div class="panel-head">
            <h2>基本表示</h2>
            <p class="muted">システム名、全体の基本色と更新間隔を変更します。</p>
          </div>
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
        </section>

        <section class="tab-panel" id="settings-inventory" role="tabpanel" data-panel="inventory">
          <div class="panel-head">
            <h2>在庫ロット表示</h2>
            <p class="muted">在庫数が0の過去ロットは削除せず、通常の在庫表示と出荷候補だけを整理します。マイナス在庫は常に表示されます。</p>
          </div>
          <label style="display:flex;align-items:center;gap:8px;font-weight:800">
            <input type="checkbox" name="hide_zero_stock_lots" value="1" style="width:auto;min-height:auto" @checked(old('hide_zero_stock_lots', $settings['hide_zero_stock_lots']) === '1')>
            在庫0のロットを通常は表示しない
          </label>
        </section>

        <section class="tab-panel" id="settings-alcohol" role="tabpanel" data-panel="alcohol">
          <div class="panel-head">
            <h2>酒類ロット判定</h2>
            <p class="muted">酒類商品の標準アルコール度数に対する、全商品共通の許容差です。</p>
          </div>
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
        </section>

        <section class="tab-panel" id="settings-billing" role="tabpanel" data-panel="billing">
          <div class="panel-head">
            <h2>請求書の振込先</h2>
            <p class="muted">振込先は最大10件登録できます。「請求書に表示する」にチェックした振込先だけを、登録順に請求書へ印字します。</p>
          </div>
          @php
            $oldBankAccounts = old('invoice_bank_accounts', $bankAccounts);
          @endphp
          <div class="bank-accounts">
            @for($index = 0; $index < 10; $index++)
              @php
                $account = $oldBankAccounts[$index] ?? ['text' => '', 'is_visible' => false];
              @endphp
              <div class="bank-account">
                <div class="bank-account-number">{{ $index + 1 }}</div>
                <textarea
                  name="invoice_bank_accounts[{{ $index }}][text]"
                  maxlength="500"
                  rows="2"
                  aria-label="振込先 {{ $index + 1 }}"
                  placeholder="銀行名、支店名、口座種別、口座番号、口座名義など">{{ $account['text'] ?? '' }}</textarea>
                <label class="visibility">
                  <input
                    type="checkbox"
                    name="invoice_bank_accounts[{{ $index }}][is_visible]"
                    value="1"
                    @checked((bool) ($account['is_visible'] ?? false))>
                  請求書に表示する
                </label>
              </div>
            @endfor
          </div>
        </section>
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
  <script>
    (() => {
      const tabs = [...document.querySelectorAll('[data-tab]')];
      const panels = [...document.querySelectorAll('[data-panel]')];
      const validTabs = tabs.map(tab => tab.dataset.tab);
      const errorTab = @json($errors->any() ? $initialTab : null);
      const rememberedTab = sessionStorage.getItem('settings.activeTab');

      function activate(name) {
        if (!validTabs.includes(name)) name = 'display';
        tabs.forEach(tab => {
          const active = tab.dataset.tab === name;
          tab.classList.toggle('active', active);
          tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach(panel => panel.classList.toggle('active', panel.dataset.panel === name));
        sessionStorage.setItem('settings.activeTab', name);
      }

      tabs.forEach(tab => tab.addEventListener('click', () => activate(tab.dataset.tab)));
      activate(errorTab || rememberedTab || 'company');
    })();
  </script>
</body>
</html>
