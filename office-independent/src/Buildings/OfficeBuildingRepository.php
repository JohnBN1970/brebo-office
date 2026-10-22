<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Office-native building register. Project relationships are many-to-many.
 * No Drupal services, entities or database connections.
 */
final class OfficeBuildingRepository {
  public function __construct(private PDO $db) {}

  public function create(array $input): string {
    $name=$input['building_name']??null;
    if (!is_string($name) || trim($name)==='' || strlen(trim($name))>255) {
      throw new InvalidArgumentException('invalid_building_name');
    }
    $code=$input['code']??null;
    if (!is_string($code) || trim($code)==='' || strlen($code)>128) throw new InvalidArgumentException('invalid_building_code');
    foreach (['street_address'=>255,'postal_code'=>32,'city'=>128] as $key=>$limit) {
      if (isset($input[$key]) && (!is_string($input[$key]) || strlen($input[$key])>$limit)) {
        throw new InvalidArgumentException('invalid_building_address');
      }
    }
    if (array_key_exists('latitude',$input) || array_key_exists('longitude',$input)) {
      throw new InvalidArgumentException('building_location_not_supported');
    }
    $stmt=$this->db->prepare('INSERT INTO office_buildings (code,name,address_line,postal_code,city) VALUES (?,?,?,?,?)');
    $stmt->execute([$code,trim($name),$input['street_address']??null,$input['postal_code']??null,$input['city']??null]);
    $id=$this->db->lastInsertId();
    if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,18}$/D',$id)) throw new RuntimeException('building_identity_unavailable');
    return $id;
  }

  public function belongsToProject(string $projectId,string $buildingId): bool {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D',$projectId)
      || !preg_match('/^[1-9][0-9]{0,18}$/D',$buildingId)) return false;
    $stmt=$this->db->prepare('SELECT 1 FROM office_project_buildings pb INNER JOIN office_buildings b ON b.id=pb.building_id WHERE pb.project_id=? AND pb.building_id=? LIMIT 1');
    $stmt->execute([$projectId,$buildingId]);
    return $stmt->fetchColumn()!==false;
  }

  public function forProject(string $projectId): array {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D',$projectId)) throw new InvalidArgumentException('invalid_project_id');
    $stmt=$this->db->prepare('SELECT b.id,b.name AS building_name,b.address_line AS street_address,b.postal_code,b.city FROM office_project_buildings pb INNER JOIN office_buildings b ON b.id=pb.building_id WHERE pb.project_id=? ORDER BY b.name,b.id');
    $stmt->execute([$projectId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function linkProject(string $projectId,string $buildingId,\Closure $authorizedProject): void {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D',$projectId)
      || !preg_match('/^[1-9][0-9]{0,18}$/D',$buildingId)
      || $authorizedProject($projectId)!==true) throw new InvalidArgumentException('unauthorized_project_building_link');
    $stmt=$this->db->prepare('INSERT INTO office_project_buildings (project_id,building_id) SELECT ?,id FROM office_buildings WHERE id=? ON DUPLICATE KEY UPDATE building_id=VALUES(building_id)');
    $stmt->execute([$projectId,$buildingId]);
    if (!$this->belongsToProject($projectId,$buildingId)) throw new InvalidArgumentException('building_not_found');
  }
}
