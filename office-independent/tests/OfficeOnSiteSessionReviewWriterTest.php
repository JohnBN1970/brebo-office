<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteSessionReviewWriter.php';

use Brebo\Office\Inzet\OnSiteSessionReviewWriter;

// Validation must fail before any database transaction or SQL execution.
$db = new PDO('sqlite::memory:');
$writer = new OnSiteSessionReviewWriter($db);
$invalid = [
  ['0', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'approved', 3600, 1, null],
  ['1', 'same', 'same', 'approved', 3600, 1, null],
  ['1', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'pending', 3600, 1, null],
  ['1', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'rejected', 0, 1, null],
  ['1', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'approved', 90000, 1, null],
];
foreach ($invalid as $args) {
  try { $writer->record(...$args); }
  catch (InvalidArgumentException $expected) { continue; }
  throw new RuntimeException('Invalid review request accepted');
}
echo "Office native review writer validation passed\n";
