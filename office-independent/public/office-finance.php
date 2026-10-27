<?php

declare(strict_types=1);

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
  http_response_code(405);
  exit;
}
if (getenv('OFFICE_FINANCE_ENABLED') !== 'enabled'
  || getenv('OFFICE_FINANCE_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled') {
  http_response_code(503);
  exit('Finance is nog niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowedHost) || $allowedHost === '' || !hash_equals($allowedHost, (string)$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit;
}
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
    require_once dirname(__DIR__) . '/src/Intake/' . $file;
  }
  require_once dirname(__DIR__) . '/src/Finance/bootstrap.php';
  $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '', getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $operator = (new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()))->currentOperator();
  if ($operator === null) {
    header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302);
    exit;
  }
  $view = $officeFinanceView ?? 'dashboard';
  if (!in_array($view, ['dashboard','payments','invoice'], true)) throw new \OutOfBoundsException();
  $invoiceId = null;
  if ($view === 'invoice') {
    $invoiceId = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($invoiceId === false) throw new \OutOfBoundsException();
  }
  $projectId = null;
  if (isset($_GET['project_id'])) {
    $projectId = filter_var($_GET['project_id'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($projectId === false) throw new \OutOfBoundsException();
  }
  $data = (new \Brebo\Office\Finance\OfficeFinanceApplication($db, \Brebo\Office\Finance\OfficeFinanceReadConnection::fromEnvironment(), getenv('OFFICE_FINANCE_TABLE_PREFIX') ?: ''))->read($operator->operatorId, $invoiceId, $projectId);
  $queue = is_string($_GET['queue'] ?? null) ? $_GET['queue'] : '';
  $search = is_string($_GET['q'] ?? null) ? substr($_GET['q'],0,120) : '';
  $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 1;
  $html = \Brebo\Office\Finance\OfficeFinanceView::render($data, $view, $queue, $page, $search);
} catch (\DomainException $e) {
  http_response_code(403);
  exit('Geen toegang tot Finance.');
} catch (\OutOfBoundsException $e) {
  http_response_code(404);
  exit('Factuur niet beschikbaar.');
} catch (Throwable $e) {
  http_response_code(503);
  exit('Finance is tijdelijk niet beschikbaar.');
}
header('Content-Type: text/html; charset=utf-8');
echo $html;
