<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeResidentAddressUploadProcessor.php';
use Brebo\Office\Buildings\OfficeResidentAddressUploadProcessor as Processor;

// Fail-closed checks run before parsing. An invalid upload path must never
// trigger either scanner or resident-data callback.
$server = ['REQUEST_METHOD'=>'POST','HTTPS'=>'on'];
$upload = ['error'=>UPLOAD_ERR_OK,'tmp_name'=>'/nonexistent/file.csv','name'=>'addresses.csv','size'=>120];
$scannerCalls=0;
$grantCalls=0;
$scanner=static function () use (&$scannerCalls): bool { $scannerCalls++; return true; };
$grant=static function () use (&$grantCalls): bool { $grantCalls++; return true; };
$cases = [
  ['POST required', ['REQUEST_METHOD'=>'GET','HTTPS'=>'on'], 'token','token',true,1],
  ['HTTPS required', ['REQUEST_METHOD'=>'POST','HTTPS'=>'off'], 'token','token',true,1],
  ['CSRF validation failed', $server,'wrong','token',true,1],
  ['Building manage permission required', $server,'token','token',false,1],
  ['Building manage permission required', $server,'token','token',true,0],
];
foreach ($cases as [$message,$request,$provided,$expected,$manage,$building]) {
  try {
    Processor::preview($request,$upload,$provided,$expected,$building,$manage,'actor','doc',$scanner,$grant);
    throw new RuntimeException('Invalid request accepted: '.$message);
  } catch (DomainException $e) {
    if ($e->getMessage()!==$message) throw $e;
  }
}
if ($scannerCalls!==0 || $grantCalls!==0) throw new RuntimeException('Callbacks ran before upload validation');
echo "OFFICE_RESIDENT_ADDRESS_UPLOAD_PROCESSOR_OK\n";
