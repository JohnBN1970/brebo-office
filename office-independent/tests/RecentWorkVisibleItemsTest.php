<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/RecentWork/RecentWorkEvent.php';
require_once dirname(__DIR__) . '/src/RecentWork/RecentWorkAccess.php';
require_once dirname(__DIR__) . '/src/RecentWork/RecentWorkVisibleItems.php';

use Brebo\Office\RecentWork\RecentWorkAccess;
use Brebo\Office\RecentWork\RecentWorkVisibleItems;

$rows = [
  ['module'=>'projects', 'resource_type'=>'project', 'resource_id'=>10, 'view_key'=>'detail', 'position_json'=>'{}'],
  ['module'=>'projects', 'resource_type'=>'project', 'resource_id'=>11, 'view_key'=>'detail', 'position_json'=>'{}'],
  ['module'=>'projects', 'resource_type'=>'project', 'resource_id'=>12, 'view_key'=>'detail', 'position_json'=>'{bad'],
  ['module'=>'projects', 'resource_type'=>'project', 'resource_id'=>13, 'view_key'=>'https://evil.invalid', 'position_json'=>'{}'],
  ['module'=>'projects', 'resource_type'=>'project', 'resource_id'=>10, 'view_key'=>'detail', 'position_json'=>'{"launch_token":"secret"}'],
  ['module'=>'calculation', 'resource_type'=>'calculation', 'resource_id'=>9, 'view_key'=>'chapter', 'position_json'=>'{"chapter_id":"1"}'],
];
$access = new RecentWorkAccess([
  'projects' => static fn (string $operator, string $type, int $id): bool =>
    $operator === 'alice' && $type === 'project' && $id === 10,
]);
$filter = new RecentWorkVisibleItems($access);
$alice = $filter->filter('alice', $rows);
$bob = $filter->filter('bob', $rows);
if (count($alice) !== 1 || $alice[0]->resourceId !== 10 || $bob !== []) {
  throw new RuntimeException('Recent-work visible item ACL filtering failed.');
}
if ((new RecentWorkVisibleItems(new RecentWorkAccess()))->filter('alice', $rows) !== []) {
  throw new RuntimeException('Unconfigured ACL must not expose persisted work.');
}
echo "Recent work visible items: OK\n";
