<?php
declare(strict_types=1);
namespace Brebo\Office\Project;

use PDO;
use InvalidArgumentException;
use RuntimeException;

final class OfficeBuildingElementRepository
{
    public function __construct(private PDO $db) {}

    public function forBuilding(int $buildingId): array
    {
        $q=$this->db->prepare('SELECT e.* FROM office_building_elements e LEFT JOIN office_housing_types h ON h.id=e.housing_type_id AND h.building_id=e.building_id WHERE e.building_id=? AND (e.housing_type_id IS NULL OR h.id IS NOT NULL) ORDER BY e.classification_code,e.element_code,e.id');
        $q->execute([$buildingId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    // Caller must enforce building write permission and CSRF.
    public function create(int $buildingId, ?int $housingTypeId, string $classification, string $code, string $description, string $unit, string $quantity, string $source, ?string $reference=null): int
    {
        if ($buildingId<1 || !preg_match('/^[0-9]{2}$/D',$classification)
            || trim($code)==='' || strlen($code)>100 || trim($description)==='' || strlen($description)>255
            || trim($unit)==='' || strlen($unit)>30 || !preg_match('/^[0-9]{1,14}(?:\.[0-9]{1,4})?$/D',$quantity)
            || !in_array($source,['manual','drawing','measurement'],true)
            || ($reference!==null && strlen($reference)>255)) {
            throw new InvalidArgumentException('INVALID_BUILDING_ELEMENT');
        }
        $q=$this->db->prepare('INSERT INTO office_building_elements (building_id,housing_type_id,classification_code,element_code,description,unit,quantity,source_kind,source_reference) SELECT b.id,?,?,?,?,?,?,?,? FROM office_buildings b WHERE b.id=? AND (? IS NULL OR EXISTS (SELECT 1 FROM office_housing_types h WHERE h.id=? AND h.building_id=b.id))');
        $q->execute([$housingTypeId,$classification,trim($code),trim($description),trim($unit),$quantity,$source,$reference,$buildingId,$housingTypeId,$housingTypeId]);
        if($q->rowCount()!==1) throw new RuntimeException('INVALID_BUILDING_OR_HOUSING_TYPE');
        return (int)$this->db->lastInsertId();
    }
}
