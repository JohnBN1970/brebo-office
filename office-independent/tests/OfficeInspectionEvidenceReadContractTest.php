<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$action=file_get_contents($root.'/src/Inspection/OfficeInspectionEvidenceReadAction.php');
$endpoint=file_get_contents($root.'/public/office-inspection-evidence-view.php');
$checks=[
 'project and building scope'=>str_contains($action,'pb.project_id=i.project_id AND pb.building_id=i.building_id'),
 'active operator'=>str_contains($action,'a.active=1'),
 'read permission'=>str_contains($action,"m.permission IN ('read','manage')"),
 'no arbitrary paths'=>str_contains($action,"preg_match('/^[a-f0-9]{48}"),
 'canonical storage path'=>str_contains($action,'dirname($path)!==$root'),
 'private no-store'=>str_contains($endpoint,'private, no-store'),
 'authenticated session'=>str_contains($endpoint,'currentOperator()'),
 'private root configuration'=>str_contains($endpoint,'OFFICE_INSPECTION_EVIDENCE_PRIVATE_DIR'),
 'no public path'=>!str_contains($endpoint,'storage_key'),
];
foreach($checks as $label=>$pass){echo ($pass?'PASS ':'FAIL ').$label."\n";if(!$pass)exit(1);}
