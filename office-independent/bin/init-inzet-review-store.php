<?php

declare(strict_types=1);

// CLI-only setup for a separate, private decision store. Never changes source.
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetReviewDecisionRepository.php';
$sourcePath = getenv('OFFICE_TEST_INZET_SQLITE_PATH');
$decisionPath = getenv('OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH');
if (!is_string($sourcePath) || !is_string($decisionPath)
  || $sourcePath === '' || $decisionPath === ''
  || !is_file($sourcePath) || !is_readable($sourcePath)) {
  fwrite(STDERR, "Missing private source or decision path.\n");
  exit(1);
}
$source = realpath($sourcePath);
$targetDir = realpath(dirname($decisionPath));
if ($source === false || $targetDir === false || !is_writable($targetDir)
  || !str_starts_with($decisionPath, DIRECTORY_SEPARATOR)
  || is_link($decisionPath) || $source === $decisionPath
  || (file_exists($decisionPath) && !is_file($decisionPath))) {
  fwrite(STDERR, "Unsafe decision store path.\n");
  exit(1);
}
$target = $targetDir . DIRECTORY_SEPARATOR . basename($decisionPath);
if ($source === $target || (is_file($target) && realpath($target) === $source)) {
  fwrite(STDERR, "Source and decision store must differ.\n");
  exit(1);
}
try {
  $sourceDb = new PDO('sqlite:file:'.str_replace('%2F', '/', rawurlencode($source)).'?mode=ro');
  $sourceDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sourceDb->query('SELECT id FROM review_issues LIMIT 1');
  $decisionDb = new PDO('sqlite:'.$target);
  $decisionDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  (new \Brebo\Office\Inzet\OfficeInzetReviewDecisionRepository($decisionDb, $sourceDb))->install();
  if (!chmod($target, 0600)) {
    throw new RuntimeException('Could not restrict private decision store permissions.');
  }
  echo "Private Inzet decision schema ready. No source changes.\n";
} catch (Throwable $e) {
  fwrite(STDERR, "Private Inzet decision initialization failed.\n");
  exit(1);
}
