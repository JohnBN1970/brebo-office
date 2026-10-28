<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; form-action \'self\'; base-uri \'none\'; frame-ancestors \'none\'');
if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['GET','POST'], true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
if (getenv('OFFICE_BASE_PATH') !== '/office-test' || getenv('OFFICE_FINANCE_ENABLED') !== 'enabled' || getenv('OFFICE_FINANCE_SCHEMA_VERIFIED') !== 'enabled' || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled') { http_response_code(503); exit; }
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!is_string($host) || !hash_equals((string)(getenv('OFFICE_PUBLIC_HOST') ?: ''), $host) || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') { http_response_code(403); exit; }
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) require_once dirname(__DIR__) . '/src/Intake/' . $file;
  require_once dirname(__DIR__) . '/src/Finance/bootstrap.php';
  $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '', getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $operator = (new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()))->currentOperator();
  if ($operator === null) { header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302); exit; }
  $access = new \Brebo\Office\Finance\OfficeFinanceAccess($db, $operator->operatorId);
  if (!$access->allows()) { http_response_code(403); exit; }
  $projects = array_values(array_filter($access->draftProjects(), static function (array $project) use ($db, $operator): bool {
    $q = $db->prepare("SELECT 1 FROM office_project_memberships WHERE operator_id=? AND project_id=? AND permission='manage' LIMIT 1");
    $q->execute([$operator->operatorId, $project['office_project_id']]);
    return $q->fetchColumn() !== false;
  }));
  $editable = getenv('OFFICE_FINANCE_SALES_DRAFTS_SCHEMA_VERIFIED') === 'enabled' && getenv('OFFICE_FINANCE_SALES_DRAFTS_CREATE_ENABLED') === 'enabled' && getenv('OFFICE_VAT_REGIMES_SCHEMA_VERIFIED') === 'enabled';
  $vatOptions = $editable ? (new \Brebo\Office\Finance\OfficeVatRegimeCatalog($db))->activeOptions() : [];
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('brebo_finance_sales_test');
    session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Strict','path'=>'/office-test']);
    session_start();
  }
  if (!isset($_SESSION['sales_draft_csrf'])) $_SESSION['sales_draft_csrf'] = bin2hex(random_bytes(32));
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!$editable) { http_response_code(503); exit('Conceptopslag niet vrijgegeven.'); }
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) { http_response_code(413); exit('Formulier te groot.'); }
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['sales_draft_csrf'], $_POST['csrf'])) { http_response_code(403); exit('Ongeldige formulierherkomst.'); }
    $input = ['customer_name'=>$_POST['customer_name'] ?? null,'office_project_id'=>$_POST['office_project_id'] ?? null,'lines'=>[['description'=>$_POST['description'] ?? null,'quantity'=>$_POST['quantity'] ?? null,'unit_price'=>$_POST['unit_price'] ?? null,'vat_regime_id'=>$_POST['vat_regime_id'] ?? null]]];
    try {
      $draftId = (new \Brebo\Office\Finance\OfficeOutgoingInvoiceRepository($db))->create($operator->operatorId, $input);
      // Invalidate the submitted token after a successful write to reject a replay.
      unset($_SESSION['sales_draft_csrf']);
      header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('finance/facturen', ['direction'=>'outgoing']), true, 303);
      exit;
    } catch (\InvalidArgumentException | \DomainException $validationError) { http_response_code(422); exit('Controleer project, factuurregels en btw-regime.'); }
  }
  $e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verkoopfactuurconcept · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f3f6fa;color:#17283a;margin:0;padding:24px}main{max-width:850px;margin:auto;background:white;border-radius:12px;padding:24px}label{display:block;margin:16px 0}input,select{display:block;width:100%;padding:10px;box-sizing:border-box}button{padding:12px}a{color:#155c87}</style></head><body><main><p><a href="'.$e(\Brebo\Office\Intake\OfficeMount::route('finance/facturen')).'">Terug naar facturen</a></p><h1>Nieuw verkoopfactuurconcept</h1><p>Alleen conceptopslag. Geen factuurnummer en geen verzending.</p>';
  if (!$editable) echo '<p>Conceptopslag is nog niet vrijgegeven.</p>';
  echo '<form method="post"><input type="hidden" name="csrf" value="'.$e($_SESSION['sales_draft_csrf']).'"><label>Klantnaam<input name="customer_name" maxlength="160" required'.($editable?'':' disabled').'></label><label>Office-project<select name="office_project_id" required'.($editable?'':' disabled').'><option value="">Project kiezen</option>';
  foreach ($projects as $project) echo '<option value="'.(int)$project['office_project_id'].'">'.$e($project['title']).'</option>';
  echo '</select></label><h2>Factuurregel</h2><label>Omschrijving<input name="description" maxlength="240" required'.($editable?'':' disabled').'></label><label>Aantal<input name="quantity" value="1" required'.($editable?'':' disabled').'></label><label>Eenheidsprijs exclusief btw<input name="unit_price" required'.($editable?'':' disabled').'></label><label>Btw-regime<select name="vat_regime_id" required'.($editable?'':' disabled').'><option value="">Kies btw-regime</option>';
  foreach ($vatOptions as $code=>$label) echo '<option value="'.$e($code).'">'.$e($label).'</option>';
  echo '</select></label><button type="submit"'.($editable?'':' disabled').'>Concept opslaan</button></form></main></body></html>';
} catch (Throwable $e) {
  error_log('Office sales draft page unavailable: ' . get_class($e));
  http_response_code(503);
  echo 'Verkoopfactuurconcept tijdelijk niet beschikbaar.';
}
