<?php

declare(strict_types=1);

// Isolated test review endpoint. Source remains read-only; decisions use a private store.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['GET', 'POST'], true)) {
  header('Allow: GET, POST');
  http_response_code(405);
  exit;
}
require_once dirname(__DIR__) . '/src/Intake/OfficeMount.php';
if (!\Brebo\Office\Intake\OfficeMount::isTest()
  || getenv('OFFICE_TEST_INZET_IMPORT_REVIEW_ENABLED') !== 'enabled') {
  http_response_code(404);
  exit;
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$expectedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($expectedHost) || $expectedHost === ''
  || !hash_equals($expectedHost, (string)$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit;
}
try {
  foreach ([
    'WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php',
    'WebsiteOfficeCrmCookieSessionProvider.php',
  ] as $file) {
    require_once dirname(__DIR__) . '/src/Intake/' . $file;
  }
  $officeDb = new PDO(
    getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
    getenv('OFFICE_INTAKE_DB_USER') ?: '',
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]
  );
  $operator = (new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($officeDb),
    $_COOKIE,
    time()
  ))->currentOperator();
  if ($operator === null) {
    header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302);
    exit;
  }
  // This workbook can contain multiple projects and sensitive worker data.
  // Only an explicitly designated test reviewer may access the whole file.
  $reviewerId = filter_var(getenv('OFFICE_TEST_INZET_IMPORT_REVIEWER_ID'), FILTER_VALIDATE_INT,
    ['options'=>['min_range'=>1]]);
  if (!is_int($reviewerId) || $reviewerId !== (int)$operator->operatorId) {
    http_response_code(403);
    exit;
  }
  $path = getenv('OFFICE_TEST_INZET_SQLITE_PATH');
  if (!is_string($path) || $path === '' || !is_file($path)
    || !is_readable($path) || str_contains($path, "\0")) {
    throw new RuntimeException('Test source unavailable');
  }
  $realPath = realpath($path);
  $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
  if ($realPath === false || $documentRoot === false
    || str_starts_with($realPath, rtrim($documentRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('Private test source required');
  }
  $db = new PDO('sqlite:file:'.str_replace('%2F', '/', rawurlencode($realPath)).'?mode=ro', null, null, [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_TIMEOUT=>2,
  ]);
  require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetImportReviewReadRepository.php';
  require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetSourceReviewWorkbench.php';
  $report = (new \Brebo\Office\Inzet\OfficeInzetImportReviewReadRepository($db))->summary();
  // A review session belongs to one authenticated operator only.
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('BREBO_INZET_REVIEW');
    session_set_cookie_params(['secure'=>true, 'httponly'=>true, 'samesite'=>'Strict', 'path'=>'/']);
    session_start();
  }
  if (isset($_SESSION['inzet_reviewer_id']) && $_SESSION['inzet_reviewer_id'] !== (int)$operator->operatorId) {
    $_SESSION = [];
    session_regenerate_id(true);
  }
  $_SESSION['inzet_reviewer_id'] = (int)$operator->operatorId;
  $filters = [];
  foreach (['person', 'project', 'sheet'] as $field) {
    $value = $_GET[$field] ?? '';
    if (!is_string($value) || strlen($value) > 120) {
      http_response_code(400);
      exit('Ongeldig filter.');
    }
    $filters[$field] = trim($value);
  }
  $decisionIndex = [];
  $decisionPath = getenv('OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH');
  $decisionStoreReady = false;
  if (is_string($decisionPath) && $decisionPath !== '') {
    $decisionRealPath = realpath($decisionPath);
    if ($decisionRealPath === false || !is_file($decisionRealPath) || !is_readable($decisionRealPath)
      || str_starts_with($decisionRealPath, rtrim($documentRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
      || $decisionRealPath === $realPath) {
      throw new RuntimeException('Private decision store unavailable');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !is_writable($decisionRealPath)) {
      throw new RuntimeException('Private decision store is not writable');
    }
    $decisionMode = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? 'rw' : 'ro';
    $decisionDb = new PDO('sqlite:file:'.str_replace('%2F', '/', rawurlencode($decisionRealPath)).'?mode='.$decisionMode, null, null, [
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT=>2,
    ]);
    require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetReviewDecisionRepository.php';
    $decisionRepo = new \Brebo\Office\Inzet\OfficeInzetReviewDecisionRepository($decisionDb, $db);
    $decisionStoreReady = true;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
      $contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
      if ($contentType !== 'application/x-www-form-urlencoded'
        || (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) {
        http_response_code(400); exit('Ongeldig verzoek.');
      }
      if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('BREBO_INZET_REVIEW');
        session_set_cookie_params(['secure'=>true, 'httponly'=>true, 'samesite'=>'Strict', 'path'=>'/']);
        session_start();
      }
      $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
      if (is_string($origin) && $origin !== 'https://'.$expectedHost) {
        http_response_code(403); exit('Ongeldige herkomst.');
      }
      $csrf = $_POST['csrf'] ?? null;
      if (!is_string($csrf) || !is_string($_SESSION['inzet_csrf'] ?? null)
        || !hash_equals($_SESSION['inzet_csrf'], $csrf)) {
        http_response_code(403); exit('Ongeldige sessiecontrole.');
      }
      $issue = filter_var($_POST['issue_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
      $revision = filter_var($_POST['revision'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
      $action = $_POST['action'] ?? null;
      $note = $_POST['note'] ?? null;
      if (!is_int($issue) || !is_int($revision) || !is_string($action) || !is_string($note)) {
        http_response_code(400); exit('Ongeldige beoordeling.');
      }
      try {
        $decisionRepo->save($issue, $action, null, $note, (int)$operator->operatorId, $revision);
      } catch (InvalidArgumentException|LogicException $invalid) {
        http_response_code(422); exit('Beoordeling niet toegestaan.');
      } catch (RuntimeException $conflict) {
        http_response_code(409); exit('Beoordeling gewijzigd; ververs de pagina.');
      }
      header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inzet/importcontrole').'#afwijkingen', true, 303);
      exit;
    }
    foreach ($decisionRepo->allDecisions() as $decision) {
      $decisionIndex[(int)$decision['issue_id']] = $decision;
    }
  }
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    http_response_code(503); exit('Beslissingenopslag niet geconfigureerd.');
  }
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('BREBO_INZET_REVIEW');
    session_set_cookie_params(['secure'=>true, 'httponly'=>true, 'samesite'=>'Strict', 'path'=>'/']);
    session_start();
  }
  if (!isset($_SESSION['inzet_csrf'])) $_SESSION['inzet_csrf'] = bin2hex(random_bytes(32));
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Urenverantwoording · BREBO Office test</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#182638;margin:0}main{max-width:1100px;margin:30px auto;padding:24px;background:white;border-radius:12px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:10px;border-bottom:1px solid #ddd}.inzet-kpis{display:flex;gap:16px;flex-wrap:wrap}.inzet-kpi{padding:16px;border:1px solid #ddd;border-radius:8px}.inzet-kpi span,.inzet-kpi strong{display:block}details{margin:20px 0}li{overflow-wrap:anywhere}</style></head><body><main>';
  echo '<p><a href="'.htmlspecialchars(\Brebo\Office\Intake\OfficeMount::route('home'), ENT_QUOTES, 'UTF-8').'">← Terug naar Office</a></p>';
  echo \Brebo\Office\Inzet\OfficeInzetSourceReviewWorkbench::render($report, $filters, $decisionIndex, $decisionStoreReady ? $_SESSION['inzet_csrf'] : null);
  echo '</main></body></html>';
} catch (Throwable $exception) {
  http_response_code(503);
  exit('Testwerkbank tijdelijk niet beschikbaar.');
}
