<?php

declare(strict_types=1);

// CLI-only, read-only diagnostics. Run on the authorized Office-test host.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/src/Finance/OfficeSalesInvoiceSourceMap.php';
require_once dirname(__DIR__) . '/src/Finance/OfficeSalesInvoicePreflight.php';

use Brebo\Office\Finance\OfficeSalesInvoicePreflight;

function connection(string $prefix): PDO
{
    $dsn = getenv($prefix . '_PDO_DSN') ?: '';
    if ($dsn === '') {
        throw new RuntimeException('missing_' . strtolower($prefix) . '_dsn');
    }
    return new PDO($dsn, getenv($prefix . '_DB_USER') ?: '', getenv($prefix . '_DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
}

try {
    if (getenv('OFFICE_TEST_PREFLIGHT_ALLOWED') !== 'enabled') {
        throw new RuntimeException('test_preflight_not_enabled');
    }
    $office = connection('OFFICE_INTAKE');
    $sales = connection('OFFICE_FINANCE_SALES_READ');
    $result = OfficeSalesInvoicePreflight::inspect($office, $sales);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
    exit($result['draft_read_ready'] && $result['office_ready'] ? 0 : 2);
} catch (Throwable $e) {
    // Deliberately omit exception details: DSNs and database errors can contain secrets.
    fwrite(STDERR, "Sales invoice preflight unavailable; check environment and access.\n");
    exit(3);
}
