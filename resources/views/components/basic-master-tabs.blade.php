@props(['active' => null])
@php
  $navigationUser = auth()->user();
  $canManage = fn (string $permission): bool => (bool) ($navigationUser?->hasPermission($permission) || $navigationUser?->hasPermission('role.manage'));
  $entries = [
    ['key' => 'products', 'label' => '商品', 'href' => '/masters/products', 'permission' => 'product_master.view'],
    ['key' => 'customers', 'label' => '取引先', 'href' => '/masters/customers', 'permission' => 'customer_master.view'],
    ['key' => 'billing-cycles', 'label' => '締日条件', 'href' => '/masters/billing-cycles', 'permission' => 'billing_cycle_master.view'],
    ['key' => 'consumption-tax-categories', 'label' => '消費税区分', 'href' => '/masters/foundation/consumption-tax-categories', 'permission' => 'tax_master.view'],
    ['key' => 'consumption-tax-rates', 'label' => '消費税率', 'href' => '/masters/foundation/consumption-tax-rates', 'permission' => 'tax_master.view'],
    ['key' => 'units', 'label' => '単位', 'href' => '/masters/foundation/units', 'permission' => 'unit_master.view'],
    ['key' => 'liquor-tax-categories', 'label' => '酒税区分', 'href' => '/masters/foundation/liquor-tax-categories', 'permission' => 'liquor_tax_master.view'],
    ['key' => 'stock-locations', 'label' => '在庫場所', 'href' => '/masters/foundation/stock-locations', 'permission' => 'stock_location_master.view'],
    ['key' => 'lots', 'label' => 'ロット', 'href' => '/masters/foundation/lots', 'permission' => 'product_master.view'],
    ['key' => 'transaction-categories', 'label' => '取引区分', 'href' => '/masters/foundation/transaction-categories', 'permission' => 'transaction_category_master.view'],
    ['key' => 'settlement-receivable-categories', 'label' => '売掛精算区分', 'href' => '/masters/foundation/settlement-receivable-categories', 'permission' => 'settlement_category_master.view'],
    ['key' => 'number-sequences', 'label' => '採番', 'href' => '/masters/foundation/number-sequences', 'permission' => 'number_sequence_master.view'],
    ['key' => 'roles', 'label' => '権限・ロール', 'href' => '/masters/foundation/roles', 'permission' => 'role_master.view'],
  ];
@endphp
<style>
  .basic-master-tabs{display:flex;gap:6px;margin:0 0 10px;overflow:auto}
  .basic-master-tabs a{white-space:nowrap;min-height:32px;padding:7px 10px;border:1px solid #d7e1ee;border-radius:5px;background:#fff;color:#33516f;text-decoration:none;font-size:11px}
  .basic-master-tabs a.active{background:#0b6ff6;border-color:#0b6ff6;color:#fff;font-weight:800}
</style>
<nav class="basic-master-tabs" aria-label="マスタ">
  @foreach($entries as $entry)
    @if($canManage($entry['permission']))
      <a href="{{ $entry['href'] }}" class="{{ $active === $entry['key'] ? 'active' : '' }}">{{ $entry['label'] }}</a>
    @endif
  @endforeach
</nav>
