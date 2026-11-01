<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficePersonnelDetailsReadForm.php';
use Brebo\Office\Inzet\OfficePersonnelDetailsReadForm;

$html = OfficePersonnelDetailsReadForm::render([
  'phone_country'=>'BE',
  'phone_e164'=>'+32470123456',
  'nationality'=>'NL',
  'preferred_language'=>'nl',
  'skills'=>['Glaszetten'],
  'vca_expiry'=>'2027-12-31',
], 17, 23, str_repeat('a', 64));
foreach (['name="person_id" value="17"','name="project_id" value="23"','name="csrf_token"',
  'name="phone_country"','name="phone_number"','name="nationality"',
  'name="preferred_language"','name="skills[]"','name="vca_expiry"',
  'value="BE" selected','value="Glaszetten" checked','2027-12-31'] as $expected) {
  if (!str_contains($html, $expected)) throw new RuntimeException('Missing form value: ' . $expected);
}
$escaped = OfficePersonnelDetailsReadForm::render(['phone_e164'=>'"><script>alert(1)</script>'], 17, 23, str_repeat('a',64));
if (str_contains($escaped, '<script>') || !str_contains($escaped, '&lt;script&gt;')) {
  throw new RuntimeException('Unescaped form value');
}
echo "Personnel details read form OK\n";
