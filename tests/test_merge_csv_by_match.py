import csv
import tempfile
import unittest
from pathlib import Path

from merge_csv_by_match import merge_rows, read_csv, write_csv


class MergeCsvByMatchTests(unittest.TestCase):
    def test_matching_rows_come_from_second_file(self) -> None:
        with tempfile.TemporaryDirectory() as tempdir:
            temp_path = Path(tempdir)
            first_csv = temp_path / "first.csv"
            second_csv = temp_path / "second.csv"
            output_csv = temp_path / "output.csv"

            first_csv.write_text(
                "match,name,first_only\n"
                "A,Alice,alpha\n"
                "B,Bob,beta\n",
                encoding="utf-8",
            )
            second_csv.write_text(
                "match,name,second_only\n"
                "B,Robert,beta-override\n"
                "C,Carol,gamma\n",
                encoding="utf-8",
            )

            first_headers, first_rows = read_csv(first_csv)
            second_headers, second_rows = read_csv(second_csv)
            output_headers, output_rows = merge_rows(
                first_headers,
                first_rows,
                second_headers,
                second_rows,
                "match",
                second_csv,
            )
            write_csv(output_csv, output_headers, output_rows)

            with output_csv.open(newline="", encoding="utf-8") as handle:
                rows = list(csv.DictReader(handle))

            self.assertEqual(
                rows,
                [
                    {
                        "match": "A",
                        "name": "Alice",
                        "first_only": "alpha",
                        "second_only": "",
                    },
                    {
                        "match": "B",
                        "name": "Robert",
                        "first_only": "",
                        "second_only": "beta-override",
                    },
                ],
            )


if __name__ == "__main__":
    unittest.main()
