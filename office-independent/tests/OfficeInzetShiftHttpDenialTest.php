<?php
declare(strict_types=1);
/**
 * Black-box HTTP denial smoke. The isolated shift endpoint must remain closed
 * unless the verified test mount and all write flags are enabled.
 */
$root = dirname(__DIR__);
$endpoint = $root . '/public/office-inzet-shift-create.php';
$router = file_get_contents($root . '/deployment/hostinger/index.php');
if (!is_string($router) || !str_contains($router, "'/office-test/inzet/dienst/nieuw'")) {
  throw new RuntimeException('Explicit test route missing');
}
$run = static function(array $environment, string $method) use ($endpoint): array {
  $script = '<?php '
    . '$_SERVER["REQUEST_METHOD"]=' . var_export($method,true) . ';'
    . '$_SERVER["HTTP_HOST"]="office.example.test";'
    . '$_SERVER["HTTPS"]="on";'
    . '$_SERVER["HTTP_ORIGIN"]="https://office.example.test";'
    . 'require ' . var_export($endpoint,true) . ';';
  $tmp = tempnam(sys_get_temp_dir(),'inzet-http-');
  if ($tmp === false) throw new RuntimeException('Temporary script unavailable');
  file_put_contents($tmp,$script);
  $command = 'php ' . escapeshellarg($tmp);
  $descriptors = [1=>['pipe','w'],2=>['pipe','w']];
  $process = proc_open($command,$descriptors,$pipes,null,$environment);
  if (!is_resource($process)) { unlink($tmp); throw new RuntimeException('Cannot start PHP'); }
  $stdout = stream_get_contents($pipes[1]);
  $stderr = stream_get_contents($pipes[2]);
  fclose($pipes[1]); fclose($pipes[2]);
  $status = proc_close($process);
  unlink($tmp);
  return [$status,$stdout,$stderr];
};
$env = [
  'PATH'=>getenv('PATH') ?: '/usr/bin:/bin',
  'OFFICE_BASE_PATH'=>'/office',
  'OFFICE_PUBLIC_HOST'=>'office.example.test',
  'OFFICE_NATIVE_PROJECTS_ENABLED'=>'enabled',
  'OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED'=>'enabled',
  'OFFICE_TEST_INZET_SHIFTS_ENABLED'=>'enabled',
  'OFFICE_TEST_INZET_SHIFTS_SCHEMA_VERIFIED'=>'enabled',
  'OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED'=>'enabled',
];
foreach ([
  [$env,'POST','Dienstinvoer niet beschikbaar.'],
  [array_replace($env,['OFFICE_BASE_PATH'=>'/office-test','OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED'=>'disabled']),'POST','Dienstinvoer niet beschikbaar.'],
  [array_replace($env,['OFFICE_BASE_PATH'=>'/office-test']),'GET','Alleen POST toegestaan.'],
] as [$vars,$method,$expected]) {
  [$status,$stdout,$stderr] = $run($vars,$method);
  if ($status !== 0 || !str_contains((string)$stdout,$expected)) {
    throw new RuntimeException('HTTP denial contract failed: ' . $expected . ' ' . $stderr);
  }
}
echo "Office Inzet shift HTTP denial gates OK\n";
