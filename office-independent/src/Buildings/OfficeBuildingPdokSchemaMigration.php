<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use PDO;
use RuntimeException;

/**
 * Explicit, additive migration for existing Office buildings.
 * Not invoked by web requests or by deployment automatically.
 */
final class OfficeBuildingPdokSchemaMigration {
  public static function inspect(PDO $db): array {
    $stmt = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_buildings'");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('id', $columns, true) || !in_array('name', $columns, true)
      || !in_array('address_line', $columns, true)) {
      throw new RuntimeException('unexpected_office_buildings_schema');
    }
    return array_fill_keys(array_values(array_diff(['latitude','longitude','bag_id'], $columns)), true);
  }

  /**
   * Run only as an approved migration with a verified database backup.
   * Nullable fields preserve existing rows and do not touch building zones.
   */
  public static function apply(PDO $db): array {
    $missing = self::inspect($db);
    $ddl = [
      'latitude' => 'ALTER TABLE office_buildings ADD COLUMN latitude DECIMAL(10,7) NULL',
      'longitude' => 'ALTER TABLE office_buildings ADD COLUMN longitude DECIMAL(10,7) NULL',
      'bag_id' => 'ALTER TABLE office_buildings ADD COLUMN bag_id VARCHAR(128) NULL',
    ];
    foreach ($ddl as $column => $sql) {
      if (isset($missing[$column])) $db->exec($sql);
    }
    $remaining = self::inspect($db);
    if ($remaining !== []) throw new RuntimeException('pdok_schema_migration_incomplete');
    return array_keys($missing);
  }
}
