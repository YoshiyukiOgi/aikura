import csv
import re
import sys
import unicodedata
from collections import defaultdict, deque
from pathlib import Path

import pdfplumber


def normalize_name(value: str) -> str:
    return re.sub(r"[\s　]+", "", unicodedata.normalize("NFKC", value)).removesuffix("様")


def amount(words: list[dict], x_min: float, x_max: float) -> int:
    tokens = [
        word["text"]
        for word in sorted(words, key=lambda item: item["x0"])
        if x_min <= (word["x0"] + word["x1"]) / 2 < x_max
    ]
    value = "".join(tokens).replace("¥", "").replace(",", "")
    match = re.search(r"-?\d+", value)
    return int(match.group()) if match else 0


def main() -> None:
    if len(sys.argv) != 4:
        raise SystemExit("usage: extract_access_invoice_summary.py PDF CUSTOMER_CSV OUTPUT_CSV")

    pdf_path, customer_path, output_path = map(Path, sys.argv[1:])
    with customer_path.open(encoding="utf-8-sig", newline="") as stream:
        customer_rows = list(csv.DictReader(stream))
    customers_by_name: dict[str, deque] = defaultdict(deque)
    for customer in customer_rows:
        customers_by_name[normalize_name(customer["printed_customer_name"])].append(customer)
    rows: list[dict] = []
    seen: set[str] = set()
    with pdfplumber.open(pdf_path) as document:
        for page_index, page in enumerate(document.pages, start=1):
            words = page.extract_words()
            page_labels = [word["text"] for word in words if word["top"] < 50 and word["x0"] > 730]
            if "1ページ" not in page_labels:
                continue
            header_words = [word for word in words if 90 <= word["top"] <= 110]
            name = "".join(
                word["text"]
                for word in sorted(header_words, key=lambda item: item["x0"])
                if word["x0"] < 450
            ).strip().removesuffix("様")
            matches = customers_by_name[normalize_name(name)]
            if not matches:
                raise RuntimeError(f"page {page_index}: customer not found: {name}")
            customer = matches.popleft()
            code = customer["customer_code"]
            if code in seen:
                raise RuntimeError(f"page {page_index}: duplicate first page for {code}: {name}")
            seen.add(code)

            previous = amount(header_words, 485, 545)
            payments = amount(header_words, 545, 595)
            sales = amount(header_words, 595, 650)
            containers = amount(header_words, 650, 710)
            billed = amount(header_words, 710, 820)
            calculated = previous - payments + sales - containers
            rows.append({
                "customer_code": code,
                "printed_customer_name": name,
                "previous_balance": previous,
                "payments": payments,
                "current_sales": sales,
                "container_amount": containers,
                "billed_amount": billed,
                "calculated_amount": calculated,
                "formula_matches": int(calculated == billed),
                "pdf_page": page_index,
            })

    missing = sorted(set(row["customer_code"] for row in customer_rows) - seen)

    output_path.parent.mkdir(parents=True, exist_ok=True)
    with output_path.open("w", encoding="utf-8-sig", newline="") as stream:
        writer = csv.DictWriter(stream, fieldnames=list(rows[0]))
        writer.writeheader()
        writer.writerows(rows)

    print(f"rows={len(rows)}")
    print(f"missing_customer_codes={','.join(missing)}")
    print(f"formula_mismatches={sum(row['formula_matches'] == 0 for row in rows)}")
    print(f"previous_total={sum(row['previous_balance'] for row in rows)}")
    print(f"payments_total={sum(row['payments'] for row in rows)}")
    print(f"sales_total={sum(row['current_sales'] for row in rows)}")
    print(f"container_total={sum(row['container_amount'] for row in rows)}")
    print(f"billed_total={sum(row['billed_amount'] for row in rows)}")
    print(f"output={output_path}")


if __name__ == "__main__":
    main()
