<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use PDO;
use InvalidArgumentException;
use RuntimeException;

require_once __DIR__.'/OfficeBuildingAddressSchema.php';
require_once __DIR__.'/OfficeBagAddressRange.php';

/** Database writes require a separately authenticated, authorized and CSRF-checked caller. */
final class OfficeBuildingAddressRepository {
  public function __construct(private PDO $db) {}

  /** Insert only validated BAG documents. Duplicate BAG IDs are ignored, never overwritten. */
  public function importBagDocuments(int $buildingId, array $docs, int $first, int $last): int {
    if ($buildingId<1) throw new InvalidArgumentException('invalid_building');
    $rows=OfficeBagAddressRange::select($docs,$first,$last);
    if (!$rows) return 0;
    $this->db->beginTransaction();
    try {
      $building=$this->db->prepare('SELECT id FROM office_buildings WHERE id=?');
      $building->execute([$buildingId]);
      if (!$building->fetchColumn()) throw new RuntimeException('building_not_found');
      $statement=$this->db->prepare(
        "INSERT IGNORE INTO office_building_addresses
        (building_id,bag_address_id,street,house_number,house_letter,house_addition,postal_code,city,status)
        VALUES (?,?,?,?,?,?,?,?,'concept')"
      );
      $count=0;
      foreach ($rows as $row) {
        $statement->execute([
          $buildingId,$row['bag_address_id'],$row['street'],$row['house_number'],
          $row['house_letter'],$row['house_addition'],$row['postal_code'],$row['city']
        ]);
        $count+=$statement->rowCount();
      }
      $this->db->commit();
      return $count;
    } catch (\Throwable $error) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $error;
    }
  }

  /** Cross-building type assignments are explicitly rejected. */
  public function classify(int $buildingId,int $addressId,?int $floor,?int $typeId): void {
    OfficeBuildingAddressSchema::validateAddress($floor,$typeId);
    if ($buildingId<1 || $addressId<1) throw new InvalidArgumentException('invalid_address');
    $this->db->beginTransaction();
    try {
      if ($typeId!==null) {
        $type=$this->db->prepare('SELECT id FROM office_building_dwelling_types WHERE id=? AND building_id=?');
        $type->execute([$typeId,$buildingId]);
        if (!$type->fetchColumn()) throw new InvalidArgumentException('dwelling_type_not_in_building');
      }
      $update=$this->db->prepare('UPDATE office_building_addresses SET primary_floor=?,dwelling_type_id=? WHERE id=? AND building_id=?');
      $update->execute([$floor,$typeId,$addressId,$buildingId]);
      if ($update->rowCount()===0) {
        $check=$this->db->prepare('SELECT id FROM office_building_addresses WHERE id=? AND building_id=?');
        $check->execute([$addressId,$buildingId]);
        if (!$check->fetchColumn()) throw new RuntimeException('address_not_in_building');
      }
      $this->db->commit();
    } catch (\Throwable $error) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $error;
    }
  }

  /** Replaces all levels for a maisonette or other multi-floor dwelling. */
  public function setAddressFloors(int $buildingId,int $addressId,array $floors): void {
    if ($buildingId<1 || $addressId<1 || count($floors)>30) {
      throw new InvalidArgumentException('invalid_address_floors');
    }
    $validated=[];
    foreach ($floors as $floor) {
      if (!is_int($floor)) throw new InvalidArgumentException('floor_must_be_integer');
      OfficeBuildingAddressSchema::floor($floor);
      $validated[$floor]=$floor;
    }
    sort($validated,SORT_NUMERIC);
    $this->db->beginTransaction();
    try {
      $check=$this->db->prepare('SELECT id FROM office_building_addresses WHERE id=? AND building_id=? FOR UPDATE');
      $check->execute([$addressId,$buildingId]);
      if (!$check->fetchColumn()) throw new RuntimeException('address_not_in_building');
      $delete=$this->db->prepare('DELETE FROM office_building_address_floors WHERE address_id=?');
      $delete->execute([$addressId]);
      $insert=$this->db->prepare('INSERT INTO office_building_address_floors (address_id,floor_number) VALUES (?,?)');
      foreach ($validated as $floor) $insert->execute([$addressId,$floor]);
      $this->db->commit();
    } catch (\Throwable $error) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $error;
    }
  }

  /**
   * Bulk assign a dwelling type. Physical locations (demarcations) are NOT
   * copied or overwritten: they are independently linked to building parts.
   * Address floors and inventory exceptions remain untouched.
   * Caller must authenticate, authorize and verify CSRF.
   */
  public function assignDwellingTypeBulk(int $buildingId,int $typeId,array $addressIds): int {
    if ($buildingId<1 || $typeId<1 || count($addressIds)<1 || count($addressIds)>500) {
      throw new InvalidArgumentException('invalid_bulk_selection');
    }
    $ids=[];
    foreach ($addressIds as $id) {
      if (!is_int($id) || $id<1) throw new InvalidArgumentException('invalid_bulk_address');
      $ids[$id]=$id;
    }
    $ids=array_values($ids);
    sort($ids,SORT_NUMERIC);
    $this->db->beginTransaction();
    try {
      $type=$this->db->prepare('SELECT id FROM office_building_dwelling_types WHERE id=? AND building_id=?');
      $type->execute([$typeId,$buildingId]);
      if (!$type->fetchColumn()) throw new RuntimeException('dwelling_type_not_in_building');
      $placeholders=implode(',',array_fill(0,count($ids),'?'));
      $lock=$this->db->prepare("SELECT id FROM office_building_addresses WHERE building_id=? AND id IN ($placeholders) FOR UPDATE");
      $lock->execute(array_merge([$buildingId],$ids));
      $found=array_map('intval',$lock->fetchAll(PDO::FETCH_COLUMN));
      sort($found,SORT_NUMERIC);
      if ($found!==$ids) throw new RuntimeException('address_not_in_building');
      $update=$this->db->prepare('UPDATE office_building_addresses SET dwelling_type_id=? WHERE id=? AND building_id=?');
      foreach ($ids as $id) $update->execute([$typeId,$id,$buildingId]);
      $this->db->commit();
      return count($ids);
    } catch (\Throwable $error) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $error;
    }
  }

  public function assignDemarcation(int $buildingId,int $demarcationId,int $addressId): void {
    if (min($buildingId,$demarcationId,$addressId)<1) throw new InvalidArgumentException('invalid_assignment');
    $sql="INSERT IGNORE INTO office_building_demarcation_addresses (demarcation_id,address_id)
      SELECT d.id,a.id FROM office_building_demarcations d
      JOIN office_building_addresses a ON a.building_id=d.building_id
      WHERE d.id=? AND a.id=? AND d.building_id=?";
    $statement=$this->db->prepare($sql);
    $statement->execute([$demarcationId,$addressId,$buildingId]);
  }
}
