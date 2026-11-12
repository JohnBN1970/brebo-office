<?php
declare(strict_types=1);

/**
 * Read-only Office-test handoff readiness audit.
 * Run via CLI with the same trusted PDO bootstrap used by the Office deployment.
 * No schema installation or project creation is performed.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(3);
}
if (!isset($db) || !$db instanceof PDO) {
    fwrite(STDERR, "HANDOFF_PREFLIGHT_PDO_REQUIRED\n");
    exit(3);
}
require_once dirname(__DIR__, 2) . '/src/Calculation/OfficeNativeProjectHandoffPreflight.php';
try {
    $result = \Brebo\Office\Calculation\OfficeNativeProjectHandoffPreflight::inspect($db);
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
    exit($result['schema_ready'] ? 0 : 2);
} catch (Throwable $error) {
    fwrite(STDERR, "HANDOFF_PREFLIGHT_FAILED: " . get_class($error) . "\n");
    exit(3);
}
