<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/RecentWork/RecentWorkEvent.php';

use Brebo\Office\RecentWork\RecentWorkEvent;

function recentAssert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$valid = new RecentWorkEvent('calculation', 'calculation', 42, 'chapter', 7);
recentAssert($valid->positionJson() === '{"chapter_id":7}', 'Chapter position serialization failed');
$empty = new RecentWorkEvent('projects', 'project', 3, 'overview');
recentAssert($empty->positionJson() === '{}', 'Empty position serialization failed');

$invalid = [
  ['admin', 'project', 3, 'detail', null],
  ['projects', 'invoice', 3, 'detail', null],
  ['projects', 'project', 0, 'detail', null],
  ['projects', 'project', 3, 'https://evil.invalid', null],
  ['calculation', 'calculation', 3, 'chapter', 0],
  ['finance', 'invoice', 3, 'detail', 9],
];
foreach ($invalid as [$module, $type, $id, $view, $position]) {
  try {
    new RecentWorkEvent($module, $type, $id, $view, $position);
  } catch (DomainException $e) {
    continue;
  }
  throw new RuntimeException('Unsafe recent-work event accepted: ' . $module . '/' . $view);
}
echo "Recent work validation: OK\n";
