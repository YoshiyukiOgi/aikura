<?php

namespace App\Services\Tax;

use App\Models\ShipmentHeader;
use App\Models\ShipmentLiquorTaxEvidence;
use App\Models\User;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RecordShipmentLiquorTaxEvidenceService
{
    public function __construct(
        private readonly EnsureLiquorTaxFilingPeriodIsOpenService $ensurePeriodIsOpenService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /** @param array<string, mixed> $data */
    public function record(ShipmentHeader $shipment, User $user, array $data): ShipmentLiquorTaxEvidence
    {
        $storedDocumentPath = null;

        try {
            return DB::transaction(function () use ($shipment, $user, $data, &$storedDocumentPath): ShipmentLiquorTaxEvidence {
                $shipment = ShipmentHeader::query()->lockForUpdate()->findOrFail($shipment->id);
                if ($shipment->status !== 'confirmed') {
                    throw new DomainException('確定済み出荷だけ税務証明を確認できます。');
                }
                if (! in_array($shipment->confirmed_liquor_tax_treatment, ['export_exempt', 'untaxed_transfer'], true)) {
                    throw new DomainException('輸出免税または未納税移出の出荷ではありません。');
                }
                $this->ensurePeriodIsOpenService->ensureOpen(
                    ($shipment->liquor_tax_transfer_date ?? $shipment->document_date)->toDateString(),
                );

                $status = (string) $data['status'];
                $beforeModel = $shipment->liquorTaxEvidence;
                $before = $beforeModel?->toArray();
                $oldDocumentPath = $beforeModel?->document_file_path;
                $uploadedDocument = $data['document'] ?? null;
                $documentAttributes = $this->resolveDocumentAttributes($shipment->id, $uploadedDocument, $beforeModel);
                $storedDocumentPath = $uploadedDocument instanceof UploadedFile
                    ? ($documentAttributes['document_file_path'] ?? null)
                    : null;

                $evidence = ShipmentLiquorTaxEvidence::query()->updateOrCreate(
                    ['shipment_header_id' => $shipment->id],
                    [
                        'tax_treatment' => $shipment->confirmed_liquor_tax_treatment,
                        'status' => $status,
                        'evidence_reference' => $data['evidence_reference'] ?? null,
                        'evidence_date' => $data['evidence_date'] ?? null,
                        'destination' => $data['destination'] ?? null,
                        'customs_office' => $data['customs_office'] ?? null,
                        'exporter_type' => $shipment->confirmed_liquor_tax_treatment === 'export_exempt'
                            ? match ($shipment->confirmed_settlement_receivable_category_code) {
                                'direct_export' => 'direct',
                                'indirect_export' => 'indirect',
                                default => $data['exporter_type'] ?? null,
                            } : null,
                        'note' => $data['note'] ?? null,
                        'confirmed_by' => $status === 'confirmed' ? $user->id : null,
                        'confirmed_at' => $status === 'confirmed' ? now() : null,
                    ] + $documentAttributes,
                );

                if (($documentAttributes['document_file_path'] ?? null) !== null && $oldDocumentPath && $oldDocumentPath !== $documentAttributes['document_file_path']) {
                    DB::afterCommit(static fn (): bool => Storage::disk('local')->delete($oldDocumentPath));
                }

                $this->auditLogService->record(new AuditLogData(
                    event: 'shipment.liquor_tax_evidence_recorded',
                    auditable: $evidence,
                    beforeValues: $before,
                    afterValues: $evidence->toArray(),
                    reason: $data['note'] ?? '税務証明状態を更新',
                ));

                return $evidence->refresh()->load('confirmer');
            });
        } catch (\Throwable $e) {
            if ($storedDocumentPath !== null) {
                Storage::disk('local')->delete($storedDocumentPath);
            }

            throw $e;
        }
    }

    /**
     * @return array<string, int|string|null>
     */
    private function resolveDocumentAttributes(int $shipmentId, mixed $document, ?ShipmentLiquorTaxEvidence $current): array
    {
        if (! $document instanceof UploadedFile) {
            return [
                'document_file_path' => $current?->document_file_path,
                'document_file_name' => $current?->document_file_name,
                'document_file_size' => $current?->document_file_size,
                'document_mime_type' => $current?->document_mime_type,
                'document_checksum_sha256' => $current?->document_checksum_sha256,
            ];
        }

        $extension = $document->getClientOriginalExtension();
        $relativePath = 'tax/shipment-liquor-tax-evidences/'.$shipmentId.'/'.Str::uuid().($extension !== '' ? '.'.$extension : '');
        Storage::disk('local')->putFileAs(
            dirname($relativePath),
            $document,
            basename($relativePath),
        );

        return [
            'document_file_path' => $relativePath,
            'document_file_name' => $document->getClientOriginalName(),
            'document_file_size' => Storage::disk('local')->size($relativePath),
            'document_mime_type' => $document->getClientMimeType() ?: $document->getMimeType(),
            'document_checksum_sha256' => hash_file('sha256', Storage::disk('local')->path($relativePath)),
        ];
    }
}
