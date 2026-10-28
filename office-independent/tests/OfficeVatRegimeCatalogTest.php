<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Finance/OfficeVatRegimeCatalog.php';

use Brebo\Office\Finance\OfficeVatRegimeCatalog;

putenv('OFFICE_VAT_REGIMES_SCHEMA_VERIFIED=disabled');
$catalog = new OfficeVatRegimeCatalog(new PDO('sqlite::memory:'));
try {
  $catalog->activeOptions();
  throw new RuntimeException('Unverified VAT settings were accepted');
} catch (DomainException $e) {
  // Expected: no fallback tax rates.
}
putenv('OFFICE_VAT_REGIMES_SCHEMA_VERIFIED=enabled');
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_vat_regimes (code TEXT, label TEXT, active INTEGER, sort_order INTEGER)');
$db->exec("INSERT INTO office_vat_regimes VALUES ('NL_21','21 procent',1,1),('DISABLED','No longer active',0,2)");
$options = (new OfficeVatRegimeCatalog($db))->activeOptions();
if ($options !== ['NL_21'=>'21 procent']) throw new RuntimeException('VAT settings mismatch');
echo "Office VAT regime catalog OK\n";
