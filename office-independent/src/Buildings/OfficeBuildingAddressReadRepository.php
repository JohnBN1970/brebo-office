<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use PDO;
use PDOException;

/**
 * Read-only address listing for an already authorized building.
 * Resident PII is never fetched unless the caller separately authorizes it.
 * Missing not-yet-migrated tables produce a safe unavailable result.
 */
final class OfficeBuildingAddressReadRepository {
  public function __construct(private readonly PDO $db) {}

  public function listForBuilding(int $buildingId, bool $canViewResidents): array {
    if ($buildingId < 1) throw new \InvalidArgumentException('Invalid building ID');
    try {
      $sql = 'SELECT a.id, a.street, a.house_number, a.addition, a.postal_code, a.city, a.bag_nummeraanduiding_id '
        . 'FROM office_building_addresses a WHERE a.building_id = ? ORDER BY a.street, a.house_number, a.addition, a.id LIMIT 500';
      $query = $this->db->prepare($sql);
      $query->execute([$buildingId]);
      $addresses = $query->fetchAll(PDO::FETCH_ASSOC);
      if (!$canViewResidents || $addresses === []) return ['available' => true, 'addresses' => $addresses];
      $ids = array_map(static fn(array $a): int => (int)$a['id'], $addresses);
      $marks = implode(',', array_fill(0, count($ids), '?'));
      $query = $this->db->prepare(
        'SELECT address_id, resident_name, phone, email FROM office_address_residents '
        . "WHERE address_id IN ($marks) ORDER BY id"
      );
      $query->execute($ids);
      $residents = [];
      foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)$row['address_id'];
        $residents[$id][] = [
          'name' => $row['resident_name'], 'phone' => $row['phone'], 'email' => $row['email'],
        ];
      }
      foreach ($addresses as &$address) {
        $address['residents'] = $residents[(int)$address['id']] ?? [];
      }
      unset($address);
      return ['available' => true, 'addresses' => $addresses];
    } catch (PDOException $e) {
      // 42S02: missing MySQL table; 42P01: missing PostgreSQL relation.
      // Other failures must not be misreported as an absent migration.
      if (in_array((string)$e->getCode(), ['42S02', '42P01'], true)) {
        return ['available' => false, 'addresses' => []];
      }
      throw $e;
    }
  }
}
