<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeResidentAddressSchema.php';
use Brebo\Office\Buildings\OfficeResidentAddressSchema;
$sql=OfficeResidentAddressSchema::statements();
if (count($sql)!==4) throw new RuntimeException('Unexpected schema statement count');
$all=implode("\n",$sql);
foreach (['office_building_addresses','office_address_residents','office_address_intake_proposals','office_address_resident_audit','FOREIGN KEY','UNIQUE KEY','source_id','reviewed_by'] as $required) {
  if (strpos($all,$required)===false) throw new RuntimeException('Missing schema requirement: '.$required);
}
foreach (['DROP TABLE','TRUNCATE','ALTER TABLE office_buildings','DELETE FROM office_buildings'] as $forbidden) {
  if (stripos($all,$forbidden)!==false) throw new RuntimeException('Destructive DDL detected');
}
echo "OFFICE_RESIDENT_ADDRESS_SCHEMA_OK\n";
