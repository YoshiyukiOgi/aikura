<?php

namespace App\Services\Inventory;

use App\Models\ProductionLot;
use App\Services\Operations\OperationalPeriod;

class OperationalStartStockLotEligibilityService
{
    /** @var array<int, true>|null */
    private ?array $eligibleLotIds = null;

    public function __construct(
        private readonly LotStockBalanceService $lotStockBalanceService,
        private readonly OperationalPeriod $operationalPeriod,
    ) {
    }

    public function isEligibleLot(?ProductionLot $lot): bool
    {
        if ($lot === null) {
            return false;
        }

        return $this->isEligibleLotId($lot->id);
    }

    public function isEligibleLotId(int $lotId): bool
    {
        return isset($this->eligibleLotIdMap()[$lotId]);
    }

    /**
     * @return list<int>
     */
    public function eligibleLotIds(): array
    {
        return array_keys($this->eligibleLotIdMap());
    }

    /**
     * @return array<int, true>
     */
    private function eligibleLotIdMap(): array
    {
        if ($this->eligibleLotIds !== null) {
            return $this->eligibleLotIds;
        }

        $this->eligibleLotIds = [];
        foreach ($this->lotStockBalanceService->allAsOf($this->operationalPeriod->startDate()) as $balance) {
            if (bccomp($balance->physicalQuantity, '0.0000', 4) <= 0) {
                continue;
            }

            $this->eligibleLotIds[$balance->productionLotId] = true;
        }

        return $this->eligibleLotIds;
    }
}
