@php
  $navigationUser = auth()->user();
  $appSettings = \App\Models\AppSetting::values([
      'company_name' => '白鶴酒造株式会社',
      'system_name' => 'B2B販売管理システム',
      'theme' => 'blue',
      'auto_refresh_enabled' => '1',
      'auto_refresh_interval_seconds' => '30',
  ]);
  $themes = [
      'blue' => ['primary' => '#126deb', 'primaryDark' => '#075ecf', 'sidebar' => '#0d1f36', 'hover' => '#1b385b', 'activeText' => '#ffffff', 'bg' => '#f5f7fb', 'card' => '#ffffff', 'line' => '#dce4ee'],
      'green' => ['primary' => '#15803d', 'primaryDark' => '#166534', 'sidebar' => '#0f2f24', 'hover' => '#1f4d3a', 'activeText' => '#ffffff', 'bg' => '#f5fbf7', 'card' => '#ffffff', 'line' => '#d7e7dc'],
      'brown' => ['primary' => '#b45309', 'primaryDark' => '#92400e', 'sidebar' => '#332313', 'hover' => '#5b3b1b', 'activeText' => '#ffffff', 'bg' => '#fbf7f1', 'card' => '#ffffff', 'line' => '#eadfce'],
      'indigo' => ['primary' => '#4f46e5', 'primaryDark' => '#4338ca', 'sidebar' => '#1e1b4b', 'hover' => '#312e81', 'activeText' => '#ffffff', 'bg' => '#f6f7ff', 'card' => '#ffffff', 'line' => '#dde1f5'],
      'slate' => ['primary' => '#475569', 'primaryDark' => '#334155', 'sidebar' => '#111827', 'hover' => '#374151', 'activeText' => '#ffffff', 'bg' => '#f8fafc', 'card' => '#ffffff', 'line' => '#d8dee8'],
      'neon' => ['primary' => '#00c8ff', 'primaryDark' => '#007da3', 'sidebar' => '#10081f', 'hover' => '#172b46', 'activeText' => '#ffffff', 'bg' => '#f6fbff', 'card' => '#ffffff', 'line' => '#bcecff'],
      'disco' => ['primary' => '#ff2bd6', 'primaryDark' => '#9b1dff', 'sidebar' => '#15001f', 'hover' => '#2b1248', 'activeText' => '#ffffff', 'bg' => '#fff7fe', 'card' => '#ffffff', 'line' => '#ffd0f5'],
  ];
  $theme = $themes[$appSettings['theme'] ?? 'blue'] ?? $themes['blue'];
  $themeName = $appSettings['theme'] ?? 'blue';
@endphp
<style>
  :root{--app-primary:{{ $theme['primary'] }};--app-primary-dark:{{ $theme['primaryDark'] }};--app-sidebar-bg:{{ $theme['sidebar'] }};--app-sidebar-hover:{{ $theme['hover'] }};--app-bg:{{ $theme['bg'] }};--app-card:{{ $theme['card'] }};--app-line:{{ $theme['line'] }}}
  .app-sidebar{position:fixed;z-index:10;inset:0 auto 0 0;width:208px;padding:20px 10px;background:var(--app-sidebar-bg);color:#eaf1fb;box-shadow:2px 0 14px rgba(15,31,54,.12)}
  .app-sidebar__brand{padding:0 12px 22px;border-bottom:1px solid rgba(255,255,255,.14);font-weight:800;font-size:14px;line-height:1.5}.app-sidebar__brand small{display:block;color:#9fb1c9;font-weight:500;font-size:10px}
  .app-sidebar__nav{display:grid;gap:3px;margin-top:16px}.app-sidebar__nav a{display:flex;align-items:center;min-height:34px;padding:0 12px;border-radius:4px;color:#d9e5f4;text-decoration:none;font-size:12px;transition:background .12s ease,box-shadow .12s ease}.app-sidebar__nav a:hover{background:var(--app-sidebar-hover);color:#fff}.app-sidebar__nav a.active{background:var(--app-primary);color:#fff;font-weight:800;box-shadow:inset 3px 0 0 rgba(255,255,255,.45),0 3px 8px rgba(0,0,0,.18)}.app-sidebar__group{display:grid;gap:2px}.app-sidebar__parent{justify-content:space-between}.app-sidebar__parent::after{content:'▸';font-size:10px;color:#9fb1c9}.app-sidebar__group.open .app-sidebar__parent::after{content:'▾'}.app-sidebar__subnav{display:none;gap:2px;margin:1px 0 4px 10px;padding-left:8px;border-left:1px solid rgba(255,255,255,.18)}.app-sidebar__group.open .app-sidebar__subnav{display:grid}.app-sidebar__subnav a{min-height:28px;padding:0 10px;font-size:11px;color:#c5d3e5}.app-sidebar__subnav a.active{box-shadow:inset 2px 0 0 rgba(255,255,255,.5),0 2px 5px rgba(0,0,0,.14)}.app-sidebar__section{margin:14px 12px 5px;color:#7f94ae;font-size:10px;font-weight:800}
  body{padding-left:208px;background:var(--app-bg)!important}header,.card{border-color:var(--app-line)!important}.card{background:var(--app-card)!important}a{color:var(--app-primary-dark)}button.primary,.primary{background:var(--app-primary)!important;border-color:var(--app-primary)!important}.order-no,.task-number{color:var(--app-primary-dark)!important}tbody tr.selected,.task.selected{box-shadow:inset 3px 0 var(--app-primary)!important}.app-sidebar~* #available-lines tr.customer-unavailable,#available-lines tr.customer-unavailable{opacity:.45;background:#f8fafc}.app-sidebar__disabled{display:flex;align-items:center;min-height:34px;padding:0 12px;border-radius:4px;color:#7f94ae;font-size:12px;cursor:default}@media(max-width:960px){body{padding-left:0}.app-sidebar{display:none}}@media print{body{padding-left:0!important;background:#fff!important}.app-sidebar{display:none!important}}
  @if($themeName === 'neon')
    .app-sidebar{background:linear-gradient(180deg,#10081f 0%,#061b2a 100%);box-shadow:2px 0 18px rgba(0,200,255,.25)}
    .app-sidebar__nav a.active{background:#00c8ff;color:#03131a;text-shadow:0 0 10px rgba(255,255,255,.55);box-shadow:0 0 14px rgba(0,200,255,.75),inset 3px 0 0 #ffffff}
    .app-sidebar__nav a:hover{box-shadow:0 0 10px rgba(0,200,255,.35)}
    button.primary,.primary{box-shadow:0 0 10px rgba(0,200,255,.32)}
    tbody tr.selected,.task.selected{background:#e9fbff!important;box-shadow:inset 3px 0 #00c8ff,0 0 12px rgba(0,200,255,.16)!important}
  @endif
  @if($themeName === 'disco')
    body{background:linear-gradient(135deg,#fff7fe 0%,#f4fbff 45%,#fffbe8 100%)!important}
    .app-sidebar{background:linear-gradient(160deg,#15001f 0%,#2b0048 36%,#001f4d 68%,#162100 100%);box-shadow:3px 0 24px rgba(255,43,214,.38)}
    .app-sidebar__brand{color:#fff;text-shadow:0 0 8px #ff2bd6,0 0 14px #00e5ff}
    .app-sidebar__nav a:hover{background:linear-gradient(90deg,rgba(255,43,214,.28),rgba(0,229,255,.24),rgba(203,255,0,.18));box-shadow:0 0 13px rgba(255,43,214,.45)}
    .app-sidebar__nav a.active{background:linear-gradient(90deg,#ff2bd6 0%,#8d35ff 42%,#00e5ff 100%);color:#fff;text-shadow:0 0 8px rgba(255,255,255,.9);box-shadow:0 0 18px rgba(255,43,214,.8),0 0 28px rgba(0,229,255,.35),inset 3px 0 0 #fff}
    button.primary,.primary{background:linear-gradient(90deg,#ff2bd6,#8d35ff,#00e5ff)!important;border-color:#ff8df0!important;box-shadow:0 0 14px rgba(255,43,214,.42)}
    tbody tr.selected,.task.selected{background:#fff0fc!important;box-shadow:inset 3px 0 #ff2bd6,0 0 14px rgba(255,43,214,.25)!important}
    .card{box-shadow:0 0 0 1px rgba(255,43,214,.08),0 8px 24px rgba(141,53,255,.08)}
  @endif
</style>
<aside class="app-sidebar" aria-label="主要メニュー">
  <div class="app-sidebar__brand">{{ $appSettings['company_name'] }}<small>{{ $appSettings['system_name'] }}</small></div>
  <nav class="app-sidebar__nav">
    @if($navigationUser?->hasPermission('sales_order.view'))<a href="/sales-orders" class="{{ request()->routeIs('sales-orders.*') ? 'active' : '' }}">受注</a>@endif
    @if($navigationUser?->hasPermission('shipment_pick.view'))<a href="/shipment-picks" class="{{ request()->routeIs('shipment-picks.*') ? 'active' : '' }}">ピッキング</a>@endif
    @if($navigationUser?->hasPermission('shipment.view'))<a href="/shipments" class="{{ request()->routeIs('shipments.index') ? 'active' : '' }}">出荷</a>@endif
    @if($navigationUser?->hasPermission('shipment.view'))<a href="/shipment-history" class="{{ request()->routeIs('shipments.history') ? 'active' : '' }}">出荷検索</a>@endif
    @if($navigationUser?->hasPermission('inventory.view'))
      <div class="app-sidebar__group {{ request()->routeIs('inventory.*') ? 'open' : '' }}" data-sidebar-group="inventory">
        <a href="/inventory" class="app-sidebar__parent {{ request()->routeIs('inventory.*') ? 'active' : '' }}" data-sidebar-toggle="inventory" aria-expanded="{{ request()->routeIs('inventory.*') ? 'true' : 'false' }}">在庫</a>
        <div class="app-sidebar__subnav" aria-label="在庫サブメニュー">
          <a href="/inventory" class="{{ request()->routeIs('inventory.index') ? 'active' : '' }}">在庫業務</a>
        </div>
      </div>
    @endif
    @if($navigationUser?->hasPermission('billing.view'))
      <div class="app-sidebar__group {{ request()->routeIs('billing.*') ? 'open' : '' }}" data-sidebar-group="billing">
        <a href="/billing" class="app-sidebar__parent {{ request()->routeIs('billing.*') ? 'active' : '' }}" data-sidebar-toggle="billing" aria-expanded="{{ request()->routeIs('billing.*') ? 'true' : 'false' }}">請求・入金</a>
        <div class="app-sidebar__subnav" aria-label="請求・入金サブメニュー">
          <a href="/billing/monthly-invoices" class="{{ request()->routeIs('billing.monthly-invoices') ? 'active' : '' }}">月次(締め)請求作成</a>
          <a href="/billing/spot-invoices" class="{{ request()->routeIs('billing.spot-invoices') ? 'active' : '' }}">都度請求作成</a>
          <a href="/billing/invoices" class="{{ request()->routeIs('billing.invoices') ? 'active' : '' }}">請求一覧</a>
          <a href="/billing/invoice-print" class="{{ request()->routeIs('billing.invoice-print') ? 'active' : '' }}">再請求書印刷</a>
          <a href="/billing/payment-confirmation" class="{{ request()->routeIs('billing.payment-confirmation') ? 'active' : '' }}">入金確認</a>
          <a href="/billing/payment-reviews" class="{{ request()->routeIs('billing.payment-reviews') ? 'active' : '' }}">要確認入金</a>
          <a href="/billing/receivables" class="{{ request()->routeIs('billing.receivables') ? 'active' : '' }}">売掛残高</a>
        </div>
      </div>
    @endif
    @if($navigationUser?->hasPermission('sales_return.view'))
      <div class="app-sidebar__group {{ request()->routeIs('sales-returns.*') ? 'open' : '' }}" data-sidebar-group="sales-returns">
        <a href="/sales-returns/register" class="app-sidebar__parent {{ request()->routeIs('sales-returns.*') ? 'active' : '' }}" data-sidebar-toggle="sales-returns" aria-expanded="{{ request()->routeIs('sales-returns.*') ? 'true' : 'false' }}">返品・赤伝</a>
        <div class="app-sidebar__subnav" aria-label="返品・赤伝サブメニュー">
          <a href="/sales-returns/register" class="{{ request()->routeIs('sales-returns.register') || request()->routeIs('sales-returns.index') ? 'active' : '' }}">返品・赤伝登録</a>
          <a href="/sales-returns/history" class="{{ request()->routeIs('sales-returns.history') ? 'active' : '' }}">返品・赤伝一覧</a>
        </div>
      </div>
    @endif
    @if($navigationUser?->hasPermission('tax.view'))<a href="/tax" class="{{ request()->routeIs('tax.*') ? 'active' : '' }}">税務</a>@endif
    <div class="app-sidebar__section">管理</div>
    @if($navigationUser?->hasPermission('customer_master.view') || $navigationUser?->hasPermission('billing_cycle_master.view') || $navigationUser?->hasPermission('product_master.view'))
      <div class="app-sidebar__group {{ request()->routeIs('masters.*') ? 'open' : '' }}" data-sidebar-group="masters">
        <a href="{{ $navigationUser?->hasPermission('customer_master.view') ? '/masters/customers' : ($navigationUser?->hasPermission('product_master.view') ? '/masters/products' : '/masters/billing-cycles') }}" class="app-sidebar__parent {{ request()->routeIs('masters.*') ? 'active' : '' }}" data-sidebar-toggle="masters" aria-expanded="{{ request()->routeIs('masters.*') ? 'true' : 'false' }}">マスタ</a>
        <div class="app-sidebar__subnav" aria-label="マスター管理サブメニュー">
          @if($navigationUser?->hasPermission('customer_master.view'))<a href="/masters/customers" class="{{ request()->routeIs('masters.customers.*') ? 'active' : '' }}">取引先</a>@endif
          @if($navigationUser?->hasPermission('product_master.view'))<a href="/masters/products" class="{{ request()->routeIs('masters.products.*') ? 'active' : '' }}">商品</a>@endif
          @if($navigationUser?->hasPermission('billing_cycle_master.view'))<a href="/masters/billing-cycles" class="{{ request()->routeIs('masters.billing-cycles.*') ? 'active' : '' }}">締日条件</a>@endif
        </div>
      </div>
    @else
      <span class="app-sidebar__disabled">マスタ</span>
    @endif
    @if($navigationUser?->hasPermission('role.manage'))<a href="/settings" class="{{ request()->routeIs('settings.*') ? 'active' : '' }}">設定</a>@endif
  </nav>
</aside>
<script>
  (() => {
    document.querySelectorAll('[data-sidebar-toggle]').forEach((toggle) => {
      const key = `sidebar:${toggle.dataset.sidebarToggle}:open`;
      const group = document.querySelector(`[data-sidebar-group="${toggle.dataset.sidebarToggle}"]`);
      if (!group) return;

      const saved = localStorage.getItem(key);
      if (saved !== null && !group.classList.contains('open')) {
        group.classList.toggle('open', saved === '1');
        toggle.setAttribute('aria-expanded', saved === '1' ? 'true' : 'false');
      }

      toggle.addEventListener('click', (event) => {
        event.preventDefault();
        const isOpen = group.classList.toggle('open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        localStorage.setItem(key, isOpen ? '1' : '0');
      });
    });

    const unitNames = { bottle: '本', case: 'ケース', box: '箱', piece: '個', bag: '袋', liter: 'L', milliliter: 'mL', kilogram: 'kg', gram: 'g' };
    const normalize = (root = document) => root.querySelectorAll('input[type="number"][data-quantity], input.qty, #lines input[type="number"]').forEach((input) => {
      input.min = '1'; input.step = '1';
      if (input.value !== '' && Number.isFinite(Number(input.value))) input.value = String(Math.max(1, Math.round(Number(input.value))));
    });
    normalize();
    new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => {
      if (node.nodeType === 1) { normalize(node); replaceUnitCodes(node); }
    }))).observe(document.body, { childList: true, subtree: true });
    const replaceUnitCodes = (root = document.body) => {
      const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
      const nodes = []; while (walker.nextNode()) nodes.push(walker.currentNode);
      nodes.forEach((node) => { node.nodeValue = node.nodeValue.replace(/\b(bottle|case|box|piece|bag|liter|milliliter|kilogram|gram)\b/g, (code) => unitNames[code]); });
    };
    replaceUnitCodes();
  })();
</script>
<script>
  (() => {
    document.addEventListener('change', async (event) => {
      const checkbox = event.target;
      if (!(checkbox instanceof HTMLInputElement) || !checkbox.matches('#available-lines input[type="checkbox"]')) return;

      const sourceRow = checkbox.closest('tr');
      if (!sourceRow?.dataset.customerId) {
        try {
          const response = await fetch('/api/v1/sales-orders?status=received&per_page=100', {
            headers: { Accept: 'application/json' }, credentials: 'same-origin',
          });
          const payload = await response.json();
          const customerByLineId = new Map(
            (payload.data?.sales_orders ?? []).flatMap((order) =>
              (order.lines ?? []).map((line) => [String(line.id), String(order.customer_id)]),
            ),
          );
          document.querySelectorAll('#available-lines tr').forEach((row) => {
            row.dataset.customerId = customerByLineId.get(row.dataset.line) ?? '';
          });
        } catch (_) {
          return;
        }
      }

      const rows = [...document.querySelectorAll('#available-lines tr')];
      const selectedRow = rows.find((row) => row.querySelector('input[type="checkbox"]:checked'));
      const selectedCustomerId = selectedRow?.dataset.customerId;

      rows.forEach((row) => {
        const rowCheckbox = row.querySelector('input[type="checkbox"]');
        const quantity = row.querySelector('input[type="number"]');
        if (!rowCheckbox || !quantity) return;

        const allowed = !selectedCustomerId || row.dataset.customerId === selectedCustomerId;
        rowCheckbox.disabled = !allowed;
        row.classList.toggle('customer-unavailable', !allowed);
        if (!allowed) {
          rowCheckbox.checked = false;
          quantity.disabled = true;
        } else {
          quantity.disabled = !rowCheckbox.checked;
        }
      });
    });
  })();
</script>
