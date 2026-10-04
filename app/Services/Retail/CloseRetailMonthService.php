<?php

namespace App\Services\Retail;

use App\Models\Retail\RetailCompany;
use App\Models\Retail\RetailLedgerDocument;
use App\Models\Retail\RetailLedgerLine;
use App\Models\Retail\RetailMonthlyBalance;
use App\Models\Retail\RetailMonthlyClosing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseRetailMonthService
{
    public function handle(RetailCompany $company, string $period, ?int $closedBy = null, ?string $note = null): RetailMonthlyClosing
    {
        $start = Carbon::parse($period)->startOfMonth();
        $end = $start->copy()->addMonth();
        $previousPeriod = $start->copy()->subMonth()->toDateString();

        return DB::connection('retail')->transaction(function () use ($company, $start, $end, $previousPeriod, $closedBy, $note) {
            $closing = RetailMonthlyClosing::query()->firstOrCreate(
                ['retail_company_id' => $company->id, 'period' => $start->toDateString()],
                ['status' => RetailMonthlyClosing::STATUS_DRAFT]
            );

            if ($closing->status === RetailMonthlyClosing::STATUS_CLOSED) {
                throw ValidationException::withMessages(['period' => ['この月はすでに月次締め済みです。']]);
            }

            $hasEarlierTransactions = RetailLedgerDocument::query()
                ->where('retail_company_id', $company->id)
                ->where('business_date', '<', $start->toDateString())
                ->where('status', 'active')
                ->exists();
            $previousClosing = RetailMonthlyClosing::query()
                ->where('retail_company_id', $company->id)
                ->whereDate('period', $previousPeriod)
                ->where('status', RetailMonthlyClosing::STATUS_CLOSED)
                ->first();

            if ($hasEarlierTransactions && $previousClosing === null) {
                throw ValidationException::withMessages(['period' => ['過去の取引があります。月次締めは古い月から連続して実行してください。']]);
            }

            $previousBalances = $previousClosing === null
                ? collect()
                : RetailMonthlyBalance::query()->where('retail_monthly_closing_id', $previousClosing->id)->get()->keyBy('retail_customer_id');

            $totals = RetailLedgerLine::query()
                ->join('retail_ledger_documents as ledger_documents', 'ledger_documents.id', '=', 'retail_ledger_lines.retail_ledger_document_id')
                ->where('ledger_documents.retail_company_id', $company->id)
                ->where('ledger_documents.status', 'active')
                ->where('ledger_documents.business_date', '>=', $start->toDateString())
                ->where('ledger_documents.business_date', '<', $end->toDateString())
                ->selectRaw("ledger_documents.retail_customer_id,\n                    COALESCE(SUM(CASE WHEN retail_ledger_lines.line_kind = 'opening' THEN retail_ledger_lines.amount ELSE 0 END), 0) as opening_entries,\n                    COALESCE(SUM(CASE WHEN retail_ledger_lines.line_kind = 'sale' THEN retail_ledger_lines.amount ELSE 0 END), 0) as sales_amount,\n                    COALESCE(SUM(CASE WHEN retail_ledger_lines.line_kind = 'payment' THEN retail_ledger_lines.amount ELSE 0 END), 0) as payment_amount,\n                    COALESCE(SUM(CASE WHEN retail_ledger_lines.line_kind = 'adjustment' THEN retail_ledger_lines.amount ELSE 0 END), 0) as adjustment_amount")
                ->groupBy('ledger_documents.retail_customer_id')
                ->get()
                ->keyBy('retail_customer_id');

            $customerIds = $previousBalances->keys()->merge($totals->keys())->unique()->sort()->values();
            $closing->balances()->delete();
            $checksumRows = [];

            foreach ($customerIds as $customerId) {
                $prior = $previousBalances->get($customerId);
                $current = $totals->get($customerId);
                $opening = round((float) ($prior?->closing_amount ?? 0) + (float) ($current?->opening_entries ?? 0), 2);
                $sales = round((float) ($current?->sales_amount ?? 0), 2);
                $payments = round((float) ($current?->payment_amount ?? 0), 2);
                $adjustments = round((float) ($current?->adjustment_amount ?? 0), 2);
                $balance = round($opening + $sales + $payments + $adjustments, 2);

                $closing->balances()->create([
                    'retail_customer_id' => $customerId,
                    'opening_amount' => $opening,
                    'sales_amount' => $sales,
                    'payment_amount' => $payments,
                    'adjustment_amount' => $adjustments,
                    'closing_amount' => $balance,
                ]);
                $checksumRows[] = compact('customerId', 'opening', 'sales', 'payments', 'adjustments', 'balance');
            }

            $documents = RetailLedgerDocument::query()
                ->where('retail_company_id', $company->id)
                ->where('status', 'active')
                ->where('business_date', '>=', $start->toDateString())
                ->where('business_date', '<', $end->toDateString());
            $documentCount = (clone $documents)->count();
            $lineCount = RetailLedgerLine::query()
                ->whereIn('retail_ledger_document_id', $documents->select('id'))
                ->count();

            $closing->forceFill([
                'status' => RetailMonthlyClosing::STATUS_CLOSED,
                'document_count' => $documentCount,
                'line_count' => $lineCount,
                'checksum' => hash('sha256', json_encode($checksumRows, JSON_THROW_ON_ERROR)),
                'closed_at' => now(),
                'closed_by' => $closedBy,
                'note' => $note,
            ])->save();

            return $closing->load('balances.customer');
        });
    }
}
