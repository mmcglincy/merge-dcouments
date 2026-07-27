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

$tempDir = sys_get_temp_dir() . '/merge-csv-by-match-' . bin2hex(random_bytes(8));
if (!mkdir($tempDir) && !is_dir($tempDir)) {
    fwrite(STDERR, "Unable to create temp directory\n");
    exit(1);
}

$firstCsv = $tempDir . '/first.csv';
$secondCsv = $tempDir . '/second.csv';
$outputCsv = $tempDir . '/output.csv';

file_put_contents(
    $firstCsv,
    "match,name,first_only\n" .
    "A,Alice,alpha\n" .
    "B,Bob,beta\n"
);
file_put_contents(
    $secondCsv,
    "match,name,second_only\n" .
    "B,Robert,beta-override\n" .
    "C,Carol,gamma\n"
);

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

unlink($firstCsv);
unlink($secondCsv);
unlink($outputCsv);
rmdir($tempDir);

fwrite(STDOUT, "OK\n");
