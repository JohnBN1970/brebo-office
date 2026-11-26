<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use PDO;
use RuntimeException;
use InvalidArgumentException;

/**
 * Persist a verified BAG location on an existing Office building.
 * Not connected to HTTP until the active schema and CSRF/ACL are verified.
 */
final class OfficeBuildingPdokLocationRepository {
  public function __construct(private PDO $db) {}

  /** Link an existing building to an independently verified official address. */
  public function linkVerified(int $operatorId,int $buildingId,string $bagId,string $expectedRevision): void {
    if ($operatorId<1 || $buildingId<1 || getenv('OFFICE_BUILDING_BAG_SCHEMA_VERIFIED')!=='enabled' || getenv('OFFICE_BUILDING_AUDIT_SCHEMA_VERIFIED')!=='enabled') throw new RuntimeException('bag_storage_not_ready');
    require_once __DIR__.'/OfficePdokAddressLookup.php';
    require_once __DIR__.'/OfficeBuildingAudit.php';
    $bag=(new OfficePdokAddressLookup())->verify($bagId);
    $this->db->beginTransaction();
    try {
      $q=$this->db->prepare('SELECT id FROM office_operator_accounts WHERE id=? AND active=1 AND can_edit_crm=1 FOR UPDATE');
      $q->execute([$operatorId]);
      if (!$q->fetchColumn()) throw new RuntimeException('building_manage_permission_required');
      $q=$this->db->prepare("SELECT b.building_name,b.street_address,b.address_line,b.postal_code,b.city,b.latitude,b.longitude,b.bag_id,b.bag_verified_at FROM office_buildings b WHERE b.id=? AND b.active=1 AND (EXISTS (SELECT 1 FROM office_building_register_grants g WHERE g.building_id=b.id AND g.operator_id=?) OR EXISTS (SELECT 1 FROM office_project_buildings pb JOIN office_project_memberships m ON m.project_id=pb.project_id WHERE pb.building_id=b.id AND m.operator_id=? AND m.permission='manage')) FOR UPDATE");
      $q->execute([$buildingId,$operatorId,$operatorId]);
      $before=$q->fetch(PDO::FETCH_ASSOC);
      if (!$before || !hash_equals(self::revision($before),$expectedRevision)) throw new RuntimeException('building_changed_or_forbidden');
      $q=$this->db->prepare("SELECT id FROM office_buildings WHERE id<>? AND (bag_id=? OR (UPPER(REPLACE(postal_code,' ',''))=? AND LOWER(TRIM(street_address))=LOWER(?))) LIMIT 1");
      $q->execute([$buildingId,$bagId,str_replace(' ','',$bag['postal_code']),$bag['street_address']]);
      if ($q->fetchColumn()!==false) throw new RuntimeException('building_address_candidate_exists');
      $q=$this->db->prepare('UPDATE office_buildings SET bag_id=?,bag_verified_at=CURRENT_TIMESTAMP(6),street_address=?,address_line=?,postal_code=?,city=?,latitude=?,longitude=? WHERE id=?');
      $q->execute([$bagId,$bag['street_address'],$bag['street_address'],$bag['postal_code'],$bag['city'],$bag['latitude'],$bag['longitude'],$buildingId]);
      $q=$this->db->prepare('SELECT building_name,street_address,address_line,postal_code,city,latitude,longitude,bag_id,bag_verified_at FROM office_buildings WHERE id=?');
      $q->execute([$buildingId]);$after=$q->fetch(PDO::FETCH_ASSOC);
      if (!$after) throw new RuntimeException('building_snapshot_unavailable');
      (new OfficeBuildingAudit($this->db))->record($buildingId,$operatorId,'updated','register',null,$before,$after);
      $this->db->commit();
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }

  public static function revision(array $row): string {
    $fields=[];
    foreach (['building_name','street_address','address_line','postal_code','city','latitude','longitude','bag_id','bag_verified_at'] as $key) $fields[$key]=$row[$key]??null;
    return hash('sha256',json_encode($fields,JSON_THROW_ON_ERROR));
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
    int $operatorId,
    int $buildingId,
    string $bagId,
    string $address,
    string $postalCode,
    string $city,
    float $latitude,
    float $longitude
  ): void {
    if ($operatorId < 1 || $buildingId < 1
      || !preg_match('/^[a-zA-Z0-9._:-]{1,128}$/D', $bagId)
      || trim($address) === '' || strlen($address) > 255
      || strlen($postalCode) > 32 || strlen($city) > 160
      || !is_finite($latitude) || !is_finite($longitude)
      || $latitude < 50 || $latitude > 54 || $longitude < 3 || $longitude > 8) {
      throw new InvalidArgumentException('invalid_pdok_location');
    }
    if (in_array(false, $this->readiness(), true)) {
      throw new RuntimeException('pdok_building_schema_not_ready');
    }
    $this->db->beginTransaction();
    try {
      $access = $this->db->prepare("SELECT 1 FROM office_project_buildings pb
        INNER JOIN office_project_memberships m ON m.project_id=pb.project_id
        INNER JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
        WHERE pb.building_id=? AND m.operator_id=? AND m.permission='manage' LIMIT 1");
      $access->execute([$buildingId, $operatorId]);
      if ($access->fetchColumn() === false) {
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
