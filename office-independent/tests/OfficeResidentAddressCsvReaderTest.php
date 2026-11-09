<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeResidentAddressCsvReader.php';
require_once dirname(__DIR__) . '/src/Buildings/OfficeResidentAddressIntakeAdapter.php';
use Brebo\Office\Buildings\OfficeResidentAddressCsvReader as Csv;
use Brebo\Office\Buildings\OfficeResidentAddressIntakeAdapter as Adapter;
$csv="\xEF\xBB\xBFStraatnaam;Huisnummer;Postcode;Woonplaats;Bewonersnaam\n"
  . "Kerkstraat;1;1234AB;Utrecht;\"Test, A\"\n"
  . "Kerkstraat;2;1234AB;Utrecht;\n";
$rows=Csv::read($csv);
if (count($rows)!==2 || $rows[2]['Bewonersnaam']!=='Test, A') throw new RuntimeException('CSV parsing failed');
$result=Adapter::fromExtractedRows($rows,'csv','file-csv-1');
if (count($result['proposals'])!==2 || $result['proposals'][0]['proposal']['source']['id']!=='file-csv-1:row:2') {
  throw new RuntimeException('CSV intake integration failed');
}
foreach (["Straat;Straat\na;b\n", "Straat;Huisnummer\na\n"] as $bad) {
  $failed=false;
  try { Csv::read($bad); } catch (InvalidArgumentException $e) { $failed=true; }
  if (!$failed) throw new RuntimeException('Malformed CSV accepted');
}
$failed=false;
try { Csv::read($csv,';',1); } catch (LengthException $e) { $failed=true; }
if (!$failed) throw new RuntimeException('CSV limit bypassed');
echo "OFFICE_RESIDENT_ADDRESS_CSV_READER_OK\n";
