<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeResidentAddressFileImport.php';
use Brebo\Office\Buildings\OfficeResidentAddressFileImport as Import;
$file=tempnam(sys_get_temp_dir(),'office-csv-');
file_put_contents($file,"Straatnaam,Huisnummer,Postcode,Woonplaats,Bewonersnaam\nKerkstraat,10,1234AB,Utrecht,Persoon A\n");
try {
  $r=Import::propose($file,'adreslijst.CSV','document-42');
  if (count($r['proposals'])!==1 || $r['proposals'][0]['proposal']['source']['id']!=='document-42:row:2') {
    throw new RuntimeException('CSV import facade failed');
  }
  $blocked=false;
  try { Import::propose($file,'adreslijst.pdf','document-42'); }
  catch (InvalidArgumentException $e) { $blocked=true; }
  if (!$blocked) throw new RuntimeException('Unsupported file extension accepted');
  $blocked=false;
  try { Import::propose($file,'adreslijst.csv',''); }
  catch (InvalidArgumentException $e) { $blocked=true; }
  if (!$blocked) throw new RuntimeException('Missing provenance accepted');
} finally { unlink($file); }
echo "OFFICE_RESIDENT_ADDRESS_FILE_IMPORT_OK\n";
