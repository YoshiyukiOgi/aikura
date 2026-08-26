<?php

namespace App\Exceptions\Reports;

use DomainException;

class ReportExportRetentionException extends DomainException
{
    public static function unsupportedDisk(string $disk): self
    {
        return new self("未対応の帳票保存先です: {$disk}");
    }

    public static function missingFile(string $path): self
    {
        return new self("帳票出力ファイルが見つかりません: {$path}");
    }

    public static function fileSizeMismatch(string $path): self
    {
        return new self("帳票出力ファイルのサイズが履歴と一致しません: {$path}");
    }

    public static function checksumMismatch(string $path): self
    {
        return new self("帳票出力ファイルのチェックサムが履歴と一致しません: {$path}");
    }
}
