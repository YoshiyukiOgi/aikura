<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$sourceCounts = DB::select(<<<'SQL'
    SELECT
        h.payload->>'伝票番号' AS doc_no,
        COUNT(*) AS source_lines
    FROM access_migration_staging_rows l
    JOIN access_migration_staging_rows h
      ON h.batch_id = l.batch_id
     AND h.source_table = '出荷伝票・取引先'
     AND h.source_key = l.payload->>'伝票番号'
    WHERE l.batch_id = 4
      AND l.source_table = '出荷伝票・商品'
      AND CAST(h.payload->>'年月日' AS date) <= DATE '2026-06-30'
    GROUP BY h.payload->>'伝票番号'
SQL);

$currentCounts = DB::select(<<<'SQL'
    SELECT
        sh.legacy_access_document_number AS doc_no,
        COUNT(*) AS current_lines
    FROM shipment_lines sl
    JOIN shipment_headers sh ON sh.id = sl.shipment_header_id
    WHERE sh.legacy_access_document_number IS NOT NULL
      AND sh.document_date <= DATE '2026-06-30'
    GROUP BY sh.legacy_access_document_number
SQL);

$sourceMap = [];
foreach ($sourceCounts as $row) {
    $sourceMap[(string) $row->doc_no] = (int) $row->source_lines;
}

$targetDocs = [];
foreach ($currentCounts as $row) {
    $docNo = (string) $row->doc_no;
    $current = (int) $row->current_lines;
    $source = $sourceMap[$docNo] ?? null;
    if ($source !== null && $current === $source * 2) {
        $targetDocs[] = $docNo;
    }
}

if ($targetDocs === []) {
    fwrite(STDOUT, "No suspicious documents found.\n");
    exit(0);
}

$quotedDocs = implode(', ', array_map(
    static fn (string $docNo): string => DB::getPdo()->quote($docNo),
    $targetDocs,
));

[$deleteCountBefore, $referenceCounts] = [DB::selectOne(<<<SQL
    WITH target_docs AS (
        SELECT unnest(ARRAY[$quotedDocs]::text[]) AS doc_no
    ),
    ranked AS (
        SELECT
            sl.id,
            sh.legacy_access_document_number AS doc_no,
            row_number() OVER (
                PARTITION BY
                    sh.legacy_access_document_number,
                    sl.product_id,
                    sl.quantity,
                    sl.unit_id,
                    COALESCE(sl.confirmed_quantity::text, ''),
                    COALESCE(sl.confirmed_unit_price::text, ''),
                    COALESCE(sl.confirmed_product_code, ''),
                    COALESCE(sl.confirmed_product_name, ''),
                    COALESCE(sl.confirmed_display_name, ''),
                    COALESCE(sl.confirmed_unit_code, ''),
                    COALESCE(sl.confirmed_unit_name, '')
                ORDER BY sl.id
            ) AS rn,
            count(*) OVER (
                PARTITION BY
                    sh.legacy_access_document_number,
                    sl.product_id,
                    sl.quantity,
                    sl.unit_id,
                    COALESCE(sl.confirmed_quantity::text, ''),
                    COALESCE(sl.confirmed_unit_price::text, ''),
                    COALESCE(sl.confirmed_product_code, ''),
                    COALESCE(sl.confirmed_product_name, ''),
                    COALESCE(sl.confirmed_display_name, ''),
                    COALESCE(sl.confirmed_unit_code, ''),
                    COALESCE(sl.confirmed_unit_name, '')
            ) AS dup_count
        FROM shipment_lines sl
        JOIN shipment_headers sh ON sh.id = sl.shipment_header_id
        JOIN target_docs td ON td.doc_no = sh.legacy_access_document_number
        WHERE sh.legacy_access_document_number IS NOT NULL
          AND sh.document_date <= DATE '2026-06-30'
    )
    SELECT COUNT(*) AS c
    FROM ranked
    WHERE dup_count > 1
      AND rn > 1
SQL), DB::selectOne(<<<SQL
    WITH target_docs AS (
        SELECT unnest(ARRAY[$quotedDocs]::text[]) AS doc_no
    ),
    ranked AS (
        SELECT
            sl.id,
            sh.legacy_access_document_number AS doc_no,
            row_number() OVER (
                PARTITION BY
                    sh.legacy_access_document_number,
                    sl.product_id,
                    sl.quantity,
                    sl.unit_id,
                    COALESCE(sl.confirmed_quantity::text, ''),
                    COALESCE(sl.confirmed_unit_price::text, ''),
                    COALESCE(sl.confirmed_product_code, ''),
                    COALESCE(sl.confirmed_product_name, ''),
                    COALESCE(sl.confirmed_display_name, ''),
                    COALESCE(sl.confirmed_unit_code, ''),
                    COALESCE(sl.confirmed_unit_name, '')
                ORDER BY sl.id
            ) AS rn,
            count(*) OVER (
                PARTITION BY
                    sh.legacy_access_document_number,
                    sl.product_id,
                    sl.quantity,
                    sl.unit_id,
                    COALESCE(sl.confirmed_quantity::text, ''),
                    COALESCE(sl.confirmed_unit_price::text, ''),
                    COALESCE(sl.confirmed_product_code, ''),
                    COALESCE(sl.confirmed_product_name, ''),
                    COALESCE(sl.confirmed_display_name, ''),
                    COALESCE(sl.confirmed_unit_code, ''),
                    COALESCE(sl.confirmed_unit_name, '')
            ) AS dup_count
        FROM shipment_lines sl
        JOIN shipment_headers sh ON sh.id = sl.shipment_header_id
        JOIN target_docs td ON td.doc_no = sh.legacy_access_document_number
        WHERE sh.legacy_access_document_number IS NOT NULL
          AND sh.document_date <= DATE '2026-06-30'
    ),
    dups AS (
        SELECT id
        FROM ranked
        WHERE dup_count > 1
          AND rn > 1
    )
    SELECT
        (SELECT COUNT(*) FROM dups) AS duplicate_rows,
        (SELECT COUNT(*) FROM invoice_lines WHERE shipment_line_id IN (SELECT id FROM dups)) AS invoice_lines,
        (SELECT COUNT(*) FROM stock_movements WHERE source_shipment_line_id IN (SELECT id FROM dups)) AS stock_movements,
        (SELECT COUNT(*) FROM sales_return_lines WHERE source_shipment_line_id IN (SELECT id FROM dups)) AS sales_return_lines,
        (SELECT COUNT(*) FROM shipment_lot_allocations WHERE shipment_line_id IN (SELECT id FROM dups)) AS shipment_lot_allocations
SQL)];

DB::transaction(static function () use ($quotedDocs): void {
    $commonCte = <<<SQL
        WITH target_docs AS (
            SELECT unnest(ARRAY[$quotedDocs]::text[]) AS doc_no
        ),
        ranked AS (
            SELECT
                sl.id,
                first_value(sl.id) OVER (
                    PARTITION BY
                        sh.legacy_access_document_number,
                        sl.product_id,
                        sl.quantity,
                        sl.unit_id,
                        COALESCE(sl.confirmed_quantity::text, ''),
                        COALESCE(sl.confirmed_unit_price::text, ''),
                        COALESCE(sl.confirmed_product_code, ''),
                        COALESCE(sl.confirmed_product_name, ''),
                        COALESCE(sl.confirmed_display_name, ''),
                        COALESCE(sl.confirmed_unit_code, ''),
                        COALESCE(sl.confirmed_unit_name, '')
                    ORDER BY sl.id
                ) AS keeper_id,
                row_number() OVER (
                    PARTITION BY
                        sh.legacy_access_document_number,
                        sl.product_id,
                        sl.quantity,
                        sl.unit_id,
                        COALESCE(sl.confirmed_quantity::text, ''),
                        COALESCE(sl.confirmed_unit_price::text, ''),
                        COALESCE(sl.confirmed_product_code, ''),
                        COALESCE(sl.confirmed_product_name, ''),
                        COALESCE(sl.confirmed_display_name, ''),
                        COALESCE(sl.confirmed_unit_code, ''),
                        COALESCE(sl.confirmed_unit_name, '')
                    ORDER BY sl.id
                ) AS rn,
                count(*) OVER (
                    PARTITION BY
                        sh.legacy_access_document_number,
                        sl.product_id,
                        sl.quantity,
                        sl.unit_id,
                        COALESCE(sl.confirmed_quantity::text, ''),
                        COALESCE(sl.confirmed_unit_price::text, ''),
                        COALESCE(sl.confirmed_product_code, ''),
                        COALESCE(sl.confirmed_product_name, ''),
                        COALESCE(sl.confirmed_display_name, ''),
                        COALESCE(sl.confirmed_unit_code, ''),
                        COALESCE(sl.confirmed_unit_name, '')
                ) AS dup_count
            FROM shipment_lines sl
            JOIN shipment_headers sh ON sh.id = sl.shipment_header_id
            JOIN target_docs td ON td.doc_no = sh.legacy_access_document_number
            WHERE sh.legacy_access_document_number IS NOT NULL
              AND sh.document_date <= DATE '2026-06-30'
        ),
        dups AS (
            SELECT id, keeper_id
            FROM ranked
            WHERE dup_count > 1
              AND rn > 1
        )
    SQL;

    foreach ([
        ['invoice_lines', 'shipment_line_id'],
        ['stock_movements', 'source_shipment_line_id'],
        ['sales_return_lines', 'source_shipment_line_id'],
        ['shipment_lot_allocations', 'shipment_line_id'],
    ] as [$table, $column]) {
        DB::statement($commonCte . " UPDATE {$table} t SET {$column} = d.keeper_id FROM dups d WHERE t.{$column} = d.id");
    }

    DB::statement($commonCte . ' DELETE FROM shipment_lines sl USING dups d WHERE sl.id = d.id');
});

$remainingCounts = DB::selectOne(<<<'SQL'
    WITH source_counts AS (
        SELECT
            h.payload->>'伝票番号' AS doc_no,
            COUNT(*) AS source_lines
        FROM access_migration_staging_rows l
        JOIN access_migration_staging_rows h
          ON h.batch_id = l.batch_id
         AND h.source_table = '出荷伝票・取引先'
         AND h.source_key = l.payload->>'伝票番号'
        WHERE l.batch_id = 4
          AND l.source_table = '出荷伝票・商品'
          AND CAST(h.payload->>'年月日' AS date) <= DATE '2026-06-30'
        GROUP BY h.payload->>'伝票番号'
    ),
    current_counts AS (
        SELECT
            sh.legacy_access_document_number AS doc_no,
            COUNT(*) AS current_lines
        FROM shipment_lines sl
        JOIN shipment_headers sh ON sh.id = sl.shipment_header_id
        WHERE sh.legacy_access_document_number IS NOT NULL
          AND sh.document_date <= DATE '2026-06-30'
        GROUP BY sh.legacy_access_document_number
    )
    SELECT COUNT(*) AS c
    FROM current_counts c
    JOIN source_counts s USING (doc_no)
    WHERE c.current_lines <> s.source_lines
SQL);

echo json_encode([
    'target_docs' => count($targetDocs),
    'delete_rows' => (int) $deleteCountBefore->c,
    'invoice_lines' => (int) $referenceCounts->invoice_lines,
    'stock_movements' => (int) $referenceCounts->stock_movements,
    'sales_return_lines' => (int) $referenceCounts->sales_return_lines,
    'shipment_lot_allocations' => (int) $referenceCounts->shipment_lot_allocations,
    'remaining_mismatched_docs' => (int) $remainingCounts->c,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
