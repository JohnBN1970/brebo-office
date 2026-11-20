<?php
declare(strict_types=1);
/** VPS Office-test only. Existing module routing, isolated from production. */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
$release=realpath(dirname(__DIR__));
$root=realpath('/srv/brebo/office-test');
$expectedHost='office-test.brebobv.nl';
$host=$_SERVER['HTTP_HOST']??'';
$https=($_SERVER['HTTPS']??'')==='on';
if (!$release || !$root || !str_starts_with($release,$root.'/releases/')
    || !is_file($release.'/.office-source-sha')
    || !hash_equals($expectedHost,(string)$host)
    || !$https || getenv('OFFICE_VPS_TEST_ROUTER_ENABLED')!=='enabled') {
  http_response_code(503); exit('Testomgeving niet beschikbaar.');
}
if (getenv('OFFICE_NATIVE_PROJECTS_ENABLED')==='enabled'
    && getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED')!=='enabled') {
  http_response_code(503); exit('Testomgeving niet beschikbaar.');
}
putenv('OFFICE_PUBLIC_HOST='.$expectedHost);
putenv('OFFICE_BASE_PATH=/office-test');
putenv('OFFICE_PRIVATE_ROOT='.$release);
// The VPS deployment archives office-independent only; the shared-host
// activation script does not run here. Enable CRM writes for the isolated
// test router only after the existing CRM and schema gates are verified.
if (getenv('OFFICE_TEST_CRM_ENABLED')==='enabled'
    && getenv('OFFICE_CRM_AUTH_MODE')==='session_only'
    && getenv('OFFICE_CRM_RELATIONS_SCHEMA_VERIFIED')==='enabled') {
  putenv('OFFICE_CRM_RELATIONS_WRITE_ENABLED=enabled');
}
$private = $release; // Existing isolated module handlers use this root.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
// Preserve the existing /office-test URL and cookie namespace on the new subdomain.
if ($path === '/') {
  header('Location: /office-test/', true, 302);
  exit;
}
$routes = [
  '/office-test/materialen' => 'office-materials.php',
  '/office-test/marketing' => 'office-marketing.php',
  '/office-test/calculatie' => 'office-calc-crm-workbench.php',
  '/office-test/calculatie/samenwerken' => 'office-calc-crm-workbench.php',
  '/office-test' => 'office-home.php',
  '/office-test/' => 'office-home.php',
  '/office-test/inloggen' => 'office-login.php',
  '/office-test/uitloggen' => 'office-logout.php',
  '/office-test/mijn-dashboard' => 'office-personal-dashboard.php',
  '/office-test/crm/relaties' => 'office-crm-relations.php',
  '/office-test/crm/relaties/nieuw' => 'office-crm-relation-create.php',
  '/office-test/crm/relaties/detail' => 'office-crm-relation-detail.php',
  '/office-test/crm/relaties/beheren' => 'office-crm-relation-manage.php',
  '/office-test/crm/verkoopkansen' => 'office-crm-opportunities.php',
  '/office-test/crm/verkoopkansen/nieuw' => 'office-crm-opportunity-create.php',
  '/office-test/crm/verkoopkansen/fase' => 'office-crm-opportunity-transition.php',
  '/office-test/crm/verkoopkansen/drag.js' => 'office-crm-kanban-drag.php',
  '/office-test/crm/aanvragen/stijl.css' => 'office-leads-styles.php',
  '/office-test/crm/aanvragen' => 'crm-new-requests.php',
  '/office-test/crm/aanvragen/detail' => 'crm-new-requests.php',
  '/office-test/crm/aanvragen/nieuw' => 'office-crm-lead-create.php',
  '/office-test/crm/aanvragen/fase' => 'office-crm-lead-board-transition.php',
  '/office-test/crm/aanvragen/drag.js' => 'office-crm-lead-board-drag.php',
  '/office-test/projecten' => 'office-projects.php',
  '/office-test/gebouwen' => 'office-buildings.php',
  '/office-test/kwaliteit' => 'office-governance.php',
  '/office-test/risicos' => 'office-governance.php',
  '/office-test/inkoop' => 'office-governance.php',
  '/office-test/personeel/medewerkers' => 'office-personnel.php',
  '/office-test/personeel/medewerkers/detail' => 'office-personnel.php',
  '/office-test/finance' => 'office-finance.php',
  '/office-test/finance/verkoop' => 'office-sales.php',
  '/office-test/finance/administratie' => 'office-finance-center.php',
  '/office-test/finance/betaalcentrum' => 'office-finance-payments.php',
  '/office-test/finance/factuur' => 'office-finance-invoice.php',
  '/office-test/finance/dossier' => 'office-finance-dossier.php',
  '/office-test/finance/nieuw' => 'office-finance-new.php',
  '/office-test/finance/concept' => 'office-finance-draft.php',
  '/office-test/inzet/dienst/nieuw' => 'office-inzet-shift-create.php',
  '/office-test/inzet/importcontrole' => 'office-inzet-import-review.php',
  '/office-test/inzet/dienst/annuleren' => 'office-inzet-shift-cancel.php',
  '/office-test/inzet/dienst/verplaatsen' => 'office-inzet-shift-reschedule.php',
  '/office-test/inzet/aanwezigheid' => 'office-onsite-presence-review.php',
  '/office-test/inzet/onsite/aanwezigheid' => 'office-onsite-presence-review.php',
  '/office-test/inzet/onsite/uren-beoordelen' => 'office-onsite-presence-review.php',
  '/office-test/inzet/zone/bijwerken' => 'office-onsite-zone-update.php',
  '/office-test/inzet/medewerker/personeelsnummer' => 'office-inzet-personnel-number.php',
  '/office-test/inzet/onsite/activatie-uitgeven' => 'office-onsite-invitation.php',
  '/office-test/inzet/onsite/apparaat-intrekken' => 'office-onsite-device-revoke.php',
  '/office-test/projecten/detail' => 'office-project.php',
  '/office-test/inzet/projectkaart' => 'office-onsite-project-map.php',
  '/office-test/inzet/zone/aanmaken' => 'office-onsite-zone-create.php',
];
// Browser handoff never puts signed credentials in query strings.
if ($path === '/office-test/calc-werkbank') {
  require $private . '/public/office-calc-browser-handoff.php';
  exit;
}
// Launch endpoint is POST-only and remains disabled unless every flag is enabled.
if ($path === '/office-test/calc/start') {
  require $private . '/public/office-calc-launch.php';
  exit;
}
// Calc grant validation is an opt-in, signed machine-to-machine endpoint.
// The /api/... alias only works if the webserver rewrites it to this test router.
if ($path === '/office-test/api/workbench/v1/calculation-grants/validate'
  || $path === '/api/workbench/v1/calculation-grants/validate') {
  require $private . '/public/office-calc-grant-validate.php';
  exit;
}
// Calc uses a signed GET path for Office-owned project context.
// The /api alias requires an explicit webserver rewrite to this isolated router.
if (is_string($path)
  && preg_match('~^/api/workbench/v1/projects/[1-9][0-9]*/calculation-context$~D', $path)) {
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
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && !in_array($path, ['/office-test/inloggen', '/office-test/uitloggen', '/office-test/crm/aanvragen/stijl.css'], true)) {
  require_once $private . '/src/Intake/OfficeNavigation.php';
  \Brebo\Office\Intake\OfficeNavigation::register();
}
require $release . '/public/' . $routes[$path];
