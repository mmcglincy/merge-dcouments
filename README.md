# merge-dcouments

## CSV merge tool

Use `merge_csv_by_match.php` to combine two CSV files based on a `match` column.

```bash
php merge_csv_by_match.php first.csv second.csv output.csv
```

For each row in `first.csv`, the script writes:

- the row from `second.csv` when the `match` value exists there;
- otherwise the original row from `first.csv`.
