<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/Demo/OfficeDemoCalcV2Boundary.php';
use Brebo\Office\Demo\OfficeDemoCalcV2Boundary;

$handoff=[
    'contract'=>'brebo-office-demo-calc-handoff-v1',
    'project_code'=>'TEST-2026-001','project_id'=>1,'scope_id'=>1,
    'building_id'=>1,'housing_type_id'=>1,'calculation_id'=>null,
    'status'=>'AWAITING_CANONICAL_CALC_LINK',
    'quantity_basis'=>'per_dwelling','quantity_source'=>'synthetic_demo_not_measured',
    'price_status'=>'unpriced','lines'=>[],
];
foreach(['DEMO-B01','DEMO-B02','DEMO-B03'] as $code) $handoff['lines'][]=[
    'source_finding_code'=>$code,'description'=>'Test','unit'=>'st','quantity_per_dwelling'=>1.0,
];
$result=OfficeDemoCalcV2Boundary::prepare($handoff);
if($result['status']!=='BLOCKED_CALC_AUTHORITY_REQUIRED'||$result['external_write_allowed']!==false||count($result['lines'])!==3) throw new RuntimeException('Unsafe unlinked handoff');
$handoff['calculation_id']=41;
$handoff['status']='LINKED_REFERENCE_UNVERIFIED';
$result=OfficeDemoCalcV2Boundary::prepare($handoff);
if($result['status']!=='BLOCKED_CALC_ACCESS_VERIFICATION_REQUIRED') throw new RuntimeException('Unverified calculation allowed');
$handoff['lines'][1]['source_finding_code']='DEMO-B01';
try {OfficeDemoCalcV2Boundary::prepare($handoff);throw new RuntimeException('Duplicate accepted');}
catch(InvalidArgumentException $e) {}
echo "DEMO_CALC_V2_BOUNDARY_OK\n";
