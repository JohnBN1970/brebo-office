<?php

declare(strict_types=1);

foreach ([
  'Intake/WebsiteOfficeOperatorAccountSchema.php',
  'Intake/WebsiteOfficeCrmSessionSchema.php',
  'Calculation/OfficeCalculationPermissionSchema.php',
  'Calculation/OfficeCalcActorMappingSchema.php',
  'Calculation/OfficeCalcProjectScopeSchema.php',
] as $file) {
  require_once dirname(__DIR__) . '/src/' . $file;
}
$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
$user = getenv('OFFICE_INTAKE_TEST_USER');
$password = getenv('OFFICE_INTAKE_TEST_PASSWORD');
if (!$dsn || $user === false || $password === false) {
  throw new RuntimeException('Calc launch integration test requires isolated MySQL test credentials.');
}
$db = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
\Brebo\Office\Intake\WebsiteOfficeOperatorAccountSchema::install($db);
\Brebo\Office\Intake\WebsiteOfficeCrmSessionSchema::install($db);
\Brebo\Office\Calculation\OfficeCalculationPermissionSchema::install($db);
\Brebo\Office\Calculation\OfficeCalcActorMappingSchema::install($db);
\Brebo\Office\Calculation\OfficeCalcProjectScopeSchema::install($db);
$operator = 'calc-http-integration-test';
$token = bin2hex(random_bytes(32));
$secret = str_repeat('x', 40);
$db->prepare('INSERT INTO office_operator_accounts (id,username,password_hash,active) VALUES (?,?,?,1)')
  ->execute([$operator, $operator, 'unused-test-hash']);
$db->prepare('INSERT INTO office_operator_sessions (token_hash,operator_id,expires_at) VALUES (?,?,?)')
  ->execute([hash('sha256', $token), $operator, gmdate('Y-m-d H:i:s', time() + 3600)]);
$db->prepare('INSERT INTO office_calculation_permissions (operator_id,can_calculate) VALUES (?,1)')->execute([$operator]);
$db->prepare('INSERT INTO office_calc_actor_mapping (operator_id,calc_actor_id) VALUES (?,?)')->execute([$operator, 991234]);
$db->exec('INSERT INTO office_calc_projects (project_id) VALUES (991234)');
$db->exec('INSERT INTO office_calc_calculations (calculation_id,project_id) VALUES (991235,991234)');
$db->prepare('INSERT INTO office_calc_project_access (operator_id,project_id) VALUES (?,991234)')->execute([$operator]);

$endpoint = realpath(__DIR__ . '/../public/calc-start.php');
if ($endpoint === false) {
  throw new RuntimeException('Calc start endpoint missing.');
}
function launchHttp(string $endpoint, string $dsn, string $user, string $password, string $secret, string $session, int $projectId): array {
  $bootstrap = '$_SERVER["REQUEST_METHOD"]="POST";'
    . '$_SERVER["REQUEST_URI"]="/calc/start";'
    . '$_SERVER["HTTP_HOST"]="office.example.test";'
    . '$_SERVER["HTTP_ORIGIN"]="https://office.example.test";'
    . '$_SERVER["HTTPS"]="on";'
    . '$_COOKIE["office_session"]=' . var_export($session, true) . ';'
    . '$_POST["project_id"]=' . var_export((string) $projectId, true) . ';'
    . '$_POST["calculation_id"]="991235";'
    . 'register_shutdown_function(static function (): void { fwrite(STDERR, "HTTP_STATUS=" . (http_response_code() ?: 200)); });'
    . 'require ' . var_export($endpoint, true) . ';';
  $proc = proc_open(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($bootstrap),
    [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, null,
    array_merge($_ENV, [
      'OFFICE_CALC_LAUNCH_ENABLED' => 'enabled',
      'OFFICE_PUBLIC_HOST' => 'office.example.test',
      'OFFICE_CALC_SHARED_SECRET' => $secret,
      'OFFICE_INTAKE_PDO_DSN' => $dsn,
      'OFFICE_INTAKE_DB_USER' => $user,
      'OFFICE_INTAKE_DB_PASSWORD' => $password,
    ]));
  if (!is_resource($proc)) {
    throw new RuntimeException('Could not execute launch endpoint.');
  }
  fclose($pipes[0]);
  $body = stream_get_contents($pipes[1]);
  $error = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  $exit = proc_close($proc);
  if ($exit !== 0 || !preg_match('/^HTTP_STATUS=([0-9]{3})$/', $error, $matches)) {
    throw new RuntimeException('Launch endpoint failed: ' . $error);
  }
  return ['status' => (int) $matches[1], 'body' => json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
}
try {
  $ok = launchHttp($endpoint, $dsn, $user, $password, $secret, $token, 991234);
  if ($ok['status'] !== 200 || !is_string($ok['body']['token'] ?? null)) {
    throw new RuntimeException('Authorized Office operator did not receive a launch token.');
  }
  [$encoded, $mac] = explode('.', $ok['body']['token'], 2);
  $payload = json_decode(base64_decode(strtr($encoded, '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
  if (!hash_equals(hash_hmac('sha256', $encoded, $secret), $mac)
    || $payload['project_id'] !== 991234 || $payload['calculation_id'] !== 991235
    || $payload['actor_id'] !== 991234) {
    throw new RuntimeException('Launch token signature or scoped claims invalid.');
  }
  $missingSession = launchHttp($endpoint, $dsn, $user, $password, $secret, '', 991234);
  if ($missingSession['status'] !== 403) {
    throw new RuntimeException('Unauthenticated launch was accepted.');
  }
  $wrongProject = launchHttp($endpoint, $dsn, $user, $password, $secret, $token, 991236);
  if ($wrongProject['status'] !== 403) {
    throw new RuntimeException('Out-of-scope project launch was accepted.');
  }
  echo "Office Calc positive launch HTTP integration passed.\n";
} finally {
  $db->prepare('DELETE FROM office_operator_sessions WHERE operator_id = ?')->execute([$operator]);
  $db->prepare('DELETE FROM office_operator_accounts WHERE id = ?')->execute([$operator]);
  $db->exec('DELETE FROM office_calc_projects WHERE project_id = 991234');
}
