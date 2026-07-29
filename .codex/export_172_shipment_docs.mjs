import fs from "node:fs/promises";
import path from "node:path";
import { spawnSync } from "node:child_process";
import { Workbook, SpreadsheetFile } from "@oai/artifact-tool";

const repoRoot = process.cwd();
const outputDir = path.join(repoRoot, "reports");
const outputPath = path.join(outputDir, "shipment_172_docs.xlsx");

const phpCode = String.raw`
$sql = <<<'SQL'
WITH current_counts AS (
    SELECT
        sh.legacy_access_document_number AS doc_no,
        MIN(sh.document_date) AS document_date,
        MIN(sh.customer_id) AS customer_id,
        MIN(c.name) AS customer_name,
        COUNT(*) AS current_lines
    FROM shipment_lines sl
    JOIN shipment_headers sh ON sh.id = sl.shipment_header_id
    LEFT JOIN customers c ON c.id = sh.customer_id
    WHERE sh.legacy_access_document_number IS NOT NULL
      AND sh.document_date <= DATE '2026-06-30'
    GROUP BY sh.legacy_access_document_number
),
source_counts AS (
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
)
SELECT
    c.doc_no,
    c.document_date,
    c.customer_id,
    c.customer_name,
    c.current_lines,
    s.source_lines,
    (c.current_lines - s.source_lines) AS diff
FROM current_counts c
JOIN source_counts s USING (doc_no)
WHERE c.current_lines = s.source_lines * 2
ORDER BY c.doc_no
SQL;

echo json_encode(DB::select($sql), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
`;

const dockerResult = spawnSync(
  "docker",
  ["compose", "exec", "-T", "app", "php", "artisan", "tinker", "--execute", phpCode],
  { encoding: "utf8", maxBuffer: 1024 * 1024 * 20 },
);

if (dockerResult.status !== 0) {
  throw new Error(
    `Failed to query suspicious documents.\nSTDOUT:\n${dockerResult.stdout}\nSTDERR:\n${dockerResult.stderr}`,
  );
}

const raw = dockerResult.stdout.trim();
const rows = raw ? JSON.parse(raw) : [];

await fs.mkdir(outputDir, { recursive: true });

const workbook = Workbook.create();
const sheet = workbook.worksheets.add("SuspiciousDocs");
sheet.showGridLines = false;

const headers = [
  "doc_no",
  "document_date",
  "customer_id",
  "customer_name",
  "current_lines",
  "source_lines",
  "diff",
];

const values = [headers];
for (const row of rows) {
  values.push([
    row.doc_no ?? null,
    row.document_date ? new Date(row.document_date) : null,
    row.customer_id ?? null,
    row.customer_name ?? null,
    row.current_lines ?? null,
    row.source_lines ?? null,
    row.diff ?? null,
  ]);
}

sheet.getRangeByIndexes(0, 0, values.length, headers.length).values = values;
sheet.freezePanes.freezeRows(1);

sheet.getRange("A1:G1").format = {
  fill: "#8B0000",
  font: { color: "#FFFFFF", bold: true },
};
sheet.getRange(`A2:G${values.length}`).format = {
  borders: { insideHorizontal: { style: "thin", color: "#E5E7EB" } },
};
sheet.getRange("B2:B" + values.length).setNumberFormat("yyyy-mm-dd");
sheet.getRange("E2:G" + values.length).setNumberFormat("#,##0");
sheet.getRange("A1:G" + values.length).format.autofitColumns();
sheet.getRange("A1:G" + values.length).format.autofitRows();

const preview = await workbook.render({
  sheetName: "SuspiciousDocs",
  range: `A1:G${Math.min(values.length, 40)}`,
  scale: 1,
  format: "png",
});
await fs.writeFile(path.join(outputDir, "shipment_172_docs.png"), new Uint8Array(await preview.arrayBuffer()));

const xlsx = await SpreadsheetFile.exportXlsx(workbook);
await xlsx.save(outputPath);

console.log(JSON.stringify({ rows: rows.length, outputPath }, null, 2));
