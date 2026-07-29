import fs from "node:fs/promises";
import path from "node:path";
import { spawnSync } from "node:child_process";
import { Workbook, SpreadsheetFile } from "@oai/artifact-tool";

const repoRoot = process.cwd();
const outputDir = path.join(repoRoot, "reports");
const outputPath = path.join(outputDir, "shipment_line_delete_candidates.xlsx");
const groupedOutputPath = path.join(outputDir, "shipment_line_delete_candidates_grouped.xlsx");

const phpCode = String.raw`
$rows = DB::select(<<<'SQL'
WITH current_lines AS (
    SELECT
        sl.id,
        sh.legacy_access_document_number AS doc_no,
        sh.document_date,
        sh.customer_id,
        sh.document_number,
        sl.line_no,
        sl.product_id,
        sl.quantity,
        sl.unit_id,
        sl.confirmed_quantity,
        sl.confirmed_unit_price,
        sl.confirmed_product_code,
        sl.confirmed_product_name,
        sl.confirmed_display_name,
        sl.confirmed_unit_code,
        sl.confirmed_unit_name,
        sl.legacy_access_line_id,
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
    WHERE sh.legacy_access_document_number IS NOT NULL
      AND sh.document_date <= DATE '2026-06-30'
)
SELECT
    id AS delete_candidate_id,
    doc_no,
    document_date,
    customer_id,
    document_number,
    line_no,
    product_id,
    quantity,
    unit_id,
    confirmed_quantity,
    confirmed_unit_price,
    confirmed_product_code,
    confirmed_product_name,
    confirmed_display_name,
    confirmed_unit_code,
    confirmed_unit_name,
    legacy_access_line_id,
    dup_count
FROM current_lines
WHERE dup_count > 1
  AND rn > 1
ORDER BY doc_no, line_no, delete_candidate_id
SQL);

echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
`;

const dockerResult = spawnSync(
  "docker",
  ["compose", "exec", "-T", "app", "php", "artisan", "tinker", "--execute", phpCode],
  { encoding: "utf8", maxBuffer: 1024 * 1024 * 50 }
);

if (dockerResult.status !== 0) {
  throw new Error(
    `Failed to query delete candidates.\nSTDOUT:\n${dockerResult.stdout}\nSTDERR:\n${dockerResult.stderr}`,
  );
}

const raw = dockerResult.stdout.trim();
if (!raw) {
  throw new Error("Query returned no output.");
}

let rows;
try {
  rows = JSON.parse(raw);
} catch (error) {
  throw new Error(`Could not parse JSON from query output.\n${raw.slice(0, 2000)}`);
}

await fs.mkdir(outputDir, { recursive: true });

const workbook = Workbook.create();
const sheet = workbook.worksheets.add("DeleteCandidates");
sheet.showGridLines = false;

const headers = [
  "delete_candidate_id",
  "doc_no",
  "document_date",
  "customer_id",
  "document_number",
  "line_no",
  "product_id",
  "quantity",
  "unit_id",
  "confirmed_quantity",
  "confirmed_unit_price",
  "confirmed_product_code",
  "confirmed_product_name",
  "confirmed_display_name",
  "confirmed_unit_code",
  "confirmed_unit_name",
  "legacy_access_line_id",
  "dup_count",
];

const values = [headers];
for (const row of rows) {
  values.push([
    row.delete_candidate_id ?? null,
    row.doc_no ?? null,
    row.document_date ? new Date(row.document_date) : null,
    row.customer_id ?? null,
    row.document_number ?? null,
    row.line_no ?? null,
    row.product_id ?? null,
    row.quantity ?? null,
    row.unit_id ?? null,
    row.confirmed_quantity ?? null,
    row.confirmed_unit_price ?? null,
    row.confirmed_product_code ?? null,
    row.confirmed_product_name ?? null,
    row.confirmed_display_name ?? null,
    row.confirmed_unit_code ?? null,
    row.confirmed_unit_name ?? null,
    row.legacy_access_line_id ?? null,
    row.dup_count ?? null,
  ]);
}

sheet.getRangeByIndexes(0, 0, values.length, headers.length).values = values;
sheet.freezePanes.freezeRows(1);

const used = sheet.getUsedRange();
used.format.font.name = "Meiryo UI";
used.format.font.size = 10;

sheet.getRange("A1:R1").format = {
  fill: "#1F4E78",
  font: { color: "#FFFFFF", bold: true },
};

sheet.getRange(`A2:R${values.length}`).format = {
  borders: { insideHorizontal: { style: "thin", color: "#E5E7EB" } },
};

sheet.getRange("C2:C" + values.length).setNumberFormat("yyyy-mm-dd");
sheet.getRange("A2:B" + values.length).format.alignment = { horizontal: "left" };
sheet.getRange("D2:E" + values.length).format.alignment = { horizontal: "left" };
sheet.getRange("F2:R" + values.length).format.alignment = { horizontal: "right" };
sheet.getRange("H2:H" + values.length).setNumberFormat("0.0000");
sheet.getRange("J2:J" + values.length).setNumberFormat("0.0000");
sheet.getRange("K2:K" + values.length).setNumberFormat("#,##0.00");
sheet.getRange("R2:R" + values.length).setNumberFormat("0");

sheet.getRange("A1:R" + values.length).format.autofitColumns();
sheet.getRange("A1:R" + values.length).format.autofitRows();

const previewRows = Math.min(values.length, 40);
const preview = await workbook.render({
  sheetName: "DeleteCandidates",
  range: `A1:R${previewRows}`,
  scale: 1,
  format: "png",
});
await fs.writeFile(path.join(outputDir, "shipment_line_delete_candidates.png"), new Uint8Array(await preview.arrayBuffer()));

const xlsx = await SpreadsheetFile.exportXlsx(workbook);
await xlsx.save(outputPath);

const groupedMap = new Map();
for (const row of rows) {
  const key = row.doc_no ?? "";
  const existing = groupedMap.get(key) ?? {
    doc_no: row.doc_no ?? null,
    document_date: row.document_date ?? null,
    customer_id: row.customer_id ?? null,
    document_number: row.document_number ?? null,
    delete_candidate_count: 0,
    line_nos: [],
    delete_candidate_ids: [],
    product_ids: [],
    quantities: [],
    dup_counts: [],
  };
  existing.delete_candidate_count += 1;
  if (row.line_no != null) existing.line_nos.push(String(row.line_no));
  if (row.delete_candidate_id != null) existing.delete_candidate_ids.push(String(row.delete_candidate_id));
  if (row.product_id != null) existing.product_ids.push(String(row.product_id));
  if (row.quantity != null) existing.quantities.push(String(row.quantity));
  if (row.dup_count != null) existing.dup_counts.push(String(row.dup_count));
  groupedMap.set(key, existing);
}

const groupedRows = [...groupedMap.values()].sort((a, b) => {
  const ad = a.doc_no == null ? "" : String(a.doc_no);
  const bd = b.doc_no == null ? "" : String(b.doc_no);
  return ad.localeCompare(bd, "ja");
});

const groupedWorkbook = Workbook.create();
const groupedSheet = groupedWorkbook.worksheets.add("ByDocument");
groupedSheet.showGridLines = false;
const groupedHeaders = [
  "doc_no",
  "document_date",
  "customer_id",
  "document_number",
  "delete_candidate_count",
  "line_nos",
  "delete_candidate_ids",
  "product_ids",
  "quantities",
  "dup_counts",
];
const groupedValues = [groupedHeaders];
for (const row of groupedRows) {
  groupedValues.push([
    row.doc_no,
    row.document_date ? new Date(row.document_date) : null,
    row.customer_id,
    row.document_number,
    row.delete_candidate_count,
    row.line_nos.join(", "),
    row.delete_candidate_ids.join(", "),
    row.product_ids.join(", "),
    row.quantities.join(", "),
    row.dup_counts.join(", "),
  ]);
}
groupedSheet.getRangeByIndexes(0, 0, groupedValues.length, groupedHeaders.length).values = groupedValues;
groupedSheet.freezePanes.freezeRows(1);
groupedSheet.getRange("A1:J1").format = {
  fill: "#7A3E00",
  font: { color: "#FFFFFF", bold: true },
};
groupedSheet.getRange(`A2:J${groupedValues.length}`).format = {
  borders: { insideHorizontal: { style: "thin", color: "#E5E7EB" } },
};
groupedSheet.getRange("B2:B" + groupedValues.length).setNumberFormat("yyyy-mm-dd");
groupedSheet.getRange("A2:E" + groupedValues.length).format.alignment = { horizontal: "left" };
groupedSheet.getRange("E2:E" + groupedValues.length).format.alignment = { horizontal: "right" };
groupedSheet.getRange("F2:J" + groupedValues.length).format.alignment = { horizontal: "left" };
groupedSheet.getRange("A1:J" + groupedValues.length).format.autofitColumns();
groupedSheet.getRange("A1:J" + groupedValues.length).format.autofitRows();

const groupedPreview = await groupedWorkbook.render({
  sheetName: "ByDocument",
  range: `A1:J${Math.min(groupedValues.length, 40)}`,
  scale: 1,
  format: "png",
});
await fs.writeFile(path.join(outputDir, "shipment_line_delete_candidates_grouped.png"), new Uint8Array(await groupedPreview.arrayBuffer()));

const groupedXlsx = await SpreadsheetFile.exportXlsx(groupedWorkbook);
await groupedXlsx.save(groupedOutputPath);

console.log(JSON.stringify({ rows: rows.length, outputPath }, null, 2));
