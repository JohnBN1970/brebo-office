<?php

declare(strict_types=1);

/**
 * Exercise the actual standalone HTTP script in a separate PHP process.
 * No live database or production credentials are needed for denied paths.
 */
$endpoint = realpath(__DIR__ . '/../public/calc-project-context.php');
if ($endpoint === false) {
  throw new RuntimeException('Missing Calc context endpoint.');
}
function requestEndpoint(string $endpoint, string $method, string $path, array $env = []): string {
  $bootstrap = '$_SERVER["REQUEST_METHOD"] = ' . var_export($method, true) . ';'
    . '$_SERVER["REQUEST_URI"] = ' . var_export($path, true) . ';'
    . 'require ' . var_export($endpoint, true) . ';';
  $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($bootstrap);
  $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
  $process = proc_open($command, $descriptors, $pipes, null, array_merge($_ENV, [
    'OFFICE_CALC_CONTEXT_ENABLED' => 'disabled',
    'OFFICE_CALC_SHARED_SECRET' => '',
    'OFFICE_INTAKE_PDO_DSN' => '',
  ], $env));
  if (!is_resource($process)) {
    throw new RuntimeException('Could not start PHP HTTP endpoint test.');
  }
  fclose($pipes[0]);
  $output = stream_get_contents($pipes[1]);
  $errors = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  $code = proc_close($process);
  if ($code !== 0 || $errors !== '') {
    throw new RuntimeException('Endpoint process failed: ' . $errors);
  }
  return $output;
}
$path = '/api/workbench/v1/projects/42/calculation-context';
$disabled = json_decode(requestEndpoint($endpoint, 'GET', $path), true, 512, JSON_THROW_ON_ERROR);
if ($disabled['error'] !== 'Projectcontext niet beschikbaar.') {
  throw new RuntimeException('Feature-disabled endpoint was not denied.');
}
$method = json_decode(requestEndpoint($endpoint, 'POST', $path), true, 512, JSON_THROW_ON_ERROR);
if ($method['error'] !== 'Methode niet toegestaan.') {
  throw new RuntimeException('Wrong HTTP method was not rejected.');
}
$notFound = json_decode(requestEndpoint($endpoint, 'GET', '/api/workbench/v1/projects/0/calculation-context'), true, 512, JSON_THROW_ON_ERROR);
if ($notFound['error'] !== 'Niet gevonden.') {
  throw new RuntimeException('Invalid project path was not rejected.');
}
echo "Office Calc context HTTP denial checks passed.\n";
