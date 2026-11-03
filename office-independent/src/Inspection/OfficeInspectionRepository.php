<?php
declare(strict_types=1);
namespace Brebo\Office\Inspection;

use InvalidArgumentException;
use PDO;

/** Storage only. Caller must enforce Office project/building permissions. */
final class OfficeInspectionRepository {
  public function __construct(private PDO $db) {}

  public function create(string $buildingId, ?string $projectId, string $title, string $date, string $operatorId): string {
    foreach (array_filter([$buildingId, $projectId, $operatorId], static fn($v) => $v !== null) as $id) {
      if (!preg_match('/^[1-9][0-9]{0,18}$/D', $id)) throw new InvalidArgumentException('invalid_inspection_identity');
    }
    if (trim($title) === '' || mb_strlen($title) > 200 || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D', $date)) {
      throw new InvalidArgumentException('invalid_inspection_details');
    }
    [$y,$m,$d] = array_map('intval', explode('-', $date));
    if (!checkdate($m,$d,$y)) throw new InvalidArgumentException('invalid_inspection_date');
    $stmt = $this->db->prepare("INSERT INTO office_inspections (building_id,project_id,title,inspection_date,created_by) SELECT id,?,?,?,? FROM office_buildings WHERE id=?");
    $stmt->execute([$projectId, trim($title), $date, $operatorId, $buildingId]);
    if ($stmt->rowCount() !== 1) throw new InvalidArgumentException('building_not_found');
    return $this->db->lastInsertId();
  }

  public function addFinding(string $inspectionId, string $code, string $observation, array $fields = []): string {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D', $inspectionId) || !preg_match('/^[A-Z][A-Z0-9-]{0,29}$/D', $code) || trim($observation) === '') {
      throw new InvalidArgumentException('invalid_finding');
    }
    $allowed = ['floor_label','room_label','building_part','assessment_framework','technical_assessment','consequences','remedy'];
    foreach ($fields as $key => $value) {
      if (!in_array($key, $allowed, true) || ($value !== null && !is_string($value))) throw new InvalidArgumentException('invalid_finding_field');
    }
    $columns = array_merge(['inspection_id','code','observation'], array_keys($fields));
    $placeholders = implode(',', array_fill(0,count($columns),'?'));
    $sql = 'INSERT INTO office_inspection_findings ('.implode(',',$columns).') VALUES ('.$placeholders.')';
    $stmt = $this->db->prepare($sql);
    $stmt->execute(array_merge([$inspectionId,$code,trim($observation)],array_values($fields)));
    return $this->db->lastInsertId();
  }

  public function findings(string $inspectionId): array {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D', $inspectionId)) throw new InvalidArgumentException('invalid_inspection_identity');
    $stmt=$this->db->prepare('SELECT * FROM office_inspection_findings WHERE inspection_id=? ORDER BY id');
    $stmt->execute([$inspectionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
