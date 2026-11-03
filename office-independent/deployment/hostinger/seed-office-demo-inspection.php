<?php

declare(strict_types=1);

require_once __DIR__.'/../../src/Demo/OfficeDemoProcessChain.php';
require_once __DIR__.'/../../src/Demo/OfficeDemoInspectionSeeder.php';

use Brebo\Office\Demo\OfficeDemoProcessChain;
use Brebo\Office\Demo\OfficeDemoInspectionSeeder;

if (PHP_SAPI !== 'cli') exit(2);
$mode = $argv[1] ?? '';
if (!in_array($mode, ['--dry-run','--apply'],true)) exit(2);
$file = getenv('OFFICE_TEST_CONFIG_PATH');
if (!is_string($file) || !is_file($file)) exit(2);
$config = require $file;
if (!is_array($config)) exit(2);
$dsn = $config['OFFICE_INTAKE_PDO_DSN'] ?? '';
if (!is_string($dsn) || !preg_match('/(?:^|;)dbname=([a-zA-Z0-9_]+)/', $dsn, $matches)) exit(2);
OfficeDemoProcessChain::assertTestEnvironment((string)($config['OFFICE_BASE_PATH'] ?? ''),$matches[1]);
$db = new PDO($dsn,(string)($config['OFFICE_INTAKE_DB_USER']??''),(string)($config['OFFICE_INTAKE_DB_PASSWORD']??''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
try {
    $result = (new OfficeDemoInspectionSeeder($db))->seed($mode === '--apply');
    foreach ($result as $key=>$value) echo $key.'='.($value===true?'yes':($value===false?'no':$value))."\n";
} catch (\Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
