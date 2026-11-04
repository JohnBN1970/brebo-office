<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$reader=file_get_contents($root.'/src/Inspection/OfficeInspectionEvidenceRepository.php');
$controller=file_get_contents($root.'/public/office-inspection-overview.php');
$view=file_get_contents($root.'/src/Inspection/OfficeInspectionReportPrintView.php');
$checks=[
  'scoped evidence lookup'=>str_contains($reader,'WHERE f.inspection_id=?'),
  'no public storage key'=>!str_contains($reader,'SELECT e.storage_key'),
  'inspection ACL before evidence'=>strpos($controller,'$repo->detail(')<strpos($controller,'->forInspection($inspectionId)'),
  'report includes selected findings'=>str_contains($controller,'$reportFindings=array_values(array_filter('),
  'report captions escaped'=>str_contains($view,"$"."e($"."photo['caption'])"),
  'photos chapter gate'=>str_contains($view,"in_array('photos',"),
  'photo visibility gated'=>str_contains($view,'$showPhotos && !empty($evidenceByFinding'),
  'no direct image URL'=>!str_contains($view,'storage_key'),
];
foreach($checks as $name=>$ok){echo ($ok?'PASS ':'FAIL ').$name."\n";if(!$ok)exit(1);}
