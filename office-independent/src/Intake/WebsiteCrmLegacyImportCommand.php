<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;
use RuntimeException;

/**
 * Offline-only import from a reviewed JSON export. Never queries Drupal or
 * modifies existing Drupal records.
 */
final class WebsiteCrmLegacyImportCommand {

  public static function run(array $argv): int {
    if (count($argv) !== 2 || !is_file($argv[1])) {
      fwrite(STDERR, "Usage: php import-legacy-crm.php reviewed-export.json\n");
      return 2;
    }
    $raw = file_get_contents($argv[1]);
    if ($raw === false || strlen($raw) > 20_000_000) {
      fwrite(STDERR, "CRM export missing or too large.\n");
      return 2;
    }
    try {
      $rows = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
      if (!is_array($rows) || !array_is_list($rows)) {
        throw new RuntimeException('Expected a JSON array of CRM opportunities.');
      }
      $validator = new WebsiteCrmLegacyExportValidator();
      $inventory = $validator->validate($rows);
      fwrite(STDOUT, "Validated {$inventory['records']} legacy opportunities.\n");
      if (getenv('OFFICE_CRM_IMPORT_CONFIRM') !== 'YES') {
        fwrite(STDOUT, "Dry run only. Set OFFICE_CRM_IMPORT_CONFIRM=YES to import into the configured independent database.\n");
        return 0;
      }
      $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
      if (!$dsn) {
        throw new RuntimeException('OFFICE_INTAKE_PDO_DSN missing.');
      }
      $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
      // DDL runs outside the import transaction. Run on a controlled
      // migration window, never from the public HTTP endpoint.
      $db->exec(WebsiteCrmLegacyImportRepository::schema());
      $result = (new WebsiteCrmLegacyImportRepository($db))->import($rows);
      fwrite(STDOUT, "Imported {$result['inserted']} new records; {$result['records']} reviewed.\n");
      return 0;
    }
    catch (\Throwable $e) {
      fwrite(STDERR, "CRM import rejected: {$e->getMessage()}\n");
      return 1;
    }
  }
}
