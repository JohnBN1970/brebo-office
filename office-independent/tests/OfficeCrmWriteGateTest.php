<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Intake/WebsiteCrmOperatorContext.php';
require_once dirname(__DIR__) . '/src/Crm/OfficeCrmWriteGate.php';
use Brebo\Office\Crm\OfficeCrmWriteGate;
use Brebo\Office\Intake\WebsiteCrmOperatorContext;
$editor = new WebsiteCrmOperatorContext('17','server-session-abc',true);
$reader = new WebsiteCrmOperatorContext('18','server-session-def',false);
$secret = str_repeat('s', 40);
$token = OfficeCrmWriteGate::token($editor,$secret,'create-company');
OfficeCrmWriteGate::authorize($editor,'POST','brebobv.nl','brebobv.nl','https://brebobv.nl',$token,$secret,'create-company');
$cases = [
  [$reader,'POST','brebobv.nl','https://brebobv.nl',$token,'create-company'],
  [$editor,'GET','brebobv.nl','https://brebobv.nl',$token,'create-company'],
  [$editor,'POST','evil.example','https://evil.example',$token,'create-company'],
  [$editor,'POST','brebobv.nl','',''.$token,'create-company'],
  [$editor,'POST','brebobv.nl','https://brebobv.nl','bad-token','create-company'],
  [$editor,'POST','brebobv.nl','https://brebobv.nl',$token,'create-contact'],
];
foreach ($cases as [$operator,$method,$host,$origin,$candidate,$action]) {
  try {
    OfficeCrmWriteGate::authorize($operator,$method,$host,'brebobv.nl',$origin,$candidate,$secret,$action);
    throw new RuntimeException('Denied request unexpectedly authorized');
  } catch (DomainException $e) {
    // Expected.
  }
}
echo "OfficeCrmWriteGateTest OK\n";
