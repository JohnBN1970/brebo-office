<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetIdentityMappingGuard.php';

use Brebo\Office\Inzet\OfficeInzetIdentityMappingGuard as Guard;

function rejects(callable $callback): void {
    try {
        $callback();
    } catch (InvalidArgumentException | RuntimeException $error) {
        return;
    }
    throw new RuntimeException('Unsafe identity mapping was accepted');
}

$verified = Guard::validate([
    ['source_system' => 'office_master', 'source_person_id' => 'employee-001', 'office_person_id' => 10],
    ['source_system' => 'office_master', 'source_person_id' => 'employee-002', 'office_person_id' => 11],
]);
if (Guard::requireOfficePerson($verified, 'office_master', 'employee-001') !== 10) {
    throw new RuntimeException('Verified mapping lost');
}
if (count(Guard::validate([
    ['source_system' => 'office_master', 'source_person_id' => 'employee-001', 'office_person_id' => 10],
    ['source_system' => 'office_master', 'source_person_id' => 'employee-001', 'office_person_id' => 10],
])) !== 1) {
    throw new RuntimeException('Idempotent mapping failed');
}
rejects(fn() => Guard::requireOfficePerson($verified, 'office_master', 'unknown'));
rejects(fn() => Guard::requireOfficePerson($verified, 'other_master', 'employee-001'));
rejects(fn() => Guard::validate([
    ['source_system' => 'office_master', 'source_person_id' => 'employee-001', 'office_person_id' => 10],
    ['source_system' => 'office_master', 'source_person_id' => 'employee-001', 'office_person_id' => 11],
]));
rejects(fn() => Guard::validate([
    ['source_system' => 'office_master', 'source_person_id' => 'employee-001', 'office_person_id' => 10],
    ['source_system' => 'office_master', 'source_person_id' => 'employee-002', 'office_person_id' => 10],
]));
rejects(fn() => Guard::validate([
    ['source_system' => 'office_master', 'source_person_id' => '', 'office_person_id' => 10],
]));
rejects(fn() => Guard::validate([
    ['source_system' => 'office_master', 'source_person_id' => 'employee-001', 'office_person_id' => 0],
]));
echo "Office Inzet verified identity mapping guard OK\n";
