<?php

declare(strict_types=1);

if(PHP_SAPI!=='cli') exit(2);
$mode=$argv[1]??'';
if(!in_array($mode,['--dry-run','--apply'],true)) exit(2);
$file=getenv('OFFICE_TEST_CONFIG_PATH');
if(!is_string($file)||!is_file($file)) exit(2);
$c=require $file;
if(!is_array($c)) exit(2);
$dsn=$c['OFFICE_INTAKE_PDO_DSN']??'';
if(!is_string($dsn)||!preg_match('/(?:^|;)dbname=([a-zA-Z0-9_]+)/',$dsn,$m)
    ||$m[1]!=='u213420663_office_test'
    ||($c['OFFICE_BASE_PATH']??'')!=='/office-test'
    ||!preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);/',$dsn)) exit(3);
$root=is_file(__DIR__.'/src/Demo/OfficeDemoQuantitySeeder.php')?__DIR__:dirname(__DIR__,2);
require_once $root.'/src/Demo/OfficeDemoQuantitySeeder.php';
$db=new PDO($dsn,(string)($c['OFFICE_INTAKE_DB_USER']??''),(string)($c['OFFICE_INTAKE_DB_PASSWORD']??''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
try {
    $result=(new \Brebo\Office\Demo\OfficeDemoQuantitySeeder($db))->run($mode==='--apply');
    foreach($result as $name=>$value) echo "$name=$value\n";
} catch(\Throwable $e) {
    fwrite(STDERR,$e->getMessage()."\n");
    exit(1);
}
