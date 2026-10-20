<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftForm.php';

use Brebo\Office\Inzet\OfficeInzetShiftForm;

$html = OfficeInzetShiftForm::renderEditable(
    ['id'=>17,'title'=>'Testproject'],
    [['id'=>101,'display_name'=>'Test medewerker','personnel_number'=>'BREBO-101']],
    '/office-test/inzet/dienst/nieuw',
    str_repeat('a',64)
);
foreach (['method="post"','name="csrf_token"','name="project_id" value="17"','name="person_id"','BREBO-101','name="starts_at_local"','name="ends_at_local"'] as $part) {
    if (!str_contains($html,$part)) throw new RuntimeException('Missing secure shift form field: '.$part);
}
try {
    OfficeInzetShiftForm::renderEditable(['id'=>17],[], '/office/inzet/dienst/nieuw', str_repeat('a',64));
    throw new RuntimeException('Production route accepted by test-only form');
} catch (InvalidArgumentException $expected) {}
echo "Office test shift form contract OK\n";
