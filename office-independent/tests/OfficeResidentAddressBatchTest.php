<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeResidentAddressBatch.php';
use Brebo\Office\Buildings\OfficeResidentAddressBatch as Batch;
$rows = [
  ['street'=>'Kerkstraat','house_number'=>'1','postal_code'=>'1234 AB','resident_name'=>'Persoon A'],
  ['street'=>'Kerkstraat','house_number'=>'1','postal_code'=>'1234AB','resident_name'=>'Persoon B'],
  ['street'=>'Kerkstraat','house_number'=>'2','postal_code'=>'1234AB'],
  ['street'=>'Kerkstraat','house_number'=>'3'],
  ['street'=>'Kerkstraat','house_number'=>'4','postal_code'=>'invalid'],
];
$r=Batch::propose($rows,'xlsx','file-123');
if (count($r['proposals'])!==2 || count($r['needs_review'])!==2 || count($r['errors'])!==1) {
  throw new RuntimeException('Batch classification failed');
}
if (($r['needs_review'][0]['reason']??'')!=='duplicate_address') {
  throw new RuntimeException('Conflicting residents must be reviewed');
}
if ($r['proposals'][1]['proposal']['resident']['name']!==null) {
  throw new RuntimeException('Address without resident must be allowed');
}
try { Batch::propose($rows,'xlsx','file-123',2); throw new RuntimeException('Limit bypassed'); }
catch (LengthException $expected) {}
echo "OFFICE_RESIDENT_ADDRESS_BATCH_OK\n";
