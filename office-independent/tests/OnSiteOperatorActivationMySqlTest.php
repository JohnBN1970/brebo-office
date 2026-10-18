<?php
declare(strict_types=1);
$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!is_string($dsn) || !str_starts_with($dsn,'mysql:')) throw new RuntimeException('Isolated MySQL required');
$db = new PDO($dsn,getenv('OFFICE_INTAKE_TEST_USER') ?: '',getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$sql = file_get_contents(__DIR__.'/../deployment/hostinger/sql/onsite-presence-mysql.sql');
if ($sql === false) throw new RuntimeException('Missing schema');
foreach (explode(';',preg_replace('/^--.*$/m','',$sql)) as $statement) if (trim($statement)!=='') $db->exec($statement);
foreach ([
  'Intake/WebsiteCrmOperatorContext.php',
  'Inzet/OnSiteActivationIssuer.php',
  'Inzet/OnSiteOperatorActivationService.php',
] as $name) require_once __DIR__.'/../src/'.$name;
$id = 'enroll-test-'.bin2hex(random_bytes(5));
$db->prepare('INSERT INTO onsite_employees (employee_id,display_name,language,active) VALUES (?,?,?,1)')->execute([$id,'Test','nl']);
$operator = new Brebo\Office\Intake\WebsiteCrmOperatorContext('operator-no-grant','test-session',true);
$service = new Brebo\Office\Inzet\OnSiteOperatorActivationService($db,new Brebo\Office\Inzet\OnSiteActivationIssuer($db));
try { $service->issue($operator,$id); throw new RuntimeException('Unauthorized enrollment accepted'); }
catch (RuntimeException $e) { if ($e->getMessage()==='Unauthorized enrollment accepted') throw $e; }
echo "OnSiteOperatorActivationMySqlTest OK\n";
