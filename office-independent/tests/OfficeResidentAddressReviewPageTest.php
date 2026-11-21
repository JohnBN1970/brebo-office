<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeResidentAddressReviewPage.php';
use Brebo\Office\Buildings\OfficeResidentAddressReviewPage as Page;
$file=tempnam(sys_get_temp_dir(),'office-review-');
file_put_contents($file,"Straatnaam;Huisnummer;Postcode;Woonplaats;Bewonersnaam\nKerkstraat;10;1234AB;Utrecht;Persoon A\n");
try {
  $denied=false;
  try { Page::preview($file,'bewoners.csv','doc-123','user-1',static fn() => false); }
  catch (RuntimeException $e) { $denied=true; }
  if (!$denied) throw new RuntimeException('Denied reviewer was allowed');
  $html=Page::preview($file,'bewoners.csv','doc-123','user-1',static fn($actor,$document) => $actor==='user-1' && $document==='doc-123');
  if (strpos($html,'Kerkstraat 10 1234AB Utrecht')===false || strpos($html,'Persoon A')===false) {
    throw new RuntimeException('Import review preview missing data');
  }
  if (strpos($html,'Nieuwe adresvoorstellen (1)')===false) throw new RuntimeException('Preview classification missing');
} finally { unlink($file); }
echo "OFFICE_RESIDENT_ADDRESS_REVIEW_PAGE_OK\n";
