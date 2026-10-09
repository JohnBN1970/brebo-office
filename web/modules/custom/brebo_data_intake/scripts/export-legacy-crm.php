<?php

declare(strict_types=1);

/**
 * Run from a trusted Drupal/Drush runtime:
 *   drush scr web/modules/custom/brebo_data_intake/scripts/export-legacy-crm.php -- /secure/crm-export.json
 *
 * Never expose this script as a web route. The output contains sensitive CRM
 * information and must stay outside the webroot.
 */

use Drupal\brebo_data_intake\Service\LegacyCrmOpportunityExporter;

if (PHP_SAPI !== 'cli') {
  throw new RuntimeException('CRM export requires CLI.');
}
$args = array_values(array_filter($_SERVER['argv'] ?? [], static fn (string $arg): bool => str_ends_with($arg, '.json')));
if (count($args) !== 1) {
  throw new RuntimeException('Provide exactly one absolute .json output path.');
}
$destination = $args[0];
if (!str_starts_with($destination, '/') || is_link($destination) || file_exists($destination)) {
  throw new RuntimeException('Export path must be absolute, new and not a symlink.');
}
$directory = dirname($destination);
if (!is_dir($directory) || !is_writable($directory)) {
  throw new RuntimeException('Export directory must exist and be writable.');
}
$webroot = realpath(DRUPAL_ROOT);
$targetDirectory = realpath($directory);
if ($webroot === false || $targetDirectory === false
  || $targetDirectory === $webroot
  || str_starts_with($targetDirectory, $webroot . DIRECTORY_SEPARATOR)) {
  throw new RuntimeException('Export destination must be outside the Drupal webroot.');
}

$exporter = new LegacyCrmOpportunityExporter(\Drupal::entityTypeManager());
$rows = [];
for ($offset = 0; ; $offset += 100) {
  $page = $exporter->exportPage($offset, 100);
  array_push($rows, ...$page);
  if (count($page) < 100) {
    break;
  }
}
$encoded = json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
// The independent import rejects payloads over 20 MB. Fail here rather than
// producing a protected export that the receiving command cannot accept.
if (strlen($encoded) > 20_000_000) {
  throw new RuntimeException('CRM export exceeds the 20 MB import limit; split the migration into reviewed batches.');
}
$oldUmask = umask(0077);
try {
  $handle = fopen($destination, 'x');
  if ($handle === false) {
    throw new RuntimeException('Could not create protected CRM export.');
  }
  try {
    $length = strlen($encoded);
    $written = 0;
    while ($written < $length) {
      $n = fwrite($handle, substr($encoded, $written));
      if ($n === false || $n === 0) {
        throw new RuntimeException('Incomplete CRM export write.');
      }
      $written += $n;
    }
  }
  finally {
    fclose($handle);
  }
}
catch (Throwable $e) {
  if (is_file($destination)) {
    unlink($destination);
  }
  throw $e;
}
finally {
  umask($oldUmask);
}
fwrite(STDOUT, 'Exported ' . count($rows) . " CRM opportunities to protected JSON.\n");
