<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetIdentityMappingGuard.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetProjectIdentityGuard.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetVerifiedAssignmentResolver.php';

use Brebo\Office\Inzet\OfficeInzetVerifiedAssignmentResolver;

$resolver = new OfficeInzetVerifiedAssignmentResolver(
    [
        ['source_system' => 'legacy_drupal', 'source_person_id' => 'uid-8', 'office_person_id' => 101],
        ['source_system' => 'legacy_drupal', 'source_person_id' => 'uid-9', 'office_person_id' => 102],
    ],
    [
        ['source_system' => 'legacy_drupal', 'source_project_id' => 'node-4538', 'office_project_id' => 17],
    ]
);
if ($resolver->resolve('legacy_drupal', 'node-4538', 'uid-8') !== ['project_id' => 17, 'person_id' => 101]) {
    throw new RuntimeException('Verified assignment resolution failed');
}
foreach ([
    ['legacy_drupal', 'node-4539', 'uid-8'],
    ['legacy_drupal', 'node-4538', 'uid-10'],
    ['different_source', 'node-4538', 'uid-8'],
    ['', 'node-4538', 'uid-8'],
] as [$system, $project, $person]) {
    try {
        $resolver->resolve($system, $project, $person);
    } catch (InvalidArgumentException | RuntimeException $exception) {
        continue;
    }
    throw new RuntimeException('Unverified assignment was accepted');
}
echo "Verified assignment resolution OK\n";
