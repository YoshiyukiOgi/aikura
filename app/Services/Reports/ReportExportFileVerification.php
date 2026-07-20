<?php

namespace App\Services\Reports;

class ReportExportFileVerification
{
    public function __construct(
        public readonly bool $verified,
        public readonly int $fileSize,
        public readonly string $checksumSha256,
    ) {
    }
}
