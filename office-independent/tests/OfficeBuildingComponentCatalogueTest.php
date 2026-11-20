<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingComponentCatalogue.php';
use Brebo\Office\Buildings\OfficeBuildingComponentCatalogue;

foreach (['NL-SFB','STABU','FREE'] as $system) {
  if (OfficeBuildingComponentCatalogue::codeSystem(strtolower($system))!==$system) {
    throw new RuntimeException('invalid_code_system_normalization');
  }
}
foreach (['UNKNOWN','', 'NL SFB'] as $system) {
  try {
    OfficeBuildingComponentCatalogue::codeSystem($system);
    throw new RuntimeException('unsupported_code_system_accepted');
  } catch (InvalidArgumentException $expected) {}
}
$sql=implode("\n",OfficeBuildingComponentCatalogue::statements());
foreach (['office_building_component_definitions','dwelling_type_id','demarcation_id','code_system','source_kind','approval_status'] as $field) {
  if (!str_contains($sql,$field)) throw new RuntimeException('missing_component_schema_field_'.$field);
}
echo "Building component catalogue contract OK\n";
