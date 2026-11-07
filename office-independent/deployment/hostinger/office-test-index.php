<?php

declare(strict_types=1);

/**
 * Isolated Hostinger test-only entrypoint. Install as public_html/office-test/index.php.
 * Never reuse the production Office router or its configuration file.
 * Requires a separate HTTP authentication gate before browser exposure.
 */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

$configFile = '/home/u213420663/domains/brebobv.nl/office_calc_test/config/config.php';
if (!is_file($configFile)) {
  http_response_code(503);
  exit('Testconfiguratie ontbreekt.');
}
$config = require $configFile;
if (!is_array($config)) {
  http_response_code(503);
  exit('Ongeldige testconfiguratie.');
}
foreach ($config as $key => $value) {
  if (!is_string($key) || !is_string($value)) {
    http_response_code(503);
    exit('Ongeldige testinstelling.');
  }
  putenv($key . '=' . $value);
}
// Resolve the isolated Office-test release from its verified configuration.
// Never pin a stale release or accept an arbitrary path outside the test tree.
$root = getenv('OFFICE_PRIVATE_ROOT');
$testRoot = '/home/u213420663/domains/brebobv.nl/office_calc_test/';
if (!is_string($root)
  || !preg_match('~^' . preg_quote($testRoot, '~') . 'release-[a-zA-Z0-9_-]+/private$~D', $root)) {
  http_response_code(503);
  exit('Ongeldige testrelease.');
}
$private = realpath($root);
$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_PRIVATE_ROOT') !== $root
  || $private === false || !is_dir($private . '/public')
  || !is_dir($private . '/src')
  || !is_string($dsn) || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', $dsn)
  || getenv('OFFICE_INTAKE_DB_USER') !== 'u213420663_office_test'
  || !is_string($allowedHost) || $allowedHost === ''
  || !is_string($host) || !hash_equals($allowedHost, $host)
  || in_array(strtolower($host), ['office.brebobv.nl', 'www.office.brebobv.nl'], true)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off'
  || (getenv('OFFICE_NATIVE_PROJECTS_ENABLED') === 'enabled' && getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') !== 'enabled')) {
  http_response_code(503);
  exit('Testomgeving niet beschikbaar.');
}

// Office 2.0 rollout: require the isolated test router and an explicit allow flag.
// Deny by default even if a feature flag was accidentally enabled in shared config.
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_TEST_UI_V2_ROLLOUT_ALLOWED') !== 'enabled') {
  putenv('OFFICE_TEST_NAV_V2_ENABLED=disabled');
  putenv('OFFICE_TEST_WORKSPACE_V2_ENABLED=disabled');
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$routes = [
  '/office-test' => 'office-home.php',
  '/office-test/' => 'office-home.php',
  '/office-test/inloggen' => 'office-login.php',
  '/office-test/uitloggen' => 'office-logout.php',
  '/office-test/crm/relaties' => 'office-crm-relations.php',
  '/office-test/crm/relaties/nieuw' => 'office-crm-relation-create.php',
  '/office-test/crm/relaties/detail' => 'office-crm-relation-detail.php',
  '/office-test/crm/relaties/beheren' => 'office-crm-relation-manage.php',
  '/office-test/crm/aanvragen' => 'crm-new-requests.php',
  '/office-test/crm/aanvragen/detail' => 'crm-new-requests.php',
  '/office-test/projecten' => 'office-projects.php',
  '/office-test/gebouwen' => 'office-buildings.php',
  '/office-test/personeel/medewerkers' => 'office-personnel.php',
  '/office-test/personeel/medewerkers/detail' => 'office-personnel.php',
  '/office-test/finance' => 'office-finance.php',
  '/office-test/finance/betaalcentrum' => 'office-finance-payments.php',
  '/office-test/finance/factuur' => 'office-finance-invoice.php',
  '/office-test/finance/dossier' => 'office-finance-dossier.php',
  '/office-test/finance/nieuw' => 'office-finance-new.php',
  '/office-test/finance/concept' => 'office-finance-draft.php',
  '/office-test/inzet/dienst/nieuw' => 'office-inzet-shift-create.php',
  '/office-test/inzet/importcontrole' => 'office-inzet-import-review.php',
  '/office-test/inzet/dienst/annuleren' => 'office-inzet-shift-cancel.php',
  '/office-test/inzet/dienst/verplaatsen' => 'office-inzet-shift-reschedule.php',
  '/office-test/inzet/onsite/aanwezigheid' => 'office-onsite-presence-review.php',
  '/office-test/inzet/zone/bijwerken' => 'office-onsite-zone-update.php',
  '/office-test/inzet/medewerker/personeelsnummer' => 'office-inzet-personnel-number.php',
  '/office-test/inzet/onsite/activatie-uitgeven' => 'office-onsite-invitation.php',
  '/office-test/inzet/onsite/apparaat-intrekken' => 'office-onsite-device-revoke.php',
  '/office-test/projecten/detail' => 'office-project.php',
  '/office-test/inzet/projectkaart' => 'office-onsite-project-map.php',
  '/office-test/inzet/zone/aanmaken' => 'office-onsite-zone-create.php',
];
// Incomplete Calc configuration must never disable unrelated Office-test pages.
// Each Calc route fails closed independently, including its shared-secret gate.
$calcBridgeReady = getenv('OFFICE_CALC_ENABLED') === 'enabled'
  && getenv('OFFICE_CALC_BROWSER_HANDOFF_ENABLED') === 'enabled'
  && getenv('OFFICE_CALC_SAME_ORIGIN_PROXY_VERIFIED') === 'enabled'
  && getenv('OFFICE_CALC_SESSION_GRANTS_ENABLED') === 'enabled'
  && getenv('OFFICE_CALC_GRANT_VALIDATION_ENABLED') === 'enabled'
  && strlen((string) getenv('OFFICE_CALC_SHARED_SECRET')) >= 32;
// Calc routes are intentionally absent from the generic page table.
// They stay inaccessible until every isolated test gate is explicitly verified.
if ($path === '/office-test/calc-werkbank' || $path === '/office-test/calc/start') {
  if (!$calcBridgeReady || getenv('OFFICE_CALC_LAUNCH_ENABLED') !== 'enabled') {
    http_response_code(404);
    exit('Testpagina niet gevonden.');
  }
  require $private . '/public/' . ($path === '/office-test/calc/start'
    ? 'office-calc-launch.php' : 'office-calc-browser-handoff.php');
  exit;
}
if ($path === '/office-test/api/workbench/v1/calculation-grants/validate'
  || $path === '/api/workbench/v1/calculation-grants/validate') {
  if (!$calcBridgeReady) {
    http_response_code(404);
    exit('Testpagina niet gevonden.');
  }
  require $private . '/public/office-calc-grant-validate.php';
  exit;
}
if (is_string($path)
  && preg_match('~^/api/workbench/v1/projects/[1-9][0-9]*/calculation-context$~D', $path)) {
  if (!$calcBridgeReady) {
    http_response_code(404);
    exit('Testpagina niet gevonden.');
  }
  require $private . '/public/office-calc-project-context.php';
  exit;
}
// Machine-to-machine intake is opt-in and never exposed by default.
if ($path === '/office-test/api/intake/v1/receive') {
  if (getenv('OFFICE_INTAKE_RECEIVER_ENABLED') !== 'enabled') {
    http_response_code(404);
    exit('Testpagina niet gevonden.');
  }
  require $private . '/public/office-intake-receive.php';
  exit;
}
if ($path === '/office-test/inzet/apparaten') {
  require $private . '/public/onsite-devices.php';
  exit;
}
if ($path === '/office-test/api/onsite/v1/activate') {
  require $private . '/public/onsite-activate.php';
  exit;
}
if ($path === '/office-test/api/onsite/v1/bootstrap') {
  require $private . '/public/onsite-bootstrap.php';
  exit;
}
if ($path === '/office-test/api/onsite/v1/presence') {
  // Isolated test environment: no production token or assignment fallback.
  require $private . '/public/onsite-presence.php';
  exit;
}
if (!is_string($path) || !isset($routes[$path])) {
  http_response_code(404);
  exit('Testpagina niet gevonden.');
}
// Decorate HTML pages with the shared Office navigation while preserving page-owned module tabs.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && !in_array($path, ['/office-test/inloggen', '/office-test/uitloggen'], true)) {
  require_once $private . '/src/Intake/OfficeNavigation.php';
  \Brebo\Office\Intake\OfficeNavigation::register();
}
require $private . '/public/' . $routes[$path];
