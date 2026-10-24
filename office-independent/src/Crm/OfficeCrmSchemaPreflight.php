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

  public static function requireReady(PDO $db): void {
    $result = self::inspect($db);
    if (!$result['ready']) {
      throw new RuntimeException('Office CRM schema preflight failed: ' . implode('; ', $result['issues']));
    }
  }
}
