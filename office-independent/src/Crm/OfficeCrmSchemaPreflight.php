<?php
declare(strict_types=1);

namespace Brebo\Office\Crm;

use PDO;
use RuntimeException;

/**
 * Read-only, fail-closed preflight against the actual Office test schema.
 * No DDL, writes or output containing live business records.
 */
final class OfficeCrmSchemaPreflight {
  /** @return array{ready:bool,issues:list<string>} */
  public static function inspect(PDO $db): array {
    $issues = [];
    $expected = [
      'office_projects' => 'id',
      'office_buildings' => 'id',
    ];
    $query = $db->prepare(
      "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
       FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name"
    );
    foreach ($expected as $table => $idColumn) {
      $query->execute([':table_name' => $table]);
      $columns = [];
      foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[(string)$column['COLUMN_NAME']] = $column;
      }
      if (!isset($columns[$idColumn])) {
        $issues[] = $table . ': id-kolom ontbreekt';
        continue;
      }
      $id = $columns[$idColumn];
      if (!preg_match('/^bigint(?:\\([0-9]+\\))? unsigned$/iD', (string)$id['COLUMN_TYPE'])) {
        $issues[] = $table . ': id-type wijkt af van BIGINT UNSIGNED';
      }
      if ((string)$id['IS_NULLABLE'] !== 'NO' || (string)$id['COLUMN_KEY'] !== 'PRI') {
        $issues[] = $table . ': id is geen verplichte primaire sleutel';
      }
    }
    return ['ready' => $issues === [], 'issues' => $issues];
  }

  /**
   * Verify the exact existing columns used by manual opportunity insertion.
   * No schema changes and no business records are read.
   */
  public static function requireOpportunityReady(PDO $db): void {
    $query = $db->prepare(
      "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
       FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'office_crm_opportunities'"
    );
    $query->execute();
    $columns = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $columns[(string)$row['COLUMN_NAME']] = $row;
    }
    $types = [
      'id' => 'bigint', 'title' => 'varchar(255)', 'stage' => 'varchar(32)',
      'source' => 'varchar(128)', 'estimated_value' => 'decimal(15,2)',
      'currency' => 'char(3)', 'created_at' => 'timestamp', 'updated_at' => 'timestamp',
    ];
    foreach ($types as $name => $type) {
      if (!isset($columns[$name]) ||
        !str_starts_with(strtolower((string)$columns[$name]['COLUMN_TYPE']), $type)) {
        throw new RuntimeException('CRM opportunity schema mismatch: ' . $name);
      }
    }
    if (!str_contains(strtolower((string)$columns['id']['EXTRA']), 'auto_increment')
      || (string)$columns['title']['IS_NULLABLE'] !== 'NO'
      || (string)$columns['stage']['IS_NULLABLE'] !== 'NO'
      || (string)$columns['currency']['IS_NULLABLE'] !== 'NO') {
      throw new RuntimeException('CRM opportunity schema constraints mismatch');
    }
  }

  public static function requireReady(PDO $db): void {
    $result = self::inspect($db);
    if (!$result['ready']) {
      throw new RuntimeException('Office CRM schema preflight failed: ' . implode('; ', $result['issues']));
    }
  }
}
