<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$project = file_get_contents($root . '/public/office-project.php');
if (!is_string($project)) {
    throw new RuntimeException('Project page missing');
}
foreach (['OfficeNativeProjectAccess', '->canRead(', 'OfficeNewDocumentMenu.php', "OfficeNewDocumentMenu::render('projecten'", '$projectDocumentMenu'] as $required) {
    if (!str_contains($project, $required)) {
        throw new RuntimeException('Missing project document integration: ' . $required);
    }
}
require_once $root . '/src/Documents/OfficeNewDocumentMenu.php';
$html = \Brebo\Office\Documents\OfficeNewDocumentMenu::render('projecten', ['project_id' => 42]);
if (!str_contains($html, '/office-test/inspectie?project_id=42')) {
    throw new RuntimeException('Project id not carried to document');
}
if (!str_contains($html, 'Bouwkundig inspectierapport')) {
    throw new RuntimeException('Inspection type missing');
}
echo "PASS PROJECT_DOCUMENT_MENU_CONTRACT\n";
