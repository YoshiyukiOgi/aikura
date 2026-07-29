<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\ReceivableMonthlyBalanceDraftException;
use App\Models\OpeningReceivableBalance;
use App\Models\PaymentAllocation;
use App\Models\PaymentSchedule;
use App\Models\ReceivableMonthlyBalance;
use App\Services\Operations\OperationalPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateReceivableMonthlyBalanceDraftService
{
    public function __construct(private readonly OperationalPeriod $operationalPeriod) {}

    /**
     * @return Collection<int, ReceivableMonthlyBalance>
     */
    public function create(int $year, int $month, ?string $reason = null): Collection
    {
        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->endOfMonth();

        return DB::transaction(function () use ($year, $month, $periodStart, $periodEnd, $reason): Collection {
            $existing = ReceivableMonthlyBalance::query()
                ->where('year', $year)
                ->where('month', $month)
                ->lockForUpdate()
                ->get();

            if ($existing->contains(fn (ReceivableMonthlyBalance $balance): bool => $balance->status !== 'draft')) {
                throw ReceivableMonthlyBalanceDraftException::alreadyConfirmed($year, $month);
            }

            $schedules = PaymentSchedule::query()
                ->with(['customer', 'invoiceHeader'])
                ->whereHas('invoiceHeader', function ($query) use ($periodEnd): void {
                    $query
                        ->whereNotIn('status', ['draft', 'cancelled'])
                        ->whereNull('cancelled_at')
                        ->whereDate('invoice_date', '>=', $this->operationalPeriod->startDate())
                        ->whereDate('invoice_date', '<=', $periodEnd->toDateString());
                })
                ->orderBy('customer_id')
                ->orderBy('id')
                ->get();

            $receivedBySchedule = $this->receivedAmountsBySchedule($periodEnd->toDateString());
            $rows = [];

            OpeningReceivableBalance::query()
                ->with('customer')
                ->whereIn('status', ['calculated', 'reconciled'])
                ->where('opening_balance_amount', '!=', 0)
                ->whereDate('as_of_date', '>=', $this->operationalPeriod->startDate())
                ->whereDate('as_of_date', '<=', $periodEnd->toDateString())
                ->orderByDesc('as_of_date')
                ->orderByDesc('id')
                ->get()
                ->unique('customer_id')
                ->each(function (OpeningReceivableBalance $opening) use (&$rows, $schedules): void {
                    $isCarriedByInvoice = $schedules->contains(function (PaymentSchedule $schedule) use ($opening): bool {
                        return (int) $schedule->customer_id === (int) $opening->customer_id
                            && $schedule->invoiceHeader !== null
                            && $schedule->invoiceHeader->invoice_date !== null
                            && $schedule->invoiceHeader->invoice_date->toDateString() >= $opening->as_of_date->toDateString();
                    });

                    if ($isCarriedByInvoice) {
                        return;
                    }

                    $amount = bcadd((string) $opening->opening_balance_amount, '0', 2);
                    $rows[$opening->customer_id] = [
                        'customer_id' => (int) $opening->customer_id,
                        'customer_code' => $opening->customer->customer_code,
                        'customer_name' => $opening->customer->name,
                        'scheduled_amount' => $amount,
                        'received_amount' => '0.00',
                        'outstanding_amount' => $amount,
                        'open_schedule_count' => bccomp($amount, '0.00', 2) > 0 ? 1 : 0,
                        'partial_schedule_count' => 0,
                        'closed_schedule_count' => 0,
                    ];
                });

            foreach ($schedules as $schedule) {
                $customerId = (int) $schedule->customer_id;
                $received = $receivedBySchedule[$schedule->id] ?? '0.00';
                $scheduled = bcadd((string) $schedule->scheduled_amount, '0', 2);
                $outstanding = bcsub($scheduled, $received, 2);

                if (! isset($rows[$customerId])) {
                    $rows[$customerId] = [
                        'customer_id' => $customerId,
                        'customer_code' => $schedule->customer->customer_code,
                        'customer_name' => $schedule->customer->name,
                        'scheduled_amount' => '0.00',
                        'received_amount' => '0.00',
                        'outstanding_amount' => '0.00',
                        'open_schedule_count' => 0,
                        'partial_schedule_count' => 0,
                        'closed_schedule_count' => 0,
                    ];
                }

                $rows[$customerId]['scheduled_amount'] = bcadd($rows[$customerId]['scheduled_amount'], $scheduled, 2);
                $rows[$customerId]['received_amount'] = bcadd($rows[$customerId]['received_amount'], $received, 2);
                $rows[$customerId]['outstanding_amount'] = bcadd($rows[$customerId]['outstanding_amount'], $outstanding, 2);

                if (bccomp($outstanding, '0.00', 2) === 0) {
                    $rows[$customerId]['closed_schedule_count']++;
                } elseif (bccomp($received, '0.00', 2) === 1) {
                    $rows[$customerId]['partial_schedule_count']++;
                } else {
                    $rows[$customerId]['open_schedule_count']++;
                }
            }

            ReceivableMonthlyBalance::query()
                ->where('year', $year)
                ->where('month', $month)
                ->where('status', 'draft')
                ->when(
                    count($rows) > 0,
                    fn ($query) => $query->whereNotIn('customer_id', array_keys($rows)),
                )
                ->delete();

            $balances = collect();
            foreach ($rows as $row) {
                $balances->push(ReceivableMonthlyBalance::updateOrCreate(
                    [
                        'year' => $year,
                        'month' => $month,
                        'customer_id' => $row['customer_id'],
                    ],
                    [
                        'status' => 'draft',
                        'period_start' => $periodStart->toDateString(),
                        'period_end' => $periodEnd->toDateString(),
                        'customer_code' => $row['customer_code'],
                        'customer_name' => $row['customer_name'],
                        'scheduled_amount' => $row['scheduled_amount'],
                        'received_amount' => $row['received_amount'],
                        'outstanding_amount' => $row['outstanding_amount'],
                        'open_schedule_count' => $row['open_schedule_count'],
                        'partial_schedule_count' => $row['partial_schedule_count'],
                        'closed_schedule_count' => $row['closed_schedule_count'],
                        'calculated_at' => now(),
                        'confirmed_at' => null,
                        'closed_at' => null,
                        'reason' => $reason,
                    ],
                )->refresh());
            }

            return $balances->sortBy('customer_code')->values();
        });
    }

    /**
     * @return array<int, string>
     */
    private function receivedAmountsBySchedule(string $periodEnd): array
    {
        return PaymentAllocation::query()
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payments.status', '!=', 'cancelled')
            ->whereNull('payments.cancelled_at')
            ->whereDate('payments.payment_date', '<=', $periodEnd)
            ->groupBy('payment_allocations.payment_schedule_id')
            ->select('payment_allocations.payment_schedule_id')
            ->selectRaw('COALESCE(SUM(payment_allocations.allocated_amount), 0) as received_amount')
            ->get()
            ->mapWithKeys(function (object $row): array {
                return [(int) $row->payment_schedule_id => bcadd((string) $row->received_amount, '0', 2)];
            })
            ->all();
    }
}
