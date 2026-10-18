<?php
declare(strict_types=1);
/**
 * Execute the actual HTTP boundary in an isolated PHP subprocess.
 * All cases intentionally deny access before any database connection.
 */
$entry = realpath(__DIR__ . '/../public/onsite-presence.php');
if ($entry === false) throw new RuntimeException('OnSite entry missing');
$script = tempnam(sys_get_temp_dir(), 'onsite-http-');
if ($script === false) throw new RuntimeException('Unable to create test script');
$runner = <<<'PHP'
<?php
$_SERVER['REQUEST_METHOD'] = getenv('CASE_METHOD') ?: 'POST';
$_SERVER['HTTP_HOST'] = getenv('CASE_HOST') ?: 'office.brebobv.nl';
$_SERVER['HTTPS'] = getenv('CASE_HTTPS') ?: 'on';
$_SERVER['CONTENT_TYPE'] = getenv('CASE_CONTENT_TYPE') ?: 'application/json';
$_SERVER['HTTP_AUTHORIZATION'] = getenv('CASE_AUTH') ?: '';
require getenv('CASE_ENTRY');
PHP;
file_put_contents($script, $runner);
try {
  $cases = [
    [['CASE_METHOD'=>'GET','OFFICE_ONSITE_PRESENCE_ENABLED'=>'enabled','OFFICE_ONSITE_SCHEMA_VERIFIED'=>'enabled'], 'method_not_allowed'],
    [['CASE_METHOD'=>'POST','OFFICE_ONSITE_PRESENCE_ENABLED'=>'disabled','OFFICE_ONSITE_SCHEMA_VERIFIED'=>'enabled'], 'onsite_not_enabled'],
    [['CASE_METHOD'=>'POST','OFFICE_ONSITE_PRESENCE_ENABLED'=>'enabled','OFFICE_ONSITE_SCHEMA_VERIFIED'=>'disabled'], 'onsite_not_enabled'],
    [['CASE_METHOD'=>'POST','OFFICE_ONSITE_PRESENCE_ENABLED'=>'enabled','OFFICE_ONSITE_SCHEMA_VERIFIED'=>'enabled','CASE_HOST'=>'evil.example'], 'invalid_origin'],
    [['CASE_METHOD'=>'POST','OFFICE_ONSITE_PRESENCE_ENABLED'=>'enabled','OFFICE_ONSITE_SCHEMA_VERIFIED'=>'enabled','CASE_HOST'=>'office.brebobv.nl','CASE_CONTENT_TYPE'=>'text/plain'], 'json_required'],
    [['CASE_METHOD'=>'POST','OFFICE_ONSITE_PRESENCE_ENABLED'=>'enabled','OFFICE_ONSITE_SCHEMA_VERIFIED'=>'enabled','CASE_HOST'=>'office.brebobv.nl','CASE_CONTENT_TYPE'=>'application/json'], 'device_not_linked'],
  ];
  foreach ($cases as [$vars,$expected]) {
    $env = array_merge($_ENV, [
      'CASE_ENTRY'=>$entry, 'OFFICE_PUBLIC_HOST'=>'office.brebobv.nl',
      'CASE_METHOD'=>'POST','CASE_HOST'=>'office.brebobv.nl','CASE_HTTPS'=>'on',
      'CASE_CONTENT_TYPE'=>'application/json','CASE_AUTH'=>'',
      'OFFICE_ONSITE_PRESENCE_ENABLED'=>'disabled','OFFICE_ONSITE_SCHEMA_VERIFIED'=>'disabled',
    ], $vars);
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
    $process = proc_open($command, [1=>['pipe','w'],2=>['pipe','w']], $pipes, null, $env);
    if (!is_resource($process)) throw new RuntimeException('Unable to execute HTTP case');
    $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0 || !str_contains($out, '"error":"' . $expected . '"')) {
      throw new RuntimeException('Unexpected HTTP boundary response for ' . $expected . ': ' . $out . ' ' . $err);
    }
  }
} finally { unlink($script); }
echo "OnSitePresenceHttpRuntimeTest OK\n";
