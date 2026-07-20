<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ShipmentActionReasonRequest;
use App\Http\Requests\Api\V1\StoreMonthlyClosingRequest;
use App\Models\ReceivableMonthlyBalance;
use App\Models\StockLotMonthlyBalance;
use App\Services\Billing\CloseReceivableMonthlyBalanceService;
use App\Services\Billing\ConfirmReceivableMonthlyBalanceService;
use App\Services\Billing\CreateReceivableMonthlyBalanceDraftService;
use App\Services\Inventory\ConfirmStockMonthlyBalanceService;
use App\Services\Inventory\CreateStockMonthlyBalanceDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MonthlyClosingController extends ApiController
{
    public function stockBalances(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        if (isset($validated['year'], $validated['month'])) {
            return $this->ok(['stock_monthly_balances' => $this->lotBalances((int) $validated['year'], (int) $validated['month'])]);
        }

        $query = StockLotMonthlyBalance::query()
            ->with(['productionLot.capacityUnit', 'stockLocation', 'unit'])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('production_lot_id')
            ->orderBy('stock_location_id')
            ->orderBy('unit_id');

        if (isset($validated['year'])) {
            $query->where('year', (int) $validated['year']);
        }

        if (isset($validated['month'])) {
            $query->where('month', (int) $validated['month']);
        }

        return $this->ok([
            'stock_monthly_balances' => $query
                ->limit(100)
                ->get()
                ->map(fn (StockLotMonthlyBalance $balance): array => $this->serializeLotStockBalance($balance))
                ->values()
                ->all(),
        ]);
    }

    public function createStockBalances(
        StoreMonthlyClosingRequest $request,
        CreateStockMonthlyBalanceDraftService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $balances = $service->create(
            year: (int) $validated['year'],
            month: (int) $validated['month'],
            reason: $validated['reason'] ?? null,
        );

        return $this->created(['stock_monthly_balances' => $this->lotBalances((int) $validated['year'], (int) $validated['month'])]);
    }

    public function confirmStockBalances(
        ShipmentActionReasonRequest $request,
        int $year,
        int $month,
        ConfirmStockMonthlyBalanceService $service,
    ): JsonResponse {
        $balances = $service->confirm($year, $month, $request->validated('reason'));

        return $this->ok(['stock_monthly_balances' => $this->lotBalances($year, $month)]);
    }

    public function receivableBalances(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $query = ReceivableMonthlyBalance::query()
            ->with('customer')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('customer_code');

        if (isset($validated['year'])) {
            $query->where('year', (int) $validated['year']);
        }

        if (isset($validated['month'])) {
            $query->where('month', (int) $validated['month']);
        }

        return $this->ok([
            'receivable_monthly_balances' => $query
                ->limit(100)
                ->get()
                ->map(fn (ReceivableMonthlyBalance $balance): array => $this->serializeReceivableBalance($balance))
                ->values()
                ->all(),
        ]);
    }

    public function createReceivableBalances(
        StoreMonthlyClosingRequest $request,
        CreateReceivableMonthlyBalanceDraftService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $balances = $service->create(
            year: (int) $validated['year'],
            month: (int) $validated['month'],
            reason: $validated['reason'] ?? null,
        );

        return $this->created(['receivable_monthly_balances' => $this->serializeReceivableBalances($balances)]);
    }

    public function confirmReceivableBalances(
        ShipmentActionReasonRequest $request,
        int $year,
        int $month,
        ConfirmReceivableMonthlyBalanceService $service,
    ): JsonResponse {
        $balances = $service->confirm($year, $month, $request->validated('reason'));

        return $this->ok(['receivable_monthly_balances' => $this->serializeReceivableBalances($balances)]);
    }

    public function closeReceivableBalances(
        ShipmentActionReasonRequest $request,
        int $year,
        int $month,
        CloseReceivableMonthlyBalanceService $service,
    ): JsonResponse {
        $balances = $service->close($year, $month, $request->validated('reason'));

        return $this->ok(['receivable_monthly_balances' => $this->serializeReceivableBalances($balances)]);
    }

    private function lotBalances(int $year, int $month): array
    {
        return StockLotMonthlyBalance::query()->with(['productionLot.capacityUnit', 'stockLocation', 'unit'])
            ->where('year', $year)->where('month', $month)->orderBy('production_lot_id')->orderBy('stock_location_id')->get()
            ->map(fn (StockLotMonthlyBalance $balance): array => $this->serializeLotStockBalance($balance))->values()->all();
    }

    private function serializeLotStockBalance(StockLotMonthlyBalance $balance): array
    {
        return [
            'id' => $balance->id, 'status' => $balance->status, 'year' => $balance->year, 'month' => $balance->month,
            'period_start' => $balance->period_start?->toDateString(), 'period_end' => $balance->period_end?->toDateString(),
            'production_lot_id' => $balance->production_lot_id, 'lot_code' => $balance->productionLot?->lot_code,
            'lot_name' => $balance->productionLot?->display_name, 'capacity_value' => $balance->productionLot?->capacity_value,
            'capacity_unit_name' => $balance->productionLot?->capacityUnit?->symbol ?? $balance->productionLot?->capacityUnit?->name,
            'stock_location_id' => $balance->stock_location_id, 'stock_location_name' => $balance->stockLocation?->name,
            'unit_id' => $balance->unit_id, 'unit_name' => $balance->unit?->symbol ?? $balance->unit?->name,
            'opening_quantity' => null, 'inbound_quantity' => null, 'outbound_quantity' => null, 'adjustment_quantity' => null,
            'closing_quantity' => $balance->closing_quantity, 'calculated_at' => $balance->calculated_at?->toISOString(),
            'confirmed_at' => $balance->confirmed_at?->toISOString(),
        ];
    }

    /**
     * @param Collection<int, ReceivableMonthlyBalance> $balances
     * @return array<int, array<string, mixed>>
     */
    private function serializeReceivableBalances(Collection $balances): array
    {
        return $balances
            ->map(fn (ReceivableMonthlyBalance $balance): array => $this->serializeReceivableBalance($balance->loadMissing('customer')))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReceivableBalance(ReceivableMonthlyBalance $balance): array
    {
        return [
            'id' => $balance->id,
            'status' => $balance->status,
            'year' => $balance->year,
            'month' => $balance->month,
            'period_start' => $balance->period_start?->toDateString(),
            'period_end' => $balance->period_end?->toDateString(),
            'customer_id' => $balance->customer_id,
            'customer_code' => $balance->customer_code,
            'customer_name' => $balance->customer_name,
            'scheduled_amount' => $balance->scheduled_amount,
            'received_amount' => $balance->received_amount,
            'outstanding_amount' => $balance->outstanding_amount,
            'open_schedule_count' => $balance->open_schedule_count,
            'partial_schedule_count' => $balance->partial_schedule_count,
            'closed_schedule_count' => $balance->closed_schedule_count,
            'calculated_at' => $balance->calculated_at?->toISOString(),
            'confirmed_at' => $balance->confirmed_at?->toISOString(),
            'closed_at' => $balance->closed_at?->toISOString(),
            'reason' => $balance->reason,
        ];
    }
}
