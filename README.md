# merge-dcouments

## CSV merge tool

Use `merge_csv_by_match.py` to combine two CSV files based on a `match` column.

```bash
python merge_csv_by_match.py first.csv second.csv output.csv
```

For each row in `first.csv`, the script writes:

- the row from `second.csv` when the `match` value exists there;
- otherwise the original row from `first.csv`.
