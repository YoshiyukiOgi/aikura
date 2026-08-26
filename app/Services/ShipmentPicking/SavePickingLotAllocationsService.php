<?php

namespace App\Services\ShipmentPicking;

use App\Models\ProductionLot;
use App\Models\ShipmentHeader;
use App\Models\ShipmentInstructionLine;
use App\Models\ShipmentLine;
use App\Models\ShipmentLotAllocation;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Inventory\EvaluateLotAlcoholCompatibilityService;
use App\Services\Shipment\AllocateShipmentLineLotService;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SavePickingLotAllocationsService
{
    public function __construct(
        private readonly EvaluateLotAlcoholCompatibilityService $compatibility,
        private readonly AllocateShipmentLineLotService $allocateService,
    ) {}

    /**
     * @param Collection<int, array{production_lot_id:int,quantity:string}> $rows
     * @return Collection<int, ShipmentLotAllocation>
     */
    public function save(ShipmentHeader $shipment, ShipmentInstructionLine $instructionLine, StockLocation $location, Collection $rows, string $reason, User $requester): Collection
    {
        return DB::transaction(function () use ($shipment, $instructionLine, $location, $rows, $reason, $requester): Collection {
            $shipment = ShipmentHeader::query()->lockForUpdate()->findOrFail($shipment->id);
            if ($shipment->status !== 'draft') {
                throw new DomainException('ピッキング時のロット割当は下書き出荷だけ変更できます。');
            }

            $lines = ShipmentLine::query()
                ->where('shipment_header_id', $shipment->id)
                ->where(function ($query) use ($instructionLine): void {
                    $query->where('shipment_instruction_line_id', $instructionLine->id)
                        ->orWhere(function ($query) use ($instructionLine): void {
                            $query->whereNull('shipment_instruction_line_id')
                                ->where('line_no', $instructionLine->line_no)
                                ->where('product_id', $instructionLine->product_id);
                        });
                })
                ->orderBy('line_no')->lockForUpdate()->get();
            $primary = $lines->first();
            if (! $primary) {
                throw new DomainException('この出荷指示明細に対応する下書き出荷明細が見つかりません。');
            }
            $primary->update(['shipment_instruction_line_id' => $instructionLine->id]);

            $lineIds = $lines->pluck('id');
            ShipmentLotAllocation::query()->whereIn('shipment_line_id', $lineIds)->where('status', 'allocated')->delete();
            ShipmentLine::query()->whereIn('id', $lineIds->skip(1))->delete();

            $groups = [];
            foreach ($rows as $row) {
                $lot = ProductionLot::query()->findOrFail($row['production_lot_id']);
                $result = $this->compatibility->evaluate($instructionLine->product, $lot);
                $key = $result['status'] === 'out_of_range' ? 'exception-'.$lot->id : 'normal';
                $groups[$key][] = ['lot' => $lot, 'quantity' => $row['quantity']];
            }

            $createdLines = collect();
            $nextLineNo = (int) ShipmentLine::query()->where('shipment_header_id', $shipment->id)->max('line_no');
            foreach (array_values($groups) as $index => $group) {
                $quantity = collect($group)->reduce(fn (string $sum, array $item): string => bcadd($sum, $item['quantity'], 4), '0.0000');
                $line = $index === 0 ? $primary : $shipment->lines()->create([
                    'line_no' => ++$nextLineNo,
                    'product_id' => $instructionLine->product_id,
                    'quantity' => $quantity,
                    'unit_id' => $instructionLine->unit_id,
                    'shipment_instruction_line_id' => $instructionLine->id,
                    'note' => 'アルコール範囲外のためピッキング時に明細分割',
                ]);
                $line->update(['quantity' => $quantity, 'shipment_instruction_line_id' => $instructionLine->id]);
                $createdLines->push($line);

                foreach ($group as $item) {
                    $this->allocateService->allocate(
                        shipmentLine: $line,
                        productionLot: $item['lot'],
                        stockLocation: $location,
                        quantity: $item['quantity'],
                        reason: $reason,
                        requester: $requester,
                    );
                }
            }

            if ($groups === []) {
                $primary->update(['quantity' => $instructionLine->quantity]);
            }

            return ShipmentLotAllocation::query()
                ->with(['productionLot', 'stockLocation', 'unit', 'approvalRequest'])
                ->whereIn('shipment_line_id', $createdLines->pluck('id')->push($primary->id)->unique())
                ->where('status', 'allocated')->orderBy('id')->get();
        });
    }
}
