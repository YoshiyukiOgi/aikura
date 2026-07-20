<?php

namespace App\Services\Api;

class ApiRouteCatalogService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalog(): array
    {
        return [
            [
                'module' => 'sales_orders',
                'label' => 'sales order',
                'planned_routes' => ['index', 'show', 'store', 'cancel'],
                'stage' => '8-3',
            ],
            [
                'module' => 'shipment_instructions',
                'label' => 'shipment instruction',
                'planned_routes' => ['index', 'show', 'store', 'cancel'],
                'stage' => '8-4',
            ],
            [
                'module' => 'shipment_picks',
                'label' => 'shipment pick',
                'planned_routes' => ['index', 'show', 'store', 'cancel'],
                'stage' => '8-5',
            ],
            [
                'module' => 'shipments',
                'label' => 'shipment',
                'planned_routes' => ['index', 'show', 'store', 'price', 'confirm', 'cancel'],
                'stage' => '8-6',
            ],
            [
                'module' => 'inventory',
                'label' => 'inventory and lots',
                'planned_routes' => ['stock', 'lot-stock', 'reserve', 'allocate'],
                'stage' => '8-7',
            ],
            [
                'module' => 'billing',
                'label' => 'invoice and payment',
                'planned_routes' => ['invoices', 'payments', 'allocations'],
                'stage' => '8-8',
            ],
            [
                'module' => 'monthly',
                'label' => 'tax and monthly closing',
                'planned_routes' => ['liquor-tax', 'consumption-tax', 'receivables', 'stock'],
                'stage' => '8-9',
            ],
        ];
    }
}
