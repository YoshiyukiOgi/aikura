BEGIN;

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
),
target_docs AS (
    SELECT c.doc_no
    FROM current_counts c
    JOIN source_counts s USING (doc_no)
    WHERE c.current_lines = s.source_lines * 2
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
deleted AS (
    DELETE FROM shipment_lines
    WHERE id IN (
        SELECT id
        FROM ranked
        WHERE dup_count > 1
          AND rn > 1
    )
    RETURNING id
)
SELECT
    (SELECT COUNT(*) FROM target_docs) AS suspicious_docs,
    (SELECT COUNT(*) FROM ranked WHERE dup_count > 1 AND rn > 1) AS deleted_rows,
    (SELECT COUNT(*) FROM deleted) AS deleted_rows_confirmed;

COMMIT;
