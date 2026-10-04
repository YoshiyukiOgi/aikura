<?php

namespace App\Services\Retail;

use App\Models\Retail\RetailCompany;
use App\Models\Retail\RetailCustomer;
use App\Models\Retail\RetailImportBatch;
use App\Models\Retail\RetailLedgerLine;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportTamagawaLedgerService
{
    public function __construct(private readonly CreateRetailLedgerDocumentService $createLedgerDocument)
    {
    }

    /** @param array<string, mixed> $payload */
    public function handle(RetailCompany $company, array $payload, ?int $importedBy = null): RetailImportBatch
    {
        $periodFrom = Carbon::parse(Arr::get($payload, 'period_from'))->startOfDay();
        $periodTo = Carbon::parse(Arr::get($payload, 'period_to'))->endOfDay();
        $documents = Arr::get($payload, 'documents', []);
        if (! is_array($documents) || $documents === []) {
            throw ValidationException::withMessages(['documents' => ['取込伝票がありません。']]);
        }

        $hashPayload = $payload;
        unset($hashPayload['source_file_name']);
        $fileHash = hash('sha256', json_encode($hashPayload, JSON_THROW_ON_ERROR));

        return DB::connection('retail')->transaction(function () use ($company, $payload, $periodFrom, $periodTo, $documents, $fileHash, $importedBy) {
            $existing = RetailImportBatch::query()
                ->where('retail_company_id', $company->id)
                ->where('source', 'tamagawa_access')
                ->where('source_file_hash', $fileHash)
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $batch = RetailImportBatch::create([
                'retail_company_id' => $company->id,
                'source' => 'tamagawa_access',
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'source_file_name' => Arr::get($payload, 'source_file_name'),
                'source_file_hash' => $fileHash,
                'status' => 'imported',
                'source_payload' => Arr::except($payload, ['documents']),
                'imported_at' => now(),
                'imported_by' => $importedBy,
            ]);

            $lineCount = 0;
            $salesTotal = 0.0;
            $paymentTotal = 0.0;
            foreach ($documents as $documentInput) {
                $date = Carbon::parse(Arr::get($documentInput, 'business_date'))->startOfDay();
                if ($date->lt($periodFrom) || $date->gt($periodTo)) {
                    throw ValidationException::withMessages(['documents' => ['取込伝票の日付が取込対象期間外です。']]);
                }

                $customer = RetailCustomer::query()->findOrFail((int) Arr::get($documentInput, 'retail_customer_id'));
                $lines = Arr::get($documentInput, 'lines', []);
                foreach ($lines as $line) {
                    $amount = (float) Arr::get($line, 'amount', 0);
                    if ((int) Arr::get($line, 'source_product_id') === -1) {
                        $paymentTotal += $amount;
                    } else {
                        $salesTotal += $amount;
                    }
                    $lineCount++;
                }

                $sourceDocumentId = (string) Arr::get($documentInput, 'source_document_id');
                $this->createLedgerDocument->handle($company, $customer, [
                    'document_no' => (string) Arr::get($documentInput, 'document_no', $sourceDocumentId),
                    'business_date' => $date,
                    'source' => 'tamagawa_access',
                    'source_document_id' => $sourceDocumentId,
                    'source_hash' => hash('sha256', json_encode($documentInput, JSON_THROW_ON_ERROR)),
                    'source_payload' => Arr::except($documentInput, ['lines']),
                ], $lines);
            }

            $batch->forceFill([
                'document_count' => count($documents),
                'line_count' => $lineCount,
                'sales_total' => round($salesTotal, 2),
                'payment_total' => round($paymentTotal, 2),
            ])->save();

            return $batch;
        });
    }
}
