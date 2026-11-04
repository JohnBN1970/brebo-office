<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Demo/OfficeCommercialCalcHandoff.php';
require_once __DIR__ . '/../src/Demo/OfficeCommercialContextForm.php';
use Brebo\Office\Demo\OfficeCommercialContextForm as F;
$html = F::render(['position_code' => 'hoofdleverancier', 'work_categories' => ['montage', 'glas']], '/office-test/crm/commercieel/opslaan', 'csrf-example');
foreach (['hoofdleverancier', 'toeleverancier', 'particulier', 'montage', 'glas', 'name="csrf"'] as $needle) {
    if (!str_contains($html, $needle)) throw new RuntimeException('Missing form field');
}
if (!str_contains($html, 'method="post"')) throw new RuntimeException('Expected POST');
if (str_contains($html, '<script>')) throw new RuntimeException('Unexpected script');
echo "Commercial CRM form contract OK\n";
