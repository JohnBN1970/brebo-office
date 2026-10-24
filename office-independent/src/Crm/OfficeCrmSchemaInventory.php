<?php
declare(strict_types=1);

namespace Brebo\Office\Crm;

use PDO;
use RuntimeException;

/**
 * Read-only schema inventory. No DDL, no user records, no migrations.
 * Existing CRM tables must be reviewed before enabling schema/write flags.
 */
final class OfficeCrmSchemaInventory {
  /** @return array{ready:bool,issues:list<string>,existing:list<string>,missing:list<string>} */
  public static function inspect(PDO $db): array {
    $names = [
      'office_crm_companies', 'office_crm_contacts', 'office_crm_company_contacts',
      'office_crm_opportunities', 'office_crm_opportunity_companies',
      'office_crm_opportunity_contacts', 'office_crm_company_buildings',
      'office_crm_contact_buildings', 'office_crm_company_projects',
      'office_crm_contact_projects',
    ];
    $query = $db->prepare(
      "SELECT TABLE_NAME, ENGINE FROM INFORMATION_SCHEMA.TABLES
       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name"
    );
    $issues = [];
    $existing = [];
    $missing = [];
    foreach ($names as $name) {
      $query->execute([':table_name' => $name]);
      $row = $query->fetch(PDO::FETCH_ASSOC);
      if ($row === false) {
        $missing[] = $name;
        continue;
      }
      $existing[] = $name;
      if (strcasecmp((string)($row['ENGINE'] ?? ''), 'InnoDB') !== 0) {
        $issues[] = $name . ': opslagengine is niet InnoDB';
      }
      // An existing table is not validated by CREATE TABLE IF NOT EXISTS.
      // Block automatic approval until its columns, keys and FKs are checked.
      $issues[] = $name . ': bestaande tabel vereist handmatige schema- en FK-controle';
    }
    if ($missing !== []) {
      $issues[] = 'CRM-tabellen ontbreken: ' . count($missing);
    }
    // Inventory is intentionally advisory: it never authorizes writes or migration.
    $issues[] = 'CRM-schema vereist expliciete verificatie voor vrijgave';
    return [
      'ready' => $issues === [],
      'issues' => $issues,
      'existing' => $existing,
      'missing' => $missing,
    ];
  }

  public static function requireVerified(PDO $db): void {
    if (!self::inspect($db)['ready']) {
      throw new RuntimeException('CRM-schema is niet volledig geverifieerd');
    }
  }
}
