<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Project/OfficeProjectPageStructure.php';
require_once dirname(__DIR__) . '/src/Project/OfficeProjectDetailV3View.php';
use Brebo\Office\Project\OfficeProjectDetailV3View;
$html = OfficeProjectDetailV3View::render('/office-test', 42, 'Testproject', 'T-42', 'actief', 1, 'overzicht');
foreach (['/office-test/inspectie?project_id=42', '/office-test/documenten?project_id=42', '+ Nieuw inspectierapport'] as $expected) {
    if (!str_contains($html, $expected)) {
        throw new RuntimeException('Missing V3 document action: ' . $expected);
    }
}
$other = OfficeProjectDetailV3View::render('/office-test', 43, 'Ander project', 'T-43', 'actief', 0, 'overzicht');
if (str_contains($other, 'project_id=42') || !str_contains($other, 'project_id=43')) {
    throw new RuntimeException('V3 project context leaked');
}
echo "PASS V3_DOCUMENT_ACTIONS\n";
