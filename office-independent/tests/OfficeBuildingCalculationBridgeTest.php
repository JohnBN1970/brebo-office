<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingCalculationBridge.php';
use Brebo\Office\Buildings\OfficeBuildingCalculationBridge;

$links=OfficeBuildingCalculationBridge::buildingLinks(17);
if ($links['inventory']!=='/office-test/gebouwen?id=17' || !str_contains($links['quantities_print'],'quantity_print=1')) {
  throw new RuntimeException('invalid_building_navigation');
}
try {
  OfficeBuildingCalculationBridge::buildingLinks(0);
  throw new RuntimeException('invalid building accepted');
} catch (InvalidArgumentException $expected) {}
$source=['source_kind'=>'building_inventory','rows'=>[['component_code'=>'K01','quantity'=>'2.000','unit'=>'st']]];
$first=OfficeBuildingCalculationBridge::fingerprint($source);
if ($first!==OfficeBuildingCalculationBridge::fingerprint($source)) throw new RuntimeException('unstable fingerprint');
$source['rows'][0]['quantity']='3.000';
if ($first===OfficeBuildingCalculationBridge::fingerprint($source)) throw new RuntimeException('changed quantity not detected');
echo "Building calculation bridge checks OK\n";
