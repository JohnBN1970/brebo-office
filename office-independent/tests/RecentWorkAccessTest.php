<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/RecentWork/RecentWorkEvent.php';
require_once dirname(__DIR__) . '/src/RecentWork/RecentWorkAccess.php';

use Brebo\Office\RecentWork\RecentWorkAccess;
use Brebo\Office\RecentWork\RecentWorkEvent;

$event = new RecentWorkEvent('projects', 'project', 12, 'detail');
$closed = new RecentWorkAccess();
if ($closed->canAccess('alice', $event)) {
  throw new RuntimeException('Missing resolver must deny access.');
}
$access = new RecentWorkAccess([
  'projects' => static fn (string $user, string $type, int $id): bool =>
    $user === 'alice' && $type === 'project' && $id === 12,
]);
if (!$access->canAccess('alice', $event) || $access->canAccess('bob', $event)) {
  throw new RuntimeException('Cross-user authorization isolation failed.');
}
if ($access->canAccess('', $event)) {
  throw new RuntimeException('Anonymous recent-work access was allowed.');
}
$errors = new RecentWorkAccess([
  'projects' => static function (): bool {
    throw new RuntimeException('Database unavailable');
  },
]);
if ($errors->canAccess('alice', $event)) {
  throw new RuntimeException('Resolver exception must deny access.');
}
echo "Recent work authorization: OK\n";
