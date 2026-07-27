<?php

declare(strict_types=1);

require_once __DIR__ . '/../merge_csv_by_match.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            $message . PHP_EOL .
            'Expected: ' . var_export($expected, true) . PHP_EOL .
            'Actual:   ' . var_export($actual, true) . PHP_EOL
        );
        exit(1);
    }
}

function makeTempDir(): string
{
    $tempDir = sys_get_temp_dir() . '/merge-csv-by-match-' . bin2hex(random_bytes(8));
    if (!mkdir($tempDir) && !is_dir($tempDir)) {
        fwrite(STDERR, "Unable to create temp directory\n");
        exit(1);
    }

    return $tempDir;
}

function runMergeCase(string $firstCsvContents, string $secondCsvContents): array
{
    $tempDir = makeTempDir();
    $firstCsv = $tempDir . '/first.csv';
    $secondCsv = $tempDir . '/second.csv';
    $outputCsv = $tempDir . '/output.csv';

    file_put_contents($firstCsv, $firstCsvContents);
    file_put_contents($secondCsv, $secondCsvContents);

    [$firstHeaders, $firstRows] = readCsv($firstCsv);
    [$secondHeaders, $secondRows] = readCsv($secondCsv);
    [$outputHeaders, $outputRows] = mergeRows(
        $firstHeaders,
        $firstRows,
        $secondHeaders,
        $secondRows,
        'match',
        $secondCsv
    );

    writeCsv($outputCsv, $outputHeaders, $outputRows);
    [$writtenHeaders, $writtenRows] = readCsv($outputCsv);

    unlink($firstCsv);
    unlink($secondCsv);
    unlink($outputCsv);
    rmdir($tempDir);

    return [$writtenHeaders, $writtenRows];
}

[$writtenHeaders, $writtenRows] = runMergeCase(
    "match,name,first_only\n" .
    "A,Alice,alpha\n" .
    "B,Bob,beta\n",
    "match,name,second_only\n" .
    "B,Robert,beta-override\n" .
    "C,Carol,gamma\n"
);

assertSameValue(
    ['match', 'name', 'first_only', 'second_only'],
    $writtenHeaders,
    'Output headers should preserve first-file columns and append second-file extras.'
);

assertSameValue(
    [
        [
            'match' => 'A',
            'name' => 'Alice',
            'first_only' => 'alpha',
            'second_only' => '',
        ],
        [
            'match' => 'B',
            'name' => 'Robert',
            'first_only' => '',
            'second_only' => 'beta-override',
        ],
    ],
    $writtenRows,
    'Matching rows should come from the second file and non-matching rows from the first file.'
);

[$bomHeaders, $bomRows] = runMergeCase(
    "\xEF\xBB\xBFmatch,Name,2110 SRC Number\n" .
    "A,Alice,100\n" .
    "B,Bob,200\n",
    "match,Name,2110 SRC Number\n" .
    "B,Robert,999\n"
);

assertSameValue(
    ['match', 'Name', '2110 SRC Number'],
    $bomHeaders,
    'A BOM-prefixed match header should normalize to match.'
);

assertSameValue(
    [
        [
            'match' => 'A',
            'Name' => 'Alice',
            '2110 SRC Number' => '100',
        ],
        [
            'match' => 'B',
            'Name' => 'Robert',
            '2110 SRC Number' => '999',
        ],
    ],
    $bomRows,
    'BOM-prefixed match headers should still allow rows to be matched.'
);

[$blankHeaderHeaders, $blankHeaderRows] = runMergeCase(
    "match,Name,IPTV CN IP,,PORT\n" .
    "A,Alice,10.0.0.1,group-a,1\n" .
    "B,Bob,10.0.0.2,group-b,2\n",
    "match,Name,IPTV CN IP,,PORT\n" .
    "B,Robert,10.0.9.9,group-z,9\n"
);

assertSameValue(
    ['match', 'Name', 'IPTV CN IP', 'unnamed_column_4', 'PORT'],
    $blankHeaderHeaders,
    'Blank header names should be auto-filled with stable placeholder names.'
);

assertSameValue(
    [
        [
            'match' => 'A',
            'Name' => 'Alice',
            'IPTV CN IP' => '10.0.0.1',
            'unnamed_column_4' => 'group-a',
            'PORT' => '1',
        ],
        [
            'match' => 'B',
            'Name' => 'Robert',
            'IPTV CN IP' => '10.0.9.9',
            'unnamed_column_4' => 'group-z',
            'PORT' => '9',
        ],
    ],
    $blankHeaderRows,
    'Rows with blank header columns should still merge correctly.'
);

fwrite(STDOUT, "OK\n");
