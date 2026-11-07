<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use PDO;
use RuntimeException;
use InvalidArgumentException;

/**
 * Persist a verified BAG location on an existing Office building.
 * HTTP callers use OfficeBuildingPdokSaveAction for CSRF/origin and PDOK lookup.
 */
final class OfficeBuildingPdokLocationRepository {
  public function __construct(private PDO $db) {}

  public function canManage(string $operatorId, int $buildingId, bool $lock = false): bool {
    if ($operatorId === '' || $buildingId < 1) return false;
    $stmt = $this->db->prepare("SELECT m.operator_id FROM office_project_buildings pb
      INNER JOIN office_project_memberships m ON m.project_id=pb.project_id
      INNER JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
      WHERE pb.building_id=? AND m.operator_id=? AND m.permission='manage' LIMIT 1" . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$buildingId, $operatorId]);
    return $stmt->fetchColumn() !== false;
  }

  /** @return array<string,bool> */
  public function readiness(): array {
    $stmt = $this->db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_buildings'");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $result = [];
    foreach (['id', 'name', 'address_line', 'postal_code', 'city', 'latitude', 'longitude', 'bag_id'] as $name) {
      $result[$name] = in_array($name, $columns, true);
    }
    return $result;
  }

  /**
   * Caller must verify authenticated session, same-origin request and CSRF.
   * This method independently enforces project-scoped manage permission.
   */
  public function save(
    string $operatorId,
    int $buildingId,
    string $bagId,
    string $address,
    string $postalCode,
    string $city,
    float $latitude,
    float $longitude
  ): void {
    if ($operatorId === '' || $buildingId < 1
      || !preg_match('/^[0-9]{16}$/D', $bagId)
      || trim($address) === '' || strlen($address) > 255
      || !preg_match('/^[1-9][0-9]{3}[A-Z]{2}$/D', $postalCode) || trim($city) === '' || strlen($city) > 160
      || !is_finite($latitude) || !is_finite($longitude)
      || $latitude < 50 || $latitude > 54 || $longitude < 3 || $longitude > 8) {
      throw new InvalidArgumentException('invalid_pdok_location');
    }
    if (in_array(false, $this->readiness(), true)) {
      throw new RuntimeException('pdok_building_schema_not_ready');
    }
    if ($this->db->inTransaction()) throw new RuntimeException('nested_pdok_transaction');
    $this->db->beginTransaction();
    try {
      if (!$this->canManage($operatorId, $buildingId, true)) {
        throw new RuntimeException('building_manage_permission_required');
      }
      $stmt = $this->db->prepare("UPDATE office_buildings
        SET bag_id=?, address_line=?, postal_code=?, city=?, latitude=?, longitude=?
        WHERE id=?");
      $stmt->execute([$bagId, trim($address), trim($postalCode), trim($city),
        $latitude, $longitude, $buildingId]);
      if ($stmt->rowCount() < 1) {
        $check = $this->db->prepare('SELECT 1 FROM office_buildings WHERE id=?');
        $check->execute([$buildingId]);
        if ($check->fetchColumn() === false) throw new RuntimeException('building_not_found');
      }
      $this->db->commit();
    } catch (\Throwable $error) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $error;
    }
  }
}
