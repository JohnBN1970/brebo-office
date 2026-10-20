<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetPersonnelNumberForm.php';
use Brebo\Office\Inzet\OfficeInzetPersonnelNumberForm;
$token = str_repeat('a', 64);
$html = OfficeInzetPersonnelNumberForm::render(17, 9, 'Naam <script>', 'BR-009', $token, '/office-test');
foreach (['method="post"', 'csrf_token', 'project_id', 'person_id', 'personnel_number', 'BR-009', '&lt;script&gt;'] as $expected) {
  if (!str_contains($html, $expected)) throw new RuntimeException('Missing safe personnel form field: ' . $expected);
}
if (str_contains($html, '<script>')) throw new RuntimeException('Unsafe employee name in personnel form');
foreach ([[0,9,$token,'/office-test'],[17,0,$token,'/office-test'],[17,9,'invalid','/office-test'],[17,9,$token,'/office']] as [$project,$person,$csrf,$mount]) {
  try {
    OfficeInzetPersonnelNumberForm::render($project,$person,'Employee','',$csrf,$mount);
    throw new RuntimeException('Unverified personnel form context accepted');
  } catch (InvalidArgumentException $expected) {}
}
echo "Native personnel edit form validation OK\n";
