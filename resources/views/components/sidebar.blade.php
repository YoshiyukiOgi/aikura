@php
  $navigationUser = auth()->user();
  $appSettings = \App\Models\AppSetting::values([
      'company_name' => '白杉酒造株式会社',
      'system_name' => 'B2B販売管理システム',
      'theme' => 'blue',
      'auto_refresh_enabled' => '1',
      'auto_refresh_interval_seconds' => '30',
  ]);
  $themes = [
      'blue' => ['primary' => '#126deb', 'primaryDark' => '#075ecf', 'sidebar' => '#0d1f36', 'hover' => '#1b385b', 'bg' => '#f5f7fb', 'card' => '#ffffff', 'line' => '#dce4ee'],
      'green' => ['primary' => '#15803d', 'primaryDark' => '#166534', 'sidebar' => '#0f2f24', 'hover' => '#1f4d3a', 'bg' => '#f5fbf7', 'card' => '#ffffff', 'line' => '#d7e7dc'],
      'brown' => ['primary' => '#b45309', 'primaryDark' => '#92400e', 'sidebar' => '#332313', 'hover' => '#5b3b1b', 'bg' => '#fbf7f1', 'card' => '#ffffff', 'line' => '#eadfce'],
      'indigo' => ['primary' => '#4f46e5', 'primaryDark' => '#4338ca', 'sidebar' => '#1e1b4b', 'hover' => '#312e81', 'bg' => '#f6f7ff', 'card' => '#ffffff', 'line' => '#dde1f5'],
      'slate' => ['primary' => '#475569', 'primaryDark' => '#334155', 'sidebar' => '#111827', 'hover' => '#374151', 'bg' => '#f8fafc', 'card' => '#ffffff', 'line' => '#d8dee8'],
      'neon' => ['primary' => '#00c8ff', 'primaryDark' => '#007da3', 'sidebar' => '#10081f', 'hover' => '#172b46', 'bg' => '#f6fbff', 'card' => '#ffffff', 'line' => '#bcecff'],
      'disco' => ['primary' => '#ff2bd6', 'primaryDark' => '#9b1dff', 'sidebar' => '#15001f', 'hover' => '#2b1248', 'bg' => '#fff7fe', 'card' => '#ffffff', 'line' => '#ffd0f5'],
  ];
  $theme = $themes[$appSettings['theme'] ?? 'blue'] ?? $themes['blue'];
  $themeName = $appSettings['theme'] ?? 'blue';
  $invoiceMenuActive = request()->routeIs('billing.index')
      || request()->routeIs('billing.monthly-invoices')
      || request()->routeIs('billing.spot-invoices')
      || request()->routeIs('billing.invoices')
      || request()->routeIs('billing.invoice-print');
  $paymentMenuActive = request()->routeIs('billing.payment-entry')
      || request()->routeIs('billing.payment-confirmation')
      || request()->routeIs('billing.payment-reviews');
  $foundationMasters = [
      ['key' => 'consumption-tax-categories', 'label' => '消費税区分', 'permission' => 'tax_master.view'],
      ['key' => 'consumption-tax-rates', 'label' => '消費税率', 'permission' => 'tax_master.view'],
      ['key' => 'units', 'label' => '単位', 'permission' => 'unit_master.view'],
      ['key' => 'liquor-tax-categories', 'label' => '酒税区分', 'permission' => 'liquor_tax_master.view'],
      ['key' => 'stock-locations', 'label' => '在庫場所', 'permission' => 'stock_location_master.view'],
      ['key' => 'transaction-categories', 'label' => '取引区分', 'permission' => 'transaction_category_master.view'],
      ['key' => 'settlement-receivable-categories', 'label' => '売掛精算区分', 'permission' => 'settlement_category_master.view'],
      ['key' => 'number-sequences', 'label' => '採番', 'permission' => 'number_sequence_master.view'],
      ['key' => 'roles', 'label' => '権限・ロール', 'permission' => 'role_master.view'],
  ];
  $firstFoundationMaster = collect($foundationMasters)->first(fn ($item) => $navigationUser?->hasPermission($item['permission']) || $navigationUser?->hasPermission('role.manage')) ?? ['key' => 'consumption-tax-categories'];
  $canViewBasicMasterGroup = $navigationUser?->hasPermission('product_master.view')
      || $navigationUser?->hasPermission('customer_master.view')
      || $navigationUser?->hasPermission('billing_cycle_master.view')
      || $firstFoundationMaster;
  $basicMasterHref = $navigationUser?->hasPermission('product_master.view')
      ? '/masters/products'
      : ($navigationUser?->hasPermission('customer_master.view')
          ? '/masters/customers'
          : ($navigationUser?->hasPermission('billing_cycle_master.view')
              ? '/masters/billing-cycles'
              : '/masters/foundation/'.$firstFoundationMaster['key']));
@endphp
<style>
  :root{--app-primary:{{ $theme['primary'] }};--app-primary-dark:{{ $theme['primaryDark'] }};--app-sidebar-bg:{{ $theme['sidebar'] }};--app-sidebar-hover:{{ $theme['hover'] }};--app-bg:{{ $theme['bg'] }};--app-card:{{ $theme['card'] }};--app-line:{{ $theme['line'] }}}
  .app-sidebar{position:fixed;z-index:10;inset:0 auto 0 0;width:208px;padding:20px 10px;background:var(--app-sidebar-bg);color:#eaf1fb;box-shadow:2px 0 14px rgba(15,31,54,.12);overflow-y:auto;overflow-x:hidden}
  .app-sidebar > *{position:relative;z-index:1}
  .app-sidebar__brand{padding:0 12px 22px;border-bottom:1px solid rgba(255,255,255,.14);font-weight:800;font-size:14px;line-height:1.5}
  .app-sidebar__brand small{display:block;color:#9fb1c9;font-weight:500;font-size:10px}
  .app-sidebar__nav{display:grid;gap:3px;margin-top:16px;padding-bottom:28px}
  .app-sidebar__nav a{display:flex;align-items:center;min-height:34px;padding:0 12px;border-radius:4px;color:#d9e5f4;text-decoration:none;font-size:12px;transition:background .12s ease,box-shadow .12s ease}
  .app-sidebar__nav a:hover{background:var(--app-sidebar-hover);color:#fff}
  .app-sidebar__nav a.active{background:var(--app-primary);color:#fff;font-weight:800;box-shadow:inset 3px 0 0 rgba(255,255,255,.45),0 3px 8px rgba(0,0,0,.18)}
  .app-sidebar__group{display:grid;gap:2px}
  .app-sidebar__parent{justify-content:space-between}
  .app-sidebar__parent::after{content:'▶';font-size:10px;color:#9fb1c9}
  .app-sidebar__group.open .app-sidebar__parent::after{content:'▼'}
  .app-sidebar__subnav{display:none;gap:2px;margin:1px 0 4px 10px;padding-left:8px;border-left:1px solid rgba(255,255,255,.18)}
  .app-sidebar__group.open .app-sidebar__subnav{display:grid}
  .app-sidebar__subnav a{min-height:28px;padding:0 10px;font-size:11px;color:#c5d3e5}
  .app-sidebar__subnav a.active{box-shadow:inset 2px 0 0 rgba(255,255,255,.5),0 2px 5px rgba(0,0,0,.14)}
  .app-sidebar__section{margin:14px 12px 5px;color:#7f94ae;font-size:10px;font-weight:800}
  body{padding-left:208px;background:var(--app-bg)!important}
  header,.card{border-color:var(--app-line)!important}
  .card{background:var(--app-card)!important}
  a{color:var(--app-primary-dark)}
  button.primary,.primary{background:var(--app-primary)!important;border-color:var(--app-primary)!important}
  .order-no,.task-number{color:var(--app-primary-dark)!important}
  tbody tr.selected,.task.selected{box-shadow:inset 3px 0 var(--app-primary)!important}
  .app-sidebar~* #available-lines tr.customer-unavailable,#available-lines tr.customer-unavailable{opacity:.45;background:#f8fafc}
  @keyframes neonPulse{0%,100%{text-shadow:0 0 0 rgba(255,255,255,0);box-shadow:0 0 0 rgba(0,0,0,0)}50%{text-shadow:0 0 8px rgba(0,200,255,.55),0 0 18px rgba(0,200,255,.35);box-shadow:0 0 12px rgba(0,200,255,.18)}}
  @keyframes discoFlash{0%,100%{filter:saturate(1) brightness(1);opacity:.92}50%{filter:saturate(1.45) brightness(1.15);opacity:1}}
  @keyframes sidebarScan{0%{opacity:.35;transform:translateY(-120%)}50%{opacity:.75}100%{opacity:.35;transform:translateY(220%)}}
  @keyframes sidebarHueShift{0%,100%{filter:hue-rotate(0deg)}33%{filter:hue-rotate(12deg)}66%{filter:hue-rotate(-10deg)}}
  @media (prefers-reduced-motion: no-preference){
    .app-sidebar__nav a.active,
    .app-sidebar__brand,
    .app-sidebar__section{
      animation:neonPulse 2.1s ease-in-out infinite;
    }
  }
  @media(max-width:960px){body{padding-left:0}.app-sidebar{display:none}}
  @media print{body{padding-left:0!important;background:#fff!important}.app-sidebar{display:none!important}}
  @if($themeName === 'neon')
    .app-sidebar{background:
      linear-gradient(180deg,rgba(16,8,31,.98) 0%,rgba(6,27,42,.98) 100%),
      repeating-linear-gradient(180deg,rgba(255,255,255,.08) 0 1px,transparent 1px 18px);
      box-shadow:2px 0 18px rgba(0,200,255,.25);
      position:fixed;
    }
    .app-sidebar::before{content:'';position:absolute;inset:0;background:linear-gradient(180deg,transparent 0%,rgba(0,200,255,.08) 45%,transparent 100%);pointer-events:none;z-index:0}
    .app-sidebar::after{content:'';position:absolute;inset:0;background:radial-gradient(circle at 50% 0%,rgba(0,200,255,.14),transparent 45%),radial-gradient(circle at 100% 100%,rgba(120,80,255,.12),transparent 35%);pointer-events:none;z-index:0;mix-blend-mode:screen;animation:sidebarHueShift 12s linear infinite}
    .app-sidebar__brand{position:relative;text-shadow:0 0 10px rgba(0,200,255,.24)}
    .app-sidebar__brand small{color:#9aeaff;animation:discoFlash 2.8s ease-in-out infinite}
    .app-sidebar__section{color:#7fe7ff;text-shadow:0 0 8px rgba(0,200,255,.22)}
    .app-sidebar__nav a.active{background:#00c8ff;color:#03131a;text-shadow:0 0 10px rgba(255,255,255,.55);box-shadow:0 0 14px rgba(0,200,255,.75),inset 3px 0 0 #ffffff}
    .app-sidebar__nav a{position:relative}
    .app-sidebar__nav a:hover{background:rgba(0,200,255,.16);box-shadow:0 0 10px rgba(0,200,255,.16)}
  @endif
  @if($themeName === 'disco')
    body{background:linear-gradient(135deg,#fff7fe 0%,#f4fbff 45%,#fffbe8 100%)!important}
    .app-sidebar{background:
      linear-gradient(160deg,#15001f 0%,#2b0048 36%,#001f4d 68%,#162100 100%);
      box-shadow:3px 0 24px rgba(255,43,214,.38);
      position:fixed;
    }
    .app-sidebar::before{content:'';position:absolute;inset:-20% -30%;background:
      repeating-linear-gradient(135deg,rgba(255,43,214,.16) 0 2px,transparent 2px 16px),
      repeating-linear-gradient(45deg,rgba(0,229,255,.14) 0 1px,transparent 1px 18px);
      mix-blend-mode:screen;pointer-events:none;z-index:0;animation:sidebarHueShift 10s linear infinite}
    .app-sidebar::after{content:'';position:absolute;inset:0;background:radial-gradient(circle at 20% 20%,rgba(255,43,214,.18),transparent 26%),radial-gradient(circle at 80% 24%,rgba(0,229,255,.18),transparent 24%),radial-gradient(circle at 50% 85%,rgba(255,214,0,.12),transparent 28%);pointer-events:none;z-index:0;animation:discoFlash 2.4s ease-in-out infinite}
    .app-sidebar__brand{position:relative;text-shadow:0 0 10px rgba(255,43,214,.45),0 0 18px rgba(0,229,255,.22);animation:discoFlash 3s ease-in-out infinite}
    .app-sidebar__brand small{color:#ffd3f4;animation:discoFlash 1.9s ease-in-out infinite}
    .app-sidebar__section{color:#ffc8f1;text-shadow:0 0 8px rgba(255,43,214,.34)}
    .app-sidebar__nav a.active{background:linear-gradient(90deg,#ff2bd6 0%,#8d35ff 42%,#00e5ff 100%);color:#fff;box-shadow:0 0 16px rgba(255,43,214,.6),0 0 18px rgba(0,229,255,.3),inset 3px 0 0 rgba(255,255,255,.85)}
    .app-sidebar__nav a{position:relative}
    .app-sidebar__nav a:hover{background:rgba(255,43,214,.14);box-shadow:0 0 10px rgba(255,43,214,.18)}
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
        <div class="app-sidebar__subnav" aria-label="在庫メニュー">
          <a href="/inventory" class="{{ request()->routeIs('inventory.index') ? 'active' : '' }}">在庫業務</a>
        </div>
      </div>
    @endif
    @if($navigationUser?->hasPermission('billing.view'))
      <div class="app-sidebar__group {{ $invoiceMenuActive ? 'open' : '' }}" data-sidebar-group="billing-invoice">
        <a href="/billing" class="app-sidebar__parent {{ $invoiceMenuActive ? 'active' : '' }}" data-sidebar-toggle="billing-invoice" aria-expanded="{{ $invoiceMenuActive ? 'true' : 'false' }}">請求</a>
        <div class="app-sidebar__subnav" aria-label="請求メニュー">
          <a href="/billing" class="{{ request()->routeIs('billing.index') || request()->routeIs('billing.monthly-invoices') ? 'active' : '' }}">月次請求計算</a>
          <a href="/billing/spot-invoices" class="{{ request()->routeIs('billing.spot-invoices') ? 'active' : '' }}">都度請求計算</a>
          <a href="/billing/invoices" class="{{ request()->routeIs('billing.invoices') ? 'active' : '' }}">請求書確定</a>
          <a href="/billing/invoice-print" class="{{ request()->routeIs('billing.invoice-print') ? 'active' : '' }}">請求書印刷</a>
        </div>
      </div>
      <div class="app-sidebar__group {{ $paymentMenuActive ? 'open' : '' }}" data-sidebar-group="billing-payment">
        <a href="/billing/payment-entry" class="app-sidebar__parent {{ $paymentMenuActive ? 'active' : '' }}" data-sidebar-toggle="billing-payment" aria-expanded="{{ $paymentMenuActive ? 'true' : 'false' }}">入金</a>
        <div class="app-sidebar__subnav" aria-label="入金メニュー">
          <a href="/billing/payment-entry" class="{{ request()->routeIs('billing.payment-entry') || request()->routeIs('billing.payment-confirmation') ? 'active' : '' }}">入金登録・消込</a>
          <a href="/billing/payment-reviews" class="{{ request()->routeIs('billing.payment-reviews') ? 'active' : '' }}">入金確認</a>
        </div>
      </div>
      <a href="/billing/receivables" class="{{ request()->routeIs('billing.receivables') ? 'active' : '' }}">売掛残高</a>
    @endif
    @if($navigationUser?->hasPermission('sales_return.view'))
      <div class="app-sidebar__group {{ request()->routeIs('sales-returns.*') ? 'open' : '' }}" data-sidebar-group="sales-returns">
        <a href="/sales-returns/register" class="app-sidebar__parent {{ request()->routeIs('sales-returns.*') ? 'active' : '' }}" data-sidebar-toggle="sales-returns" aria-expanded="{{ request()->routeIs('sales-returns.*') ? 'true' : 'false' }}">返品・赤伝</a>
        <div class="app-sidebar__subnav" aria-label="返品・赤伝メニュー">
          <a href="/sales-returns/register" class="{{ request()->routeIs('sales-returns.register') || request()->routeIs('sales-returns.index') ? 'active' : '' }}">返品・赤伝登録</a>
          <a href="/sales-returns/history" class="{{ request()->routeIs('sales-returns.history') ? 'active' : '' }}">返品・赤伝一覧</a>
        </div>
      </div>
    @endif
    @if($navigationUser?->hasPermission('tax.view'))<a href="/tax" class="{{ request()->routeIs('tax.*') ? 'active' : '' }}">税務</a>@endif
    <div class="app-sidebar__section">管理</div>
    @if($navigationUser?->hasPermission('product_master.view'))<a href="/masters/products" class="{{ request()->routeIs('masters.products.*') ? 'active' : '' }}">商品マスタ</a>@endif
    @if($navigationUser?->hasPermission('customer_master.view') || $navigationUser?->hasPermission('billing_cycle_master.view'))
      @php($masterGroupActive = request()->routeIs('masters.customers.*') || request()->routeIs('masters.billing-cycles.*'))
      <div class="app-sidebar__group {{ $masterGroupActive ? 'open' : '' }}" data-sidebar-group="masters">
        <a href="{{ $navigationUser?->hasPermission('customer_master.view') ? '/masters/customers' : '/masters/billing-cycles' }}" class="app-sidebar__parent {{ $masterGroupActive ? 'active' : '' }}" data-sidebar-toggle="masters" aria-expanded="{{ $masterGroupActive ? 'true' : 'false' }}">マスタ</a>
        <div class="app-sidebar__subnav" aria-label="マスタメニュー">
          @if($navigationUser?->hasPermission('customer_master.view'))<a href="/masters/customers" class="{{ request()->routeIs('masters.customers.*') ? 'active' : '' }}">取引先</a>@endif
          @if($navigationUser?->hasPermission('billing_cycle_master.view'))<a href="/masters/billing-cycles" class="{{ request()->routeIs('masters.billing-cycles.*') ? 'active' : '' }}">締日条件</a>@endif
        </div>
      </div>
    @endif
    @if($navigationUser?->hasPermission('role.manage'))<a href="/settings" class="{{ request()->routeIs('settings.*') ? 'active' : '' }}">設定</a>@endif
    @if($canViewBasicMasterGroup)
      <div class="app-sidebar__group {{ request()->routeIs('masters.foundation.*') ? 'open' : '' }}" data-sidebar-group="foundation-masters">
        <a href="{{ $basicMasterHref }}" class="app-sidebar__parent {{ request()->routeIs('masters.foundation.*') || request()->routeIs('masters.products.*') || request()->routeIs('masters.customers.*') || request()->routeIs('masters.billing-cycles.*') ? 'active' : '' }}" data-sidebar-toggle="foundation-masters" aria-expanded="{{ request()->routeIs('masters.foundation.*') ? 'true' : 'false' }}">基本マスタ</a>
        <div class="app-sidebar__subnav" aria-label="基本マスタメニュー">
          @if($navigationUser?->hasPermission('product_master.view'))<a href="/masters/products" class="{{ request()->routeIs('masters.products.*') ? 'active' : '' }}">商品</a>@endif
          @if($navigationUser?->hasPermission('customer_master.view'))<a href="/masters/customers" class="{{ request()->routeIs('masters.customers.*') ? 'active' : '' }}">取引先</a>@endif
          @if($navigationUser?->hasPermission('billing_cycle_master.view'))<a href="/masters/billing-cycles" class="{{ request()->routeIs('masters.billing-cycles.*') ? 'active' : '' }}">締日条件</a>@endif
          @foreach($foundationMasters as $foundationMaster)
            @if($navigationUser?->hasPermission($foundationMaster['permission']) || $navigationUser?->hasPermission('role.manage'))
              <a href="/masters/foundation/{{ $foundationMaster['key'] }}" class="{{ request()->routeIs('masters.foundation.*') && request()->route('master') === $foundationMaster['key'] ? 'active' : '' }}">{{ $foundationMaster['label'] }}</a>
            @endif
          @endforeach
        </div>
      </div>
    @endif
  </nav>
</aside>
<script>
  (() => {
    const sidebar = document.querySelector('.app-sidebar');
    const sidebarScrollKey = 'sidebar:scrollTop';
    if (sidebar) {
      const savedScrollTop = Number(localStorage.getItem(sidebarScrollKey) || 0);
      if (savedScrollTop > 0) requestAnimationFrame(() => { sidebar.scrollTop = savedScrollTop; });
      sidebar.addEventListener('scroll', () => localStorage.setItem(sidebarScrollKey, String(sidebar.scrollTop)), { passive: true });
    }

    document.querySelectorAll('[data-sidebar-toggle]').forEach((toggle) => {
      const groupKey = toggle.dataset.sidebarToggle;
      const group = document.querySelector(`[data-sidebar-group="${groupKey}"]`);
      if (!group) return;
      const storageKey = `sidebar:${groupKey}:open`;

      const saved = localStorage.getItem(storageKey);
      if (saved !== null && !group.classList.contains('open')) {
        group.classList.toggle('open', saved === '1');
        toggle.setAttribute('aria-expanded', saved === '1' ? 'true' : 'false');
      }

      toggle.addEventListener('click', (event) => {
        event.preventDefault();
        const isOpen = group.classList.toggle('open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        localStorage.setItem(storageKey, isOpen ? '1' : '0');
      });
    });

    const unitNames = { bottle: '本', case: 'ケース', box: '箱', piece: '個', bag: '袋', liter: 'L', milliliter: 'mL', kilogram: 'kg', gram: 'g' };
    document.querySelectorAll('.app-sidebar__nav a[href^="/billing"]').forEach((link) => {
      link.addEventListener('click', () => {
        try {
          const saved = JSON.parse(localStorage.getItem('billingCommonSearch') || '{}') || {};
          const fieldState = JSON.parse(localStorage.getItem('billingSearchState') || '{}') || {};
          const values = {
            customer: fieldState['common-customer'] ?? saved.customer ?? '',
            month: fieldState['common-billing-month'] ?? saved.month ?? '',
            closing_day: fieldState['common-closing-day'] ?? saved.closing_day ?? '',
            due_date: fieldState['common-due-date'] ?? saved.due_date ?? '',
          };
          const url = new URL(link.href, window.location.origin);
          Object.entries(values).forEach(([key, value]) => {
            value ? url.searchParams.set(key, value) : url.searchParams.delete(key);
          });
          link.href = `${url.pathname}${url.search}`;
        } catch (_) {}
      });
    });

    const replaceUnitCodes = (root = document.body) => {
      const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
      const nodes = [];
      while (walker.nextNode()) nodes.push(walker.currentNode);
      nodes.forEach((node) => {
        node.nodeValue = node.nodeValue.replace(/\b(bottle|case|box|piece|bag|liter|milliliter|kilogram|gram)\b/g, (code) => unitNames[code]);
      });
    };
    const normalizeQuantities = (root = document) => {
      root.querySelectorAll?.('input[type="number"][data-quantity], input.qty, #lines input[type="number"]').forEach((input) => {
        input.min = '1';
        input.step = '1';
        if (input.value !== '' && Number.isFinite(Number(input.value))) {
          input.value = String(Math.max(1, Math.round(Number(input.value))));
        }
      });
    };
    normalizeQuantities();
    replaceUnitCodes();
    new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => {
      if (node.nodeType === 1) {
        normalizeQuantities(node);
        replaceUnitCodes(node);
      }
    }))).observe(document.body, { childList: true, subtree: true });

    document.addEventListener('change', async (event) => {
      const checkbox = event.target;
      if (!(checkbox instanceof HTMLInputElement) || !checkbox.matches('#available-lines input[type="checkbox"]')) return;

      const sourceRow = checkbox.closest('tr');
      if (!sourceRow?.dataset.customerId) {
        try {
          const response = await fetch('/api/v1/sales-orders?status=received&per_page=100', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
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
