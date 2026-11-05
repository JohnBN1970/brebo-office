<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Documents/OfficeNewDocumentMenu.php';
use Brebo\Office\Documents\OfficeNewDocumentMenu;
use Brebo\Office\Documents\OfficeDocumentTypes;
foreach (OfficeDocumentTypes::CONTEXTS as $context) {
    $html = OfficeNewDocumentMenu::render($context, ['project_id' => '12', 'building_id' => '7']);
    if (!str_contains($html, 'Nieuw document')) { throw new RuntimeException('Missing menu: ' . $context); }
    if (str_contains($html, 'javascript:')) { throw new RuntimeException('Unsafe URL'); }
}
$html = OfficeNewDocumentMenu::render('projecten', ['project_id' => '12', 'building_id' => '7']);
if (!str_contains($html, '/office-test/inspectie?project_id=12&amp;building_id=7')) { throw new RuntimeException('Context not preserved'); }
if (str_contains(OfficeNewDocumentMenu::render('personeel'), 'href=')) { throw new RuntimeException('Unimplemented personnel document must not link'); }
if (OfficeNewDocumentMenu::render('unknown') !== '') { throw new RuntimeException('Unknown context'); }
echo "OfficeNewDocumentMenuTest OK\n";
