<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use PDO;
use RuntimeException;

/**
 * Detects either documented Office building schema without relying on a
 * deployment-specific guess. Does not alter schema or grant access.
 */
final class OfficeBuildingColumnResolver {
  public static function columns(PDO $db): array {
    $stmt = $db->query('SHOW COLUMNS FROM office_buildings');
    $found = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $found[(string)$row['Field']] = true;
    }
    return self::fromNames(array_keys($found));
  }

  public static function fromNames(array $names): array {
    $fields = array_fill_keys($names, true);
    $name = isset($fields['building_name']) ? 'building_name' : (isset($fields['name']) ? 'name' : null);
    $address = isset($fields['street_address']) ? 'street_address' : (isset($fields['address_line']) ? 'address_line' : null);
    foreach (['id','postal_code','city'] as $required) {
      if (!isset($fields[$required])) throw new RuntimeException('Building schema missing required field');
    }
    if ($name === null || $address === null) throw new RuntimeException('Unsupported building schema');
    return ['name'=>$name,'address'=>$address];
  }
}
