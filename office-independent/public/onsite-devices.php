<?php
declare(strict_types=1);
// Office-native read-only device enrollment console. No public enrollment.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['GET','POST'], true)) {
  header('Allow: GET, POST');
  http_response_code(405);
  exit;
}
if (getenv('OFFICE_ONSITE_ADMIN_ENABLED') !== 'enabled'
  || getenv('OFFICE_ONSITE_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled') {
  http_response_code(503);
  exit('OnSite-beheer is nog niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed === '' || !hash_equals($allowed,(string)$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit;
}
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
    require_once dirname(__DIR__).'/src/Intake/'.$file;
  }
  $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
  if (!is_string($dsn) || !str_starts_with($dsn,'mysql:')) throw new RuntimeException('Office database unavailable');
  $db = new PDO($dsn,getenv('OFFICE_INTAKE_DB_USER') ?: '',getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
  ]);
  $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time()
  );
  $operator = $provider->currentOperator();
  if ($operator === null) {
    http_response_code(401);
    exit('Meld je aan bij Office.');
  }
  $grant = $db->prepare('SELECT 1 FROM onsite_enrollment_operator_grants WHERE operator_id = ? AND active = 1 LIMIT 1');
  $grant->execute([$operator->operatorId]);
  if ($grant->fetchColumn() === false) {
    http_response_code(403);
    exit('Geen toegang tot apparaatbeheer.');
  }
  $issueResult = null;
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (getenv('OFFICE_ONSITE_ADMIN_ISSUE_ENABLED') !== 'enabled') {
      http_response_code(403);
      exit('Uitgifte is nog niet beschikbaar.');
    }
    $csrf = $_POST['csrf'] ?? null;
    $cookie = $_COOKIE['onsite_issue_csrf'] ?? null;
    $employeeId = $_POST['employee_id'] ?? null;
    $confirmation = $_POST['confirmation'] ?? null;
    if (!is_string($csrf) || !is_string($cookie) || strlen($csrf) !== 64
      || !ctype_xdigit($csrf) || !hash_equals($cookie,$csrf)
      || !is_string($employeeId) || $employeeId === ''
      || $confirmation !== 'ISSUE') {
      http_response_code(403);
      exit('Ongeldige aanvraag of bevestiging.');
    }
    // Require an actual same-origin browser request in addition to CSRF.
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!is_string($origin) || !hash_equals('https://'.$allowed,$origin)) {
      http_response_code(403);
      exit('Ongeldige herkomst.');
    }
    foreach (['OnSiteActivationIssuer.php','OnSiteOperatorActivationService.php'] as $file) {
      require_once dirname(__DIR__).'/src/Inzet/'.$file;
    }
    $issuer = new \\Brebo\\Office\\Inzet\\OnSiteOperatorActivationService(
      $db,new \\Brebo\\Office\\Inzet\\OnSiteActivationIssuer($db)
    );
    $issueResult = $issuer->issue($operator,$employeeId);
  }
  $employees = $db->query('SELECT e.employee_id,e.display_name,COUNT(t.token_hash) AS device_count
    FROM onsite_employees e
    LEFT JOIN onsite_device_tokens t ON t.employee_id = e.employee_id AND t.revoked_at IS NULL
    WHERE e.active = 1
    GROUP BY e.employee_id,e.display_name ORDER BY e.display_name LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  error_log('OnSite device console unavailable: '.get_class($e));
  http_response_code(503);
  exit('OnSite-beheer is tijdelijk niet beschikbaar.');
}
header('Content-Type: text/html; charset=utf-8');
$h = static fn(string $s): string => htmlspecialchars($s,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1><title>Apparaatbeheer · BREBO Office</title><style>body{font:16px system-ui;background:#f4f6fa;color:#172b42;margin:0;padding:2rem}main{max-width:900px;margin:auto;background:white;border-radius:14px;padding:2rem}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #ddd}p{color:#536174}</style></head><body><main><h1>OnSite · Apparaatbeheer</h1><p>Alleen bevoegde Office-gebruikers. Apparaten worden niet automatisch gekoppeld.</p><table><thead><tr><th>Medewerker</th><th>Medewerker-ID</th><th>Actieve apparaten</th></tr></thead><tbody>';
foreach ($employees as $employee) {
  echo '<tr><td>'.$h((string)$employee['display_name']).'</td><td>'.$h((string)$employee['employee_id']).'</td><td>'.(int)$employee['device_count'].'</td></tr>';
}
echo '</tbody></table>';
if ($issueResult !== null) {
  echo '<section><h2>Eenmalige activatiecode</h2><p>Deze code wordt slechts eenmaal getoond. Deel deze alleen via een gecontroleerd kanaal met de bedoelde medewerker.</p><p><code>'.$h($issueResult['activation_token']).'</code></p><p>Geldig tot: '.$h($issueResult['expires_at']).'</p></section>';
}
if (getenv('OFFICE_ONSITE_ADMIN_ISSUE_ENABLED') === 'enabled') {
  $csrf = bin2hex(random_bytes(32));
  setcookie('onsite_issue_csrf',$csrf,[
    'expires'=>time()+600,'path'=>\\Brebo\\Office\\Intake\\OfficeMount::basePath(),
    'secure'=>true,'httponly'=>true,'samesite'=>'Strict',
  ]);
  echo '<section><h2>Apparaat koppelen</h2><p>Controleer eerst de identiteit van de medewerker. Uitgifte wordt geregistreerd.</p><form method="post"><label>Medewerker <select name="employee_id" required>';
  foreach ($employees as $employee) {
    echo '<option value="'.$h((string)$employee['employee_id']).'">'.$h((string)$employee['display_name']).'</option>';
  }
  echo '</select></label><input type="hidden" name="csrf" value="'.$h($csrf).'"><label><input type="checkbox" name="confirmation" value="ISSUE" required> Identiteit en koppeling gecontroleerd</label><button type="submit">Eenmalige code uitgeven</button></form></section>';
} else {
  echo '<p>Uitgifte van activatiecodes is nog niet ingeschakeld.</p>';
}
echo '</main></body></html>';
