<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Intake/OfficeMount.php';
require_once dirname(__DIR__) . '/src/Documents/OfficeDocumentContextLinks.php';

use Brebo\Office\Documents\OfficeDocumentContextLinks;

putenv('OFFICE_BASE_PATH=/office-test');
$cases = [
    'project' => ['42', '/office-test/documenten/nieuw?project_id=42'],
    'building' => ['BUILDING-2', '/office-test/documenten/nieuw?building_id=BUILDING-2'],
    'organization' => ['17', '/office-test/documenten/nieuw?organization_id=17'],
    'contact' => ['9', '/office-test/documenten/nieuw?contact_id=9'],
    'personnel' => ['1', '/office-test/documenten/nieuw?person_id=1'],
    'brebo' => ['office', '/office-test/documenten/nieuw?brebo_id=office'],
];
foreach ($cases as $type => [$id, $expected]) {
    if (OfficeDocumentContextLinks::newDocument($type, $id) !== $expected) {
        throw new RuntimeException('context_link_mismatch: ' . $type);
    }
}
foreach ([['personnel', '../admin'], ['project', ''], ['other', '1'], ['contact', '1&admin=1']] as [$type, $id]) {
    try {
        OfficeDocumentContextLinks::newDocument($type, $id);
        throw new RuntimeException('unsafe_context_link_accepted');
    } catch (InvalidArgumentException $expected) {
    }
}
putenv('OFFICE_BASE_PATH=/office');
if (OfficeDocumentContextLinks::newDocument('project', '42') !== '/office/documenten/nieuw?project_id=42') {
    throw new RuntimeException('production_mount_mismatch');
}
echo "OFFICE_DOCUMENT_CONTEXT_LINKS_OK\n";
