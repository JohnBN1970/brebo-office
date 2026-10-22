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
  if (in_array($argv[1] ?? '--preflight', ['--preflight','--apply'], true)) {
    \Brebo\Office\Deployment\OfficeOnSiteTestMigration::assertConsumersStopped($c,
      ['OFFICE_ONSITE_SCHEMA_VERIFIED'=>getenv('OFFICE_ONSITE_SCHEMA_VERIFIED')]);
  }
  if (realpath($c['OFFICE_PRIVATE_ROOT']) !== $c['OFFICE_PRIVATE_ROOT']) throw new RuntimeException('test_root_symlink_or_missing');
  $db = new PDO($c['OFFICE_INTAKE_PDO_DSN'], $c['OFFICE_INTAKE_DB_USER'], $c['OFFICE_INTAKE_DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
  $result = (new \Brebo\Office\Deployment\OfficeOnSiteTestMigration($db))->run($argv[1] ?? '--preflight', $argv[2] ?? null);
  echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR), PHP_EOL;
  exit($result['status'] === 'BLOCK' ? 2 : 0);
} catch (Throwable $error) {
  // PDO diagnostics can contain connection details. Keep server/row data out of terminal and CI artifacts.
  $reason = $error instanceof RuntimeException && in_array($error->getMessage(), [
    'invalid_arguments','test_config_symlink_or_missing','invalid_config','isolated_test_configuration_required',
    'onsite_schema_gate_must_remain_disabled','test_root_symlink_or_missing','isolated_database_required',
    'unknown_mode','explicit_apply_confirmation_required','ddl_inside_transaction_forbidden','migration_lock_unavailable',
  ], true) ? $error->getMessage() : 'database_or_migration_failure_rerun_preflight';
  echo json_encode(['status'=>'BLOCK','reason'=>$reason]), PHP_EOL;
  exit(1);
}
