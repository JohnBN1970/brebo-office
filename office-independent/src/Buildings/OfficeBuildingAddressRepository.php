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

  /** Define a reusable dwelling-type template before assigning it to addresses. */
  public function createDwellingType(int $buildingId,string $code,string $description,?string $area=null,?array $layout=null): int {
    $code=trim($code);
    $description=trim($description);
    if ($buildingId<1 || $code==='' || mb_strlen($code)>40 || $description==='' || mb_strlen($description)>255) {
      throw new InvalidArgumentException('invalid_dwelling_type');
    }
    if ($area!==null && (!preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\\.[0-9]{1,2})?$/D',$area) || (float)$area>99999999.99)) {
      throw new InvalidArgumentException('invalid_dwelling_area');
    }
    $layoutJson=null;
    if ($layout!==null) {
      $layoutJson=json_encode($layout,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
      if (strlen($layoutJson)>65535) throw new InvalidArgumentException('dwelling_layout_too_large');
    }
    $this->db->beginTransaction();
    try {
      $check=$this->db->prepare('SELECT id FROM office_buildings WHERE id=? FOR UPDATE');
      $check->execute([$buildingId]);
      if (!$check->fetchColumn()) throw new RuntimeException('building_not_found');
      $insert=$this->db->prepare('INSERT INTO office_building_dwelling_types (building_id,type_code,description,usable_area_m2,layout_json) VALUES (?,?,?,?,?)');
      $insert->execute([$buildingId,$code,$description,$area,$layoutJson]);
      $id=(int)$this->db->lastInsertId();
      $this->db->commit();
      return $id;
    } catch (\Throwable $error) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $error;
    }
  }

  /** Register a physical location such as a facade, roof or stairwell. */
  public function createPhysicalLocation(int $buildingId,string $category,string $name,string $description=''): int {
    require_once __DIR__.'/OfficeBuildingPhysicalLocation.php';
    OfficeBuildingPhysicalLocation::category($category);
    $name=trim($name);
    $description=trim($description);
    if ($buildingId<1 || $name==='' || mb_strlen($name)>160 || mb_strlen($description)>5000) {
      throw new InvalidArgumentException('invalid_physical_location');
    }
    $this->db->beginTransaction();
    try {
      $check=$this->db->prepare('SELECT id FROM office_buildings WHERE id=? FOR UPDATE');
      $check->execute([$buildingId]);
      if (!$check->fetchColumn()) throw new RuntimeException('building_not_found');
      // Store the category as a machine-readable prefix until a dedicated
      // category column is introduced through an explicit test migration.
      $storedDescription='['.$category.']'.($description!==''?' '.$description:'');
      $insert=$this->db->prepare('INSERT INTO office_building_demarcations (building_id,name,description) VALUES (?,?,?)');
      $insert->execute([$buildingId,$name,$storedDescription]);
      $id=(int)$this->db->lastInsertId();
      $this->db->commit();
      return $id;
    } catch (\Throwable $error) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $error;
    }
  }

  /**
   * Record an actual measured building component. Location, address and
   * dwelling type are independent optional dimensions, never multipliers.
   * Caller must enforce operator write permission and CSRF protection.
   */
  public function addInventoryItem(int $buildingId,string $code,string $description,string $quantity,string $unit,?int $locationId=null,?int $addressId=null,?int $dwellingTypeId=null): int {
    $code=trim($code);
    $description=trim($description);
    $unit=trim($unit);
    if ($buildingId<1 || $code==='' || strlen($code)>80 || $description==='' || mb_strlen($description)>255 || $unit==='' || mb_strlen($unit)>32) {
      throw new InvalidArgumentException('invalid_inventory_item');
    }
    if (!preg_match('/^(?:0|[1-9][0-9]{0,10})(?:\\.[0-9]{1,3})?$/D',$quantity) || (float)$quantity<=0) {
      throw new InvalidArgumentException('invalid_inventory_quantity');
    }
    $this->db->beginTransaction();
    try {
      $building=$this->db->prepare('SELECT id FROM office_buildings WHERE id=? FOR UPDATE');
      $building->execute([$buildingId]);
      if (!$building->fetchColumn()) throw new RuntimeException('building_not_found');
      foreach ([
        [$locationId,'office_building_demarcations'],
        [$addressId,'office_building_addresses'],
        [$dwellingTypeId,'office_building_dwelling_types']
      ] as [$id,$table]) {
        if ($id===null) continue;
        if ($id<1) throw new InvalidArgumentException('invalid_inventory_dimension');
        $check=$this->db->prepare("SELECT id FROM $table WHERE id=? AND building_id=?");
        $check->execute([$id,$buildingId]);
        if (!$check->fetchColumn()) throw new RuntimeException('inventory_dimension_not_in_building');
      }
      $insert=$this->db->prepare('INSERT INTO office_building_inventory_items (building_id,demarcation_id,address_id,dwelling_type_id,component_code,description,quantity,unit) VALUES (?,?,?,?,?,?,?,?)');
      $insert->execute([$buildingId,$locationId,$addressId,$dwellingTypeId,$code,$description,$quantity,$unit]);
      $id=(int)$this->db->lastInsertId();
      $this->db->commit();
      return $id;
    } catch (\Throwable $error) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $error;
    }
  }

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
