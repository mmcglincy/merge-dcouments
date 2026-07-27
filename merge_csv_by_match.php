<?php

declare(strict_types=1);

/**
 * Merge two CSV files by the "match" column.
 *
 * For each row in the first CSV:
 * - if that row's "match" value exists in the second CSV, write the row from
 *   the second CSV to the output;
 * - otherwise, write the row from the first CSV to the output.
 */

function readCsv(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException("Unable to open CSV file: {$path}");
    }

    $headers = fgetcsv($handle);
    if ($headers === false) {
        fclose($handle);
        throw new RuntimeException("{$path} is missing a header row");
    }

    $rows = [];
    while (($values = fgetcsv($handle)) !== false) {
        if ($values === [null] || $values === []) {
            continue;
        }

        $row = [];
        foreach ($headers as $index => $header) {
            $row[$header] = $values[$index] ?? '';
        }
        $rows[] = $row;
    }

    fclose($handle);

    return [$headers, $rows];
}

function buildSecondIndex(array $rows, string $matchColumn, string $sourcePath): array
{
    $index = [];

    foreach ($rows as $rowOffset => $row) {
        if (!array_key_exists($matchColumn, $row)) {
            throw new RuntimeException(
                "{$sourcePath} does not contain a '{$matchColumn}' column"
            );
        }

        $matchValue = $row[$matchColumn];
        if (array_key_exists($matchValue, $index)) {
            $rowNumber = $rowOffset + 2;
            throw new RuntimeException(
                "{$sourcePath} contains duplicate '{$matchColumn}' values: '{$matchValue}' " .
                "(duplicate found on row {$rowNumber})"
            );
        }

        $index[$matchValue] = $row;
    }

    return $index;
}

function mergeRows(
    array $firstHeaders,
    array $firstRows,
    array $secondHeaders,
    array $secondRows,
    string $matchColumn,
    string $secondSourcePath
): array {
    if (!in_array($matchColumn, $firstHeaders, true)) {
        throw new RuntimeException("first CSV does not contain a '{$matchColumn}' column");
    }
    if (!in_array($matchColumn, $secondHeaders, true)) {
        throw new RuntimeException("second CSV does not contain a '{$matchColumn}' column");
    }

    $secondIndex = buildSecondIndex($secondRows, $matchColumn, $secondSourcePath);
    $outputHeaders = $firstHeaders;
    foreach ($secondHeaders as $header) {
        if (!in_array($header, $outputHeaders, true)) {
            $outputHeaders[] = $header;
        }
    }

    $outputRows = [];
    foreach ($firstRows as $row) {
        $matchValue = $row[$matchColumn];
        $outputRows[] = $secondIndex[$matchValue] ?? $row;
    }

    return [$outputHeaders, $outputRows];
}

function writeCsv(string $path, array $headers, array $rows): void
{
    $handle = fopen($path, 'wb');
    if ($handle === false) {
        throw new RuntimeException("Unable to write CSV file: {$path}");
    }

    fputcsv($handle, $headers);
    foreach ($rows as $row) {
        $orderedRow = [];
        foreach ($headers as $header) {
            $orderedRow[] = $row[$header] ?? '';
        }
        fputcsv($handle, $orderedRow);
    }

    fclose($handle);
}

function printUsageAndExit(): never
{
    fwrite(
        STDERR,
        "Usage: php merge_csv_by_match.php first.csv second.csv output.csv [match-column]\n"
    );
    exit(1);
}

function main(array $argv): void
{
    if (count($argv) < 4 || count($argv) > 5) {
        printUsageAndExit();
    }

    $firstCsv = $argv[1];
    $secondCsv = $argv[2];
    $outputCsv = $argv[3];
    $matchColumn = $argv[4] ?? 'match';

    [$firstHeaders, $firstRows] = readCsv($firstCsv);
    [$secondHeaders, $secondRows] = readCsv($secondCsv);
    [$outputHeaders, $outputRows] = mergeRows(
        $firstHeaders,
        $firstRows,
        $secondHeaders,
        $secondRows,
        $matchColumn,
        $secondCsv
    );

    writeCsv($outputCsv, $outputHeaders, $outputRows);
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    try {
        main($argv);
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . PHP_EOL);
        exit(1);
    }
}
