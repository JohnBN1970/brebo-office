<?php

declare(strict_types=1);

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self'; frame-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
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
  if ($method === 'POST' && !in_array(($officeFinanceView ?? ''), ['invoice', 'new', 'draft'], true)) {
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
  if (!in_array($view, ['dashboard','payments','invoice','new','draft'], true)) throw new \OutOfBoundsException();
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
  if ($view === 'draft') {
    // Enforce Finance read authorization before accessing private draft metadata.
    (new \Brebo\Office\Finance\OfficeFinanceApplication($db, \Brebo\Office\Finance\OfficeFinanceReadConnection::fromEnvironment(), getenv('OFFICE_FINANCE_TABLE_PREFIX') ?: ''))->read($operator->operatorId, null, $projectId);
  }
  if ($method === 'POST' && $view === 'draft') {
    if (!\Brebo\Office\Intake\OfficeMount::isTest()
      || !hash_equals((string)$_SESSION['finance_upload_csrf'], (string)($_POST['csrf'] ?? ''))) throw new \DomainException();
    $draftId = $_GET['id'] ?? '';
    if (!is_string($draftId) || !preg_match('/^[a-f0-9]{32}$/D', $draftId)) throw new \OutOfBoundsException();
    $storage = getenv('OFFICE_FINANCE_UPLOAD_PRIVATE_ROOT') ?: '';
    $root = realpath($storage);
    $webroot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '/');
    if ($root === false || is_link($storage) || ($webroot !== false && ($root === $webroot || str_starts_with($root, $webroot . '/')))) throw new \OutOfBoundsException();
    $metaFile = $root . '/' . $draftId . '.json';
    if (!is_file($metaFile) || is_link($metaFile)) throw new \OutOfBoundsException();
    $record = json_decode((string)file_get_contents($metaFile), true);
    if (!is_array($record) || ($record['id'] ?? '') !== $draftId || ($record['operator_id'] ?? null) != $operator->operatorId) throw new \OutOfBoundsException();
    if (($_POST['action'] ?? '') === 'extract') {
      $endpoint = trim((string)(getenv('OFFICE_FINANCE_EXTRACTION_ENDPOINT') ?: ''));
      $token = trim((string)(getenv('OFFICE_FINANCE_EXTRACTION_TOKEN') ?: ''));
      $expected = 'https://brebo-integration-api.john-boon.workers.dev/v1/internal/document-extraction';
      if (!hash_equals($expected, $endpoint) || $token === '' || !function_exists('curl_init')) {
        http_response_code(503); exit('Documentextractie is nog niet geconfigureerd.');
      }
      $filename = (string)($record['file'] ?? '');
      $mime = (string)($record['mime'] ?? '');
      if (!preg_match('/^[a-f0-9]{48}\\.(pdf|jpg|png)$/D', $filename)
        || !in_array($mime, ['application/pdf','image/jpeg','image/png'], true)) throw new \OutOfBoundsException();
      $path = $root . '/' . $filename;
      if (!is_file($path) || is_link($path) || filesize($path) < 1 || filesize($path) > 10 * 1024 * 1024
        || !hash_equals((string)($record['sha256'] ?? ''), hash_file('sha256', $path))) throw new \OutOfBoundsException();
      $form = ['document'=>new \CURLFile($path, $mime, 'factuur.' . pathinfo($filename, PATHINFO_EXTENSION))];
      $ch = curl_init($endpoint);
      curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$form,
        CURLOPT_HTTPHEADER=>['Accept: application/json','Authorization: Bearer ' . $token,'X-BREBO-Extraction-Contract: v1'],
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>35,
        CURLOPT_FOLLOWLOCATION=>false, CURLOPT_MAXREDIRS=>0]);
      $response = curl_exec($ch);
      $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
      curl_close($ch);
      $decoded = is_string($response) && strlen($response) <= 1048576 ? json_decode($response, true) : null;
      if ($code !== 200 || !is_array($decoded) || !in_array(($decoded['status'] ?? ''), ['extracted','no_text'], true)
        || !is_string($decoded['text'] ?? null) || strlen($decoded['text']) > 1000000) {
        http_response_code(502); exit('Documentextractie mislukt; concept ongewijzigd.');
      }
      $record['extraction'] = ['status'=>$decoded['status'], 'text'=>$decoded['text'],
        'extractor'=>(string)($decoded['extractor'] ?? 'brebo_managed_extraction_v1'),
        'at'=>gmdate('c'), 'source_sha256'=>$record['sha256']];
      $tmp = tempnam($root, '.extract-');
      if ($tmp === false || file_put_contents($tmp, json_encode($record, JSON_THROW_ON_ERROR), LOCK_EX) === false) throw new \RuntimeException('Opslaan mislukt');
      chmod($tmp, 0600);
      if (!rename($tmp, $metaFile)) throw new \RuntimeException('Opslaan mislukt');
      header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('finance/concept', ['id'=>$draftId,'extracted'=>'1']), true, 303);
      exit;
    }
    $fields = ['supplier_name'=>120,'invoice_number'=>80,'invoice_date'=>10,'due_date'=>10,'reference'=>160,'amount_inc_vat'=>30,'vat_amount'=>30];
    $review = [];
    foreach ($fields as $key=>$limit) {
      $value = trim((string)($_POST[$key] ?? ''));
      if (strlen($value) > $limit || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) { http_response_code(400); exit('Ongeldige invoer.'); }
      if (in_array($key, ['invoice_date','due_date'], true) && $value !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) || !checkdate((int)substr($value,5,2),(int)substr($value,8,2),(int)substr($value,0,4)))) { http_response_code(400); exit('Ongeldige datum.'); }
      if (in_array($key, ['amount_inc_vat','vat_amount'], true) && $value !== '' && !preg_match('/^\d{1,10}(?:[.,]\d{1,2})?$/D', $value)) { http_response_code(400); exit('Ongeldig bedrag.'); }
      $review[$key] = $value;
    }
    $selectedProject = trim((string)($_POST['office_project_id'] ?? ''));
    $access = new \Brebo\Office\Finance\OfficeFinanceAccess($db, $operator->operatorId);
    $visibleProjects = $access->draftProjects();
    if ($selectedProject !== '') {
      if (!ctype_digit($selectedProject) || (int)$selectedProject < 1) throw new \DomainException();
      $authorized = false;
      foreach ($visibleProjects as $project) {
        if ((int)$project['office_project_id'] === (int)$selectedProject) { $authorized = true; break; }
      }
      if (!$authorized) throw new \DomainException();
    }
    $record['office_project_id'] = $selectedProject === '' ? null : (int)$selectedProject;
    $record['review'] = $review;
    $record['status'] = 'manual_review_incomplete';
    $record['reviewed_at'] = gmdate('c');
    $record['reviewed_by'] = $operator->operatorId;
    $tmp = tempnam($root, '.review-');
    if ($tmp === false || file_put_contents($tmp, json_encode($record, JSON_THROW_ON_ERROR), LOCK_EX) === false) throw new RuntimeException('Opslaan mislukt');
    chmod($tmp, 0600);
    if (!rename($tmp, $metaFile)) throw new RuntimeException('Opslaan mislukt');
    header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('finance/concept', ['id'=>$draftId,'saved'=>'1']), true, 303);
    exit;
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
  if (in_array($view, ['invoice','new','draft'], true)) $data['upload_csrf'] = $_SESSION['finance_upload_csrf'];
  if ($view === 'draft') {
    if (!\Brebo\Office\Intake\OfficeMount::isTest()) throw new \OutOfBoundsException();
    $draftId = $_GET['id'] ?? '';
    if (!is_string($draftId) || !preg_match('/^[a-f0-9]{32}$/D', $draftId)) throw new \OutOfBoundsException();
    $storage = getenv('OFFICE_FINANCE_UPLOAD_PRIVATE_ROOT') ?: '';
    $root = realpath($storage);
    $webroot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '/');
    if ($root === false || !is_dir($root) || is_link($storage) || ($webroot !== false && ($root === $webroot || str_starts_with($root, $webroot . '/')))) throw new \OutOfBoundsException();
    $metaFile = $root . '/' . $draftId . '.json';
    if (!is_file($metaFile) || is_link($metaFile)) throw new \OutOfBoundsException();
    $record = json_decode((string)file_get_contents($metaFile), true);
    if (!is_array($record) || ($record['id'] ?? '') !== $draftId || ($record['operator_id'] ?? null) != $operator->operatorId) throw new \OutOfBoundsException();
    $filename = $record['file'] ?? '';
    $mime = $record['mime'] ?? '';
    if (!is_string($filename) || !preg_match('/^[a-f0-9]{48}\.(pdf|jpg|png)$/D', $filename)
      || !in_array($mime, ['application/pdf','image/jpeg','image/png'], true)) throw new \OutOfBoundsException();
    $filePath = $root . '/' . $filename;
    if (!is_file($filePath) || is_link($filePath) || filesize($filePath) > 10 * 1024 * 1024) throw new \OutOfBoundsException();
    if (hash_file('sha256', $filePath) !== ($record['sha256'] ?? '')) throw new \OutOfBoundsException();
    require_once dirname(__DIR__) . '/src/Finance/OfficeInvoiceExtractionProposal.php';
    $data['draft'] = ['id'=>$draftId,'created_at'=>$record['created_at'] ?? '', 'status'=>$record['status'] ?? '', 'mime'=>$mime,'review'=>is_array($record['review'] ?? null) ? $record['review'] : [], 'extraction'=>is_array($record['extraction'] ?? null) ? $record['extraction'] : null, 'proposals'=>is_string($record['extraction']['text'] ?? null) ? \Brebo\Office\Finance\OfficeInvoiceExtractionProposal::fromText($record['extraction']['text']) : []];
    // Advisory scan: same operator's private drafts, not imported invoices.
    $number = trim((string)($record['review']['invoice_number'] ?? ''));
    if ($number === '') $number = trim((string)($data['draft']['proposals']['invoice_number']['value'] ?? ''));
    $matches = [];
    if ($number !== '') {
      foreach (glob($root . '/*.json') ?: [] as $otherFile) {
        if ($otherFile === $metaFile || is_link($otherFile) || !is_file($otherFile)) continue;
        $other = json_decode((string)file_get_contents($otherFile), true);
        if (!is_array($other) || ($other['operator_id'] ?? null) != $operator->operatorId) continue;
        $otherId = (string)($other['id'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/D', $otherId)) continue;
        $otherNumber = trim((string)($other['review']['invoice_number'] ?? ''));
        if ($otherNumber === '' && is_string($other['extraction']['text'] ?? null)) {
          $otherProposal = \Brebo\Office\Finance\OfficeInvoiceExtractionProposal::fromText($other['extraction']['text']);
          $otherNumber = trim((string)($otherProposal['invoice_number']['value'] ?? ''));
        }
        if ($otherNumber !== '' && strcasecmp($number, $otherNumber) === 0) $matches[] = $otherId;
      }
    }
    $data['draft']['duplicate_candidates'] = array_slice($matches, 0, 20);
    $access = new \Brebo\Office\Finance\OfficeFinanceAccess($db, $operator->operatorId);
    $projectChoices = [];
    foreach ($access->draftProjects() as $project) {
      $projectChoices[(int)$project['office_project_id']] = (string)$project['title'];
    }
    $data['draft']['project_choices'] = $projectChoices;
    $data['draft']['office_project_id'] = $record['office_project_id'] ?? null;

    $data['draft']['imported_duplicate_candidates'] = $number === '' ? [] :
      (new \Brebo\Office\Finance\OfficeFinanceApplication(
        $db, \Brebo\Office\Finance\OfficeFinanceReadConnection::fromEnvironment(),
        getenv('OFFICE_FINANCE_TABLE_PREFIX') ?: ''
      ))->duplicateInvoiceCandidates($operator->operatorId, $number);

    if (($_GET['document'] ?? '') === '1') {
      header('Content-Type: ' . $mime);
      header('Content-Disposition: inline; filename="factuurconcept.' . pathinfo($filename, PATHINFO_EXTENSION) . '"');
      // The authenticated PDF viewer needs its own browser context; keep the
      // document private and non-cacheable without sandboxing the PDF plugin.
      header("Content-Security-Policy: default-src 'none'; object-src 'none'; base-uri 'none'; frame-ancestors 'self'");
      header('X-Content-Type-Options: nosniff');
      header('Content-Length: ' . filesize($filePath));
      readfile($filePath);
      exit;
    }
  }
  if ($view === 'new') {
    $storage = getenv('OFFICE_FINANCE_UPLOAD_PRIVATE_ROOT') ?: '';
    $drafts = [];
    if ($storage !== '' && is_dir($storage) && !is_link($storage)) {
      foreach (glob(rtrim($storage, '/') . '/*.json') ?: [] as $metadataFile) {
        if (!is_file($metadataFile) || is_link($metadataFile)) continue;
        $record = json_decode((string)file_get_contents($metadataFile), true);
        if (!is_array($record) || ($record['operator_id'] ?? null) != $operator->operatorId) continue;
        if (!is_string($record['id'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D', $record['id'])) continue;
        $drafts[] = [
          'id'=>$record['id'],
          'created_at'=>(string)($record['created_at'] ?? ''),
          'status'=>(string)($record['status'] ?? 'unknown'),
          'mime'=>(string)($record['mime'] ?? ''),
        ];
      }
    }
    usort($drafts, static fn(array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
    $data['drafts'] = array_slice($drafts, 0, 50);
  }

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
