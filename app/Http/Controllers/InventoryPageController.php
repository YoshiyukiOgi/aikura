<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\ProductionLot;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\Authorization\AuthorizationService;
use App\Services\Inventory\OperationalStartStockLotEligibilityService;
use App\Services\Operations\OperationalPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryPageController extends Controller
{
    public function index(Request $request, AuthorizationService $auth, OperationalStartStockLotEligibilityService $lotEligibility): View
    {
        $user = $request->user();
        $locations = StockLocation::query()->where('is_active', true)->where('is_inventory_managed', true)->orderBy('sort_order')->orderBy('code')->get();
        $eligibleLotIds = $lotEligibility->eligibleLotIds();
        $lots = ProductionLot::query()
            ->with(['stockLocation', 'unit'])
            ->where(function ($query) use ($eligibleLotIds): void {
                $query->where('is_active', true);
                if ($eligibleLotIds !== []) {
                    $query->orWhereIn('id', $eligibleLotIds);
                }
            })
            ->orderByDesc('production_date')
            ->orderBy('lot_code')
            ->get();
        $inventorySettings = AppSetting::values(['hide_zero_stock_lots' => '1']);

        return view('inventory.index', [
            'user' => $user,
            'canAdjust' => $auth->can($user, 'stock.adjust'),
            'canClose' => $auth->can($user, 'monthly_closing.execute'),
            'canCreateNonSales' => $auth->can($user, 'non_sales_stock.create'),
            'today' => now()->toDateString(),
            'showZeroStockLotsDefault' => $inventorySettings['hide_zero_stock_lots'] !== '1',
            'locationOptions' => $locations->map(fn (StockLocation $location): array => ['id' => $location->id, 'code' => $location->code, 'name' => $location->name])->values()->all(),
            'lotOptions' => $lots->map(fn (ProductionLot $lot): array => [
                'id' => $lot->id,
                'stock_location_id' => $lot->stock_location_id,
                'stock_location_name' => $lot->stockLocation?->name,
                'unit_id' => $lot->unit_id,
                'unit_name' => $lot->unit?->symbol ?: $lot->unit?->name,
                'code' => $lot->lot_code,
                'name' => $lot->display_name,
            ])->values()->all(),
        ]);
    }

    public function lotStockAsOf(Request $request): View
    {
        $inventorySettings = AppSetting::values(['hide_zero_stock_lots' => '1']);

        return view('inventory.lot-stock-as-of', [
            'user' => $request->user(),
            'today' => now()->toDateString(),
            'asOfDate' => now()->toDateString(),
            'keyword' => '',
            'productType' => 'sake',
            'printMode' => false,
            'showZeroStockLots' => $inventorySettings['hide_zero_stock_lots'] !== '1',
        ]);
    }

    public function printLotStockAsOf(Request $request): View
    {
        $validated = $request->validate([
            'as_of_date' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:160'],
            'include_zero_stock' => ['nullable', 'boolean'],
            'product_type' => ['nullable', 'in:sake,kasu,food,goods'],
        ]);

        return view('inventory.lot-stock-as-of', [
            'user' => $request->user(),
            'today' => now()->toDateString(),
            'asOfDate' => $validated['as_of_date'] ?? now()->toDateString(),
            'keyword' => $validated['q'] ?? '',
            'productType' => $validated['product_type'] ?? '',
            'printMode' => true,
            'showZeroStockLots' => (bool) ($validated['include_zero_stock'] ?? false),
        ]);
    }

    public function printMovements(Request $request, OperationalPeriod $operationalPeriod): View
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'movement_type' => ['nullable', 'string', 'max:80'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $year = (int) ($validated['year'] ?? now()->year);
        $month = (int) ($validated['month'] ?? now()->month);
        $movementType = $validated['movement_type'] ?? null;
        $search = trim((string) ($validated['q'] ?? ''));

        $query = StockMovement::query()
            ->with(['stockLocation', 'unit', 'productionLot'])
            ->whereYear('movement_date', $year)
            ->whereMonth('movement_date', $month)
            ->orderBy('movement_date')
            ->orderBy('id');
        if ($movementType) {
            $query->where('movement_type', $movementType);
        }
        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('source_document_number', 'like', '%'.$search.'%')
                    ->orWhere('lot_code', 'like', '%'.$search.'%')
                    ->orWhereHas('productionLot', function ($lotQuery) use ($search): void {
                        $lotQuery->where('lot_code', 'like', '%'.$search.'%')
                            ->orWhere('display_name', 'like', '%'.$search.'%');
                    });
            });
        }

        return view('inventory.movement-print', [
            'year' => $year,
            'month' => $month,
            'movementType' => $movementType,
            'search' => $search,
            'movements' => $query->get(),
        ]);
    }
}
