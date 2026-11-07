<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeResidentAddressIntakeAdapter.php';
use Brebo\Office\Buildings\OfficeResidentAddressIntakeAdapter as Adapter;
$rows=[
  2=>['Straatnaam'=>'Marktstraat','Huisnummer'=>'1','Postcode'=>'1234AB','Woonplaats'=>'Utrecht','Bewonersnaam'=>'Test A'],
  3=>['Straatnaam'=>'Marktstraat','Huisnummer'=>'1','Postcode'=>'1234 AB','Woonplaats'=>'Utrecht','Bewonersnaam'=>'Test B'],
  4=>['Straatnaam'=>'Marktstraat','Huisnummer'=>'2','Postcode'=>'1234AB','Woonplaats'=>'Utrecht'],
  5=>['Straat'=>'X','Straatnaam'=>'Y','Huisnummer'=>'3','Postcode'=>'1234AB'],
];
$r=Adapter::fromExtractedRows($rows,'xlsx','file-1');
if (count($r['proposals'])!==1 || count($r['needs_review'])!==2 || count($r['errors'])!==1) {
  throw new RuntimeException('Adapter classifications failed');
}
if ($r['proposals'][0]['proposal']['source']['id']!=='file-1:row:4') {
  throw new RuntimeException('Source row provenance lost');
}
if ($r['proposals'][0]['proposal']['resident']['name']!==null) {
  throw new RuntimeException('Optional resident incorrectly required');
}
echo "OFFICE_RESIDENT_ADDRESS_INTAKE_ADAPTER_OK\n";
