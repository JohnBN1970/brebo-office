<?php
declare(strict_types=1);
/** Manual CLI entry point. Never include this file in the application bootstrap. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__.'/OfficeOnSiteTestMigration.php';
try {
  if ($argc > 3) throw new RuntimeException('invalid_arguments');
  $base = '/home/u213420663/domains/brebobv.nl/office_calc_test';
  $configFile = $base.'/config/config.php';
  if (realpath($configFile) !== $configFile) throw new RuntimeException('test_config_symlink_or_missing');
  $c = require $configFile;
  if (!is_array($c)) throw new RuntimeException('invalid_config');
  \Brebo\Office\Deployment\OfficeOnSiteTestMigration::assertTarget($c);
  if (realpath($c['OFFICE_PRIVATE_ROOT']) !== $c['OFFICE_PRIVATE_ROOT']) throw new RuntimeException('test_root_symlink_or_missing');
  $db = new PDO($c['OFFICE_INTAKE_PDO_DSN'], $c['OFFICE_INTAKE_DB_USER'], $c['OFFICE_INTAKE_DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
  $result = (new \Brebo\Office\Deployment\OfficeOnSiteTestMigration($db))->run($argv[1] ?? '--preflight', $argv[2] ?? null);
  echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR), PHP_EOL;
  exit($result['status'] === 'BLOCK' ? 2 : 0);
} catch (Throwable) {
  // PDO diagnostics can contain connection details. Keep server/row data out of terminal and CI artifacts.
  echo json_encode(['status'=>'BLOCK','reason'=>'migration_failed; inspect private server logs and rerun preflight']), PHP_EOL;
  exit(1);
}
