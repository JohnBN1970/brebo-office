<?php

declare(strict_types=1);

/** HTTP contract checks for the disabled-by-default Office Calc launch endpoint. */
$endpoint = realpath(__DIR__ . '/../public/calc-start.php');
if ($endpoint === false) {
  throw new RuntimeException('Calc start endpoint missing.');
}
function launchRequest(string $endpoint, string $method, string $uri, array $env = []): array {
  $script = '$_SERVER["REQUEST_METHOD"]=' . var_export($method, true) . ';'
    . '$_SERVER["REQUEST_URI"]=' . var_export($uri, true) . ';'
    . 'register_shutdown_function(static function (): void { fwrite(STDERR, "HTTP_STATUS=" . (http_response_code() ?: 200)); });'
    . 'require ' . var_export($endpoint, true) . ';';
  $proc = proc_open(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script),
    [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, null,
    array_merge($_ENV, ['OFFICE_CALC_LAUNCH_ENABLED' => 'disabled'], $env));
  if (!is_resource($proc)) {
    throw new RuntimeException('Failed to start PHP subprocess.');
  }
  fclose($pipes[0]);
  $body = stream_get_contents($pipes[1]);
  $error = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  $exit = proc_close($proc);
  if ($exit !== 0 || !preg_match('/^HTTP_STATUS=([0-9]{3})$/', $error, $matches)) {
    throw new RuntimeException('Endpoint process failed: ' . $error);
  }
  return ['status' => (int) $matches[1], 'body' => $body];
}
$path = '/calc/start';
$wrongMethod = launchRequest($endpoint, 'GET', $path);
if ($wrongMethod['status'] !== 405 || json_decode($wrongMethod['body'], true, 512, JSON_THROW_ON_ERROR)['error'] !== 'Methode niet toegestaan.') {
  throw new RuntimeException('Calc launch must reject GET.');
}
$wrongPath = launchRequest($endpoint, 'POST', '/calc/start/extra');
if ($wrongPath['status'] !== 404) {
  throw new RuntimeException('Calc launch must reject unexpected paths.');
}
$disabled = launchRequest($endpoint, 'POST', $path);
if ($disabled['status'] !== 503 || json_decode($disabled['body'], true, 512, JSON_THROW_ON_ERROR)['error'] !== 'Calc-start niet beschikbaar.') {
  throw new RuntimeException('Calc launch must stay disabled by default.');
}
$invalidOrigin = launchRequest($endpoint, 'POST', $path, [
  'OFFICE_CALC_LAUNCH_ENABLED' => 'enabled',
  'OFFICE_PUBLIC_HOST' => 'office.example.test',
]);
if ($invalidOrigin['status'] !== 403) {
  throw new RuntimeException('Calc launch must reject requests without same-origin HTTPS.');
}
echo "Office Calc launch HTTP denial checks passed.\n";
