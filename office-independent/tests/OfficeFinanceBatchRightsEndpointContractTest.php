<?php
declare(strict_types=1);

/**
 * Static safety contract for the self-service rights endpoint.
 * No live Office database or HTTP session is needed in CI.
 */
$root = dirname(__DIR__);
$page = file_get_contents($root . '/public/office-finance-batch-permissions.php');
$router = file_get_contents($root . '/deployment/hostinger/office-test-index.php');
$nav = file_get_contents($root . '/src/Intake/OfficeNavigation.php');
if (!is_string($page) || !is_string($router) || !is_string($nav)) {
  throw new RuntimeException('Required files unavailable');
}
$checks = 0;
$require = static function (bool $ok, string $message) use (&$checks): void {
  if (!$ok) throw new RuntimeException($message);
  ++$checks;
};
$require(str_contains($page, "['GET']") || str_contains($page, "!== 'GET'"), 'GET-only page');
$require(str_contains($page, 'currentOperator()'), 'Authenticated Office identity');
$require(str_contains($page, "allows('read')"), 'Finance read authorization');
$require(str_contains($page, "if (!\$snapshot['active'])"), 'Active operator check');
$require(str_contains($page, 'OfficeFinanceBatchPermissionsView::render'), 'Read-only renderer');
$require(str_contains($page, "Cache-Control: no-store, private"), 'Private cache');
$require(str_contains($page, "Content-Security-Policy:"), 'CSP enabled');
$require(str_contains($page, "OFFICE_FINANCE_SCHEMA_VERIFIED"), 'Schema gate');
$require(str_contains($page, "OFFICE_CRM_SESSION_MODE"), 'Session gate');
$require(!str_contains($page, '$_GET['), 'No target-operator query parameter');
$require(!str_contains($page, '$_POST['), 'No POST mutation');
$require(!preg_match('/\b(?:INSERT|UPDATE|DELETE)\s+(?:INTO\s+|FROM\s+)?office_module_permissions\b/i', $page), 'No grant mutations');
$require(!preg_match('/\b(?:curl_exec|exec|shell_exec|proc_open)\s*\(/i', $page), 'No external payment execution');
$require(str_contains($router, "'/office-test/finance/mijn-betaalrechten' => 'office-finance-batch-permissions.php'"), 'Test route');
$require(str_contains($nav, "['Mijn betaalbatchrechten','/office-test/finance/mijn-betaalrechten']"), 'Finance menu');
echo "OfficeFinanceBatchRightsEndpointContractTest OK ($checks checks)\n";
