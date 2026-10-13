<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Calculation/OfficeCalcProjectScopeSchema.php';
require_once __DIR__ . '/../src/Calculation/OfficeCalcProjectContextSchema.php';
require_once __DIR__ . '/../src/Calculation/OfficeCalcRequestReplaySchema.php';

use Brebo\Office\Calculation\OfficeCalcProjectScopeSchema;
use Brebo\Office\Calculation\OfficeCalcProjectContextSchema;
use Brebo\Office\Calculation\OfficeCalcRequestReplaySchema;

$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
$user = getenv('OFFICE_INTAKE_TEST_USER');
$password = getenv('OFFICE_INTAKE_TEST_PASSWORD');
if (!$dsn || $user === false || $password === false) {
  throw new RuntimeException('Signed HTTP integration test requires MySQL test credentials.');
}
$db = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
OfficeCalcProjectScopeSchema::install($db);
OfficeCalcProjectContextSchema::install($db);
$db->exec(OfficeCalcRequestReplaySchema::MYSQL);
$db->exec("INSERT INTO office_calc_projects (project_id) VALUES (424242)");
$db->exec("INSERT INTO office_calc_project_context
(project_id,project_code,title,status,client_name,project_kind,disciplines_json,description)
VALUES (424242,'B-424242','Testgebouw','active','Testklant','renovation','[\"glass\"]','Testproject')");
$db->exec("INSERT INTO office_calc_project_context_buildings VALUES (424242,9,'Gebouw 9')");
$endpoint = realpath(__DIR__ . '/../public/calc-project-context.php');
if ($endpoint === false) {
  throw new RuntimeException('Endpoint missing.');
}
$secret = str_repeat('s', 40);
$path = '/api/workbench/v1/projects/424242/calculation-context';
function signedRequest(string $endpoint, string $path, string $secret, string $dsn, string $user, string $password, string $requestId, bool $tamper = false): array {
  $timestamp = (string) time();
  $canonical = implode("\n", ['GET', $path, hash('sha256', ''), $timestamp, $requestId]);
  $signature = hash_hmac('sha256', $canonical, $secret);
  if ($tamper) {
    $signature = str_repeat('0', 64);
  }
  $bootstrap = '$_SERVER["REQUEST_METHOD"]="GET";'
    . '$_SERVER["REQUEST_URI"]=' . var_export($path, true) . ';'
    . '$_SERVER["HTTP_X_BREBO_TIMESTAMP"]=' . var_export($timestamp, true) . ';'
    . '$_SERVER["HTTP_X_BREBO_REQUEST_ID"]=' . var_export($requestId, true) . ';'
    . '$_SERVER["HTTP_X_BREBO_SIGNATURE"]=' . var_export('v1=' . $signature, true) . ';'
    . 'register_shutdown_function(static function (): void { fwrite(STDERR, "HTTP_STATUS=" . http_response_code()); });'
    . 'require ' . var_export($endpoint, true) . ';';
  $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($bootstrap);
  $pipes = [];
  $proc = proc_open($command, [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, null, array_merge($_ENV, [
    'OFFICE_CALC_CONTEXT_ENABLED' => 'enabled',
    'OFFICE_CALC_SHARED_SECRET' => $secret,
    'OFFICE_INTAKE_PDO_DSN' => $dsn,
    'OFFICE_INTAKE_DB_USER' => $user,
    'OFFICE_INTAKE_DB_PASSWORD' => $password,
  ]));
  if (!is_resource($proc)) {
    throw new RuntimeException('Cannot run endpoint.');
  }
  fclose($pipes[0]);
  $body = stream_get_contents($pipes[1]);
  $error = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  $exit = proc_close($proc);
  if ($exit !== 0 || !preg_match('/^HTTP_STATUS=([0-9]{3})$/', $error, $matches)) {
    throw new RuntimeException('Endpoint execution failed: ' . $error);
  }
  return ['status' => (int) $matches[1], 'body' => json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
}
try {
  $id = '11111111-1111-4111-8111-111111111111';
  $ok = signedRequest($endpoint, $path, $secret, $dsn, $user, $password, $id);
  if ($ok['status'] !== 200 || $ok['body']['contract'] !== 'brebo-office-calc-context-v1'
    || $ok['body']['project']['id'] !== 424242 || $ok['body']['project']['buildings'][0]['id'] !== 9) {
    throw new RuntimeException('Signed context response invalid.');
  }
  $replay = signedRequest($endpoint, $path, $secret, $dsn, $user, $password, $id);
  if ($replay['status'] !== 403) {
    throw new RuntimeException('Replayed signed request was accepted.');
  }
  $bad = signedRequest($endpoint, $path, $secret, $dsn, $user, $password, '22222222-2222-4222-8222-222222222222', true);
  if ($bad['status'] !== 403) {
    throw new RuntimeException('Invalid signature was accepted.');
  }
  echo "Office Calc signed HTTP integration checks passed.\n";
} finally {
  $db->exec('DELETE FROM office_calc_request_nonces WHERE request_id IN ("11111111-1111-4111-8111-111111111111", "22222222-2222-4222-8222-222222222222")');
  $db->exec('DELETE FROM office_calc_projects WHERE project_id = 424242');
}
