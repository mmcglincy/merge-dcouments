#!/usr/bin/env python3
"""Merge two CSV files by the `match` column.

For each row in the first CSV:
- if that row's `match` value exists in the second CSV, write the row from
  the second CSV to the output;
- otherwise, write the row from the first CSV to the output.
"""

from __future__ import annotations

import argparse
import csv
from pathlib import Path


def read_csv(path: Path) -> tuple[list[str], list[dict[str, str]]]:
    with path.open(newline="", encoding="utf-8") as handle:
        reader = csv.DictReader(handle)
        if reader.fieldnames is None:
            raise ValueError(f"{path} is missing a header row")
        rows = list(reader)
    return reader.fieldnames, rows


def build_second_index(
    rows: list[dict[str, str]], match_column: str, source: Path
) -> dict[str, dict[str, str]]:
    index: dict[str, dict[str, str]] = {}

    for row_number, row in enumerate(rows, start=2):
        if match_column not in row:
            raise ValueError(f"{source} does not contain a '{match_column}' column")

        match_value = row[match_column]
        if match_value in index:
            raise ValueError(
                f"{source} contains duplicate '{match_column}' values: {match_value!r} "
                f"(duplicate found on row {row_number})"
            )
        index[match_value] = row

    return index


def merge_rows(
    first_headers: list[str],
    first_rows: list[dict[str, str]],
    second_headers: list[str],
    second_rows: list[dict[str, str]],
    match_column: str,
    second_source: Path,
) -> tuple[list[str], list[dict[str, str]]]:
    if match_column not in first_headers:
        raise ValueError(f"first CSV does not contain a '{match_column}' column")
    if match_column not in second_headers:
        raise ValueError(f"second CSV does not contain a '{match_column}' column")

    second_index = build_second_index(second_rows, match_column, second_source)
    output_headers = first_headers + [
        header for header in second_headers if header not in first_headers
    ]

    output_rows: list[dict[str, str]] = []
    for row in first_rows:
        match_value = row[match_column]
        output_rows.append(second_index.get(match_value, row))

    return output_headers, output_rows


def write_csv(path: Path, headers: list[str], rows: list[dict[str, str]]) -> None:
    with path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=headers)
        writer.writeheader()
        for row in rows:
            writer.writerow({header: row.get(header, "") for header in headers})


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "For each row in the first CSV, write the matching row from the "
            "second CSV if present; otherwise write the first row."
        )
    )
    parser.add_argument("first_csv", type=Path, help="Path to the first CSV file")
    parser.add_argument("second_csv", type=Path, help="Path to the second CSV file")
    parser.add_argument("output_csv", type=Path, help="Path to the output CSV file")
    parser.add_argument(
        "--match-column",
        default="match",
        help="Column name used to match rows between files (default: match)",
    )
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    first_headers, first_rows = read_csv(args.first_csv)
    second_headers, second_rows = read_csv(args.second_csv)
    output_headers, output_rows = merge_rows(
        first_headers,
        first_rows,
        second_headers,
        second_rows,
        args.match_column,
        args.second_csv,
    )
    write_csv(args.output_csv, output_headers, output_rows)


if __name__ == "__main__":
    main()
