<?php

declare(strict_types=1);

/**
 * Prevent drift between the explicit MySQL migration and PHP schema contract.
 * No database connection or DDL execution.
 */
require_once __DIR__ . '/../src/Communication/OfficeCommunicationSchema.php';

use Brebo\Office\Communication\OfficeCommunicationSchema;

$file = __DIR__ . '/../migrations/20261031_office_communication.sql';
$sql = file_get_contents($file);
if ($sql === false) {
    throw new RuntimeException('Migration missing.');
}
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
if (!is_string($sql)) {
    throw new RuntimeException('Cannot normalize migration.');
}
$statements = array_values(array_filter(array_map('trim', explode(';', $sql))));
$expected = OfficeCommunicationSchema::statements();
if (count($statements) !== count($expected)) {
    throw new RuntimeException('Migration statement count differs from schema.');
}
$normalize = static function (string $value): string {
    $value = strtolower($value);
    $value = preg_replace('/\s+/', ' ', $value);
    $value = preg_replace('/\s*([(),=])\s*/', '$1', (string) $value);
    return trim((string) $value);
};
foreach ($expected as $index => $statement) {
    if ($normalize($statement) !== $normalize($statements[$index])) {
        throw new RuntimeException('Migration differs from PHP schema at statement ' . ($index + 1));
    }
}
echo "Office communication MySQL migration parity OK\n";
