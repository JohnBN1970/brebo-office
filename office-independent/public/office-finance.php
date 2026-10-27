<?php

declare(strict_types=1);

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['GET', 'POST'], true)) {
  header('Allow: GET, POST');
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
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  if ($method === 'POST' && !in_array(($officeFinanceView ?? ''), ['invoice', 'new'], true)) {
    http_response_code(405);
    exit('Upload alleen op de factuurpagina.');
  }
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('brebo_finance_upload_test');
    session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Strict','path'=>'/office-test']);
    session_start();
  }
  if (!isset($_SESSION['finance_upload_csrf'])) $_SESSION['finance_upload_csrf'] = bin2hex(random_bytes(32));
  $view = $officeFinanceView ?? 'dashboard';
  if (!in_array($view, ['dashboard','payments','invoice','new'], true)) throw new \OutOfBoundsException();
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
  if ($method === 'POST') {
    // Verify finance access; for existing invoices also authorize the exact invoice.
    (new \Brebo\Office\Finance\OfficeFinanceApplication($db, \Brebo\Office\Finance\OfficeFinanceReadConnection::fromEnvironment(), getenv('OFFICE_FINANCE_TABLE_PREFIX') ?: ''))->read($operator->operatorId, $invoiceId, $projectId);
    if (!\Brebo\Office\Intake\OfficeMount::isTest()
      || !hash_equals((string)$_SESSION['finance_upload_csrf'], (string)($_POST['csrf'] ?? ''))
      || !isset($_FILES['invoice_file']) || !is_array($_FILES['invoice_file'])) {
      http_response_code(403);
      exit('Upload geweigerd.');
    }
    $file = $_FILES['invoice_file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
      || !is_uploaded_file((string)($file['tmp_name'] ?? ''))
      || (int)($file['size'] ?? 0) < 1 || (int)$file['size'] > 10 * 1024 * 1024) {
      http_response_code(400);
      exit('Ongeldig bestand (maximaal 10 MB).');
    }
    $types = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($types[$mime])) {
      http_response_code(415);
      exit('Alleen PDF, JPG en PNG toegestaan.');
    }
    $storage = getenv('OFFICE_FINANCE_UPLOAD_PRIVATE_ROOT') ?: '';
    if ($storage === '' || !is_dir($storage) || is_link($storage)
      || !is_writable($storage) || str_starts_with(realpath($storage) ?: '', realpath($_SERVER['DOCUMENT_ROOT'] ?? '/') ?: '/')) {
      http_response_code(503);
      exit('Private uploadopslag niet ingericht.');
    }
    // Never serve uploads directly. Persist only a random file name; no posting to Moneybird.
    $name = bin2hex(random_bytes(24)) . '.' . $types[$mime];
    $destination = rtrim($storage, '/') . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
      http_response_code(503);
      exit('Upload niet opgeslagen.');
    }
    chmod($destination, 0600);
    if ($view === 'new') {
      $draft = ['id'=>bin2hex(random_bytes(16)), 'file'=>$name, 'mime'=>$mime, 'sha256'=>hash_file('sha256', $destination), 'created_at'=>gmdate('c'), 'operator_id'=>$operator->operatorId, 'status'=>'uploaded_not_processed'];
      $meta = rtrim($storage, '/') . '/' . $draft['id'] . '.json';
      if (file_put_contents($meta, json_encode($draft, JSON_THROW_ON_ERROR), LOCK_EX) === false) { unlink($destination); throw new RuntimeException('Conceptregistratie mislukt'); }
      chmod($meta, 0600);
    }
    header('Location: ' . \Brebo\Office\Intake\OfficeMount::route($view === 'new' ? 'finance/nieuw' : 'finance/factuur', $view === 'new' ? ['uploaded'=>'1'] : ['id'=>$invoiceId,'uploaded'=>'1']), true, 303);
    exit;
  }
  $data = (new \Brebo\Office\Finance\OfficeFinanceApplication($db, \Brebo\Office\Finance\OfficeFinanceReadConnection::fromEnvironment(), getenv('OFFICE_FINANCE_TABLE_PREFIX') ?: ''))->read($operator->operatorId, $invoiceId, $projectId);
  if (in_array($view, ['invoice','new'], true)) $data['upload_csrf'] = $_SESSION['finance_upload_csrf'];
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
