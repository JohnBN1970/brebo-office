<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftForm.php';
use Brebo\Office\Inzet\OfficeInzetShiftForm;
$token = str_repeat('a',64);
$html = OfficeInzetShiftForm::renderEditable(
  ['id'=>17,'title'=>'Project <17>'],
  [
    ['id'=>101,'display_name'=>'John Boon','personnel_number'=>'BREBO-001'],
    ['id'=>102,'display_name'=>'John Boon','personnel_number'=>'BREBO-047'],
  ],
  '/office-test/inzet/dienst/nieuw',$token
);
foreach ([
  'method="post"','action="/office-test/inzet/dienst/nieuw"',
  'name="csrf_token"','name="project_id" value="17"',
  'name="person_id"','name="starts_at_local"','name="ends_at_local"',
  'BREBO-001','BREBO-047','value="101"','value="102"',
  'Project &lt;17&gt;',
] as $needle) {
  if (!str_contains($html,$needle)) throw new RuntimeException('Editable shift form missing: '.$needle);
}
foreach (['/office/inzet/dienst/nieuw','https://evil.example/shift','/office-test/../shift'] as $badAction) {
  try {
    OfficeInzetShiftForm::renderEditable(['id'=>17],[],$badAction,$token);
    throw new RuntimeException('Unsafe form action accepted');
  } catch (InvalidArgumentException $e) {}
}
try {
  OfficeInzetShiftForm::renderEditable(['id'=>17],[],'/office-test/inzet/dienst/nieuw','bad');
  throw new RuntimeException('Invalid CSRF accepted');
} catch (InvalidArgumentException $e) {}
echo "Editable shift form contract OK\n";
