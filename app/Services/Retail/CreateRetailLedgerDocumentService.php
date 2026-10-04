<?php

namespace App\Services\Retail;

use App\Models\Retail\RetailCompany;
use App\Models\Retail\RetailCustomer;
use App\Models\Retail\RetailLedgerDocument;
use App\Models\Retail\RetailLedgerLine;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRetailLedgerDocumentService
{
    public function __construct(private readonly EnsureRetailPeriodOpenService $ensurePeriodOpen)
    {
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<int, array<string, mixed>> $lines
     */
    public function handle(RetailCompany $company, RetailCustomer $customer, array $attributes, array $lines): RetailLedgerDocument
    {
        if ((int) $customer->retail_company_id !== (int) $company->id) {
            throw ValidationException::withMessages(['retail_customer_id' => ['取引先が小売会社に属していません。']]);
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => ['明細を1件以上入力してください。']]);
        }

        $businessDate = Carbon::parse(Arr::get($attributes, 'business_date'))->startOfDay();
        $source = (string) Arr::get($attributes, 'source', 'manual');
        $sourceDocumentId = Arr::get($attributes, 'source_document_id');
        $sourceHash = Arr::get($attributes, 'source_hash');

        return DB::connection('retail')->transaction(function () use ($company, $customer, $attributes, $lines, $businessDate, $source, $sourceDocumentId, $sourceHash) {
            if ($sourceDocumentId !== null) {
                $existing = RetailLedgerDocument::query()
                    ->where('retail_company_id', $company->id)
                    ->where('source', $source)
                    ->where('source_document_id', (string) $sourceDocumentId)
                    ->first();

                if ($existing !== null) {
                    if ($sourceHash !== null && $existing->source_hash !== null && ! hash_equals($existing->source_hash, (string) $sourceHash)) {
                        throw ValidationException::withMessages(['source_document_id' => ['同じ取込元伝票IDに異なる内容が指定されています。']]);
                    }

                    return $existing->load('lines');
                }
            }

            $this->ensurePeriodOpen->handle((int) $company->id, $businessDate);

            $document = RetailLedgerDocument::create([
                'retail_company_id' => $company->id,
                'retail_customer_id' => $customer->id,
                'document_no' => (string) Arr::get($attributes, 'document_no'),
                'business_date' => $businessDate,
                'source' => $source,
                'source_document_id' => $sourceDocumentId,
                'source_hash' => $sourceHash,
                'status' => 'active',
                'source_payload' => Arr::get($attributes, 'source_payload'),
            ]);

            foreach (array_values($lines) as $index => $line) {
                $sourceProductId = Arr::get($line, 'source_product_id');
                $kind = (string) Arr::get($line, 'line_kind', RetailLedgerLine::KIND_SALE);

                // 玉川Accessの -1 は、物理的な商品ではなく入金を表す業務コードである。
                if ((int) $sourceProductId === -1) {
                    $kind = RetailLedgerLine::KIND_PAYMENT;
                }

                if (! in_array($kind, [
                    RetailLedgerLine::KIND_SALE,
                    RetailLedgerLine::KIND_PAYMENT,
                    RetailLedgerLine::KIND_ADJUSTMENT,
                    RetailLedgerLine::KIND_OPENING,
                ], true)) {
                    throw ValidationException::withMessages(["lines.$index.line_kind" => ['明細区分が不正です。']]);
                }

                RetailLedgerLine::create([
                    'retail_ledger_document_id' => $document->id,
                    'line_no' => $index + 1,
                    'line_kind' => $kind,
                    'retail_product_id' => $kind === RetailLedgerLine::KIND_PAYMENT ? null : Arr::get($line, 'retail_product_id'),
                    'source_product_id' => $kind === RetailLedgerLine::KIND_PAYMENT ? -1 : $sourceProductId,
                    'description' => Arr::get($line, 'description'),
                    'quantity' => Arr::get($line, 'quantity'),
                    'unit_price' => Arr::get($line, 'unit_price'),
                    'amount' => Arr::get($line, 'amount'),
                    'source_line_id' => Arr::get($line, 'source_line_id'),
                    'source_payload' => Arr::get($line, 'source_payload'),
                ]);
            }

            return $document->load('lines');
        });
    }
}
