<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetProjectIdentityGuard.php';

use Brebo\Office\Inzet\OfficeInzetProjectIdentityGuard as Guard;

function mustReject(callable $action): void {
    try {
        $action();
    } catch (InvalidArgumentException | RuntimeException $exception) {
        return;
    }
    throw new RuntimeException('Unsafe project mapping accepted');
}

$rows = [
    ['source_system' => 'legacy_drupal', 'source_project_id' => '4538', 'office_project_id' => 17],
    ['source_system' => 'legacy_drupal', 'source_project_id' => '4539', 'office_project_id' => 18],
];
$map = Guard::validate($rows);
if (Guard::requireProject($map, 'legacy_drupal', '4538') !== 17) {
    throw new RuntimeException('Verified project mapping failed');
}
if (count(Guard::validate([$rows[0], $rows[0]])) !== 1) {
    throw new RuntimeException('Idempotent mapping failed');
}
mustReject(fn() => Guard::requireProject($map, 'legacy_drupal', 'unknown'));
mustReject(fn() => Guard::requireProject($map, 'other_source', '4538'));
mustReject(fn() => Guard::validate([$rows[0], [
    'source_system' => 'legacy_drupal', 'source_project_id' => '4538', 'office_project_id' => 19,
]]));
mustReject(fn() => Guard::validate([$rows[0], [
    'source_system' => 'legacy_drupal', 'source_project_id' => '9999', 'office_project_id' => 17,
]]));
mustReject(fn() => Guard::validate([[
    'source_system' => 'legacy_drupal', 'source_project_id' => '', 'office_project_id' => 17,
]]));
echo "Office project identity guard OK\n";
