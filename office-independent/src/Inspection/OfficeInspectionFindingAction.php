<?php
declare(strict_types=1);

namespace Brebo\Office\Inspection;

use InvalidArgumentException;
use PDO;

/**
 * Authorized mutation boundary for draft findings.
 * HTTP caller must verify Office session, origin and CSRF token.
 */
final class OfficeInspectionFindingAction {
  public function __construct(private PDO $db) {}

  public function save(
    string $inspectionId,
    string $operatorId,
    array $input,
    bool $includeInReport = true,
    ?string $findingId = null
  ): string {
    foreach ([$inspectionId,$operatorId,$findingId] as $id) {
      if ($id !== null && !preg_match('/^[1-9][0-9]{0,18}$/D',$id)) {
        throw new InvalidArgumentException('invalid_finding_identity');
      }
    }
    $code=$input['code'] ?? null;
    $observation=$input['observation'] ?? null;
    if (!is_string($code) || !preg_match('/^[A-Z][A-Z0-9-]{0,29}$/D',$code)
      || !is_string($observation) || trim($observation)==='') {
      throw new InvalidArgumentException('invalid_finding');
    }
    $limits=['floor_label'=>120,'room_label'=>120,'building_part'=>160];
    $textFields=['assessment_framework','technical_assessment','consequences','remedy'];
    $fields=[];
    foreach (array_merge(array_keys($limits),$textFields) as $key) {
      $value=$input[$key] ?? null;
      if ($value !== null && (!is_string($value) || (isset($limits[$key]) && mb_strlen($value)>$limits[$key]))) {
        throw new InvalidArgumentException('invalid_finding_field');
      }
      $fields[$key]=$value;
    }
    $ownTransaction=!$this->db->inTransaction();
    if (!$ownTransaction) throw new InvalidArgumentException('finding_requires_own_transaction');
    $this->db->beginTransaction();
    try {
      $permission=$this->db->prepare(
        "SELECT 1 FROM office_inspections i
         INNER JOIN office_project_buildings pb ON pb.building_id=i.building_id AND pb.project_id=i.project_id
         INNER JOIN office_project_memberships m ON m.project_id=pb.project_id
         INNER JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
         WHERE i.id=? AND i.status='draft' AND m.operator_id=? AND m.permission='manage' LIMIT 1"
      );
      $permission->execute([$inspectionId,$operatorId]);
      if ($permission->fetchColumn()===false) throw new InvalidArgumentException('finding_access_denied');
      if ($findingId===null) {
        $columns=array_merge(['inspection_id','code','observation'],array_keys($fields));
        $sql='INSERT INTO office_inspection_findings ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')';
        $stmt=$this->db->prepare($sql);
        $stmt->execute(array_merge([$inspectionId,$code,trim($observation)],array_values($fields)));
        $findingId=$this->db->lastInsertId();
      } else {
        $assignments=['code=?','observation=?'];
        foreach (array_keys($fields) as $field) $assignments[]=$field.'=?';
        $stmt=$this->db->prepare(
          'UPDATE office_inspection_findings SET '.implode(',',$assignments).' WHERE id=? AND inspection_id=? AND status=?'
        );
        $stmt->execute(array_merge([$code,trim($observation)],array_values($fields),[$findingId,$inspectionId,'draft']));
        if ($stmt->rowCount()===0) {
          $exists=$this->db->prepare('SELECT 1 FROM office_inspection_findings WHERE id=? AND inspection_id=? AND status=?');
          $exists->execute([$findingId,$inspectionId,'draft']);
          if ($exists->fetchColumn()===false) throw new InvalidArgumentException('finding_not_editable');
        }
      }
      $selection=$this->db->prepare(
        'INSERT INTO office_inspection_report_findings (inspection_id,finding_id,include_in_report,updated_by) VALUES (?,?,?,?) ' .
        'ON DUPLICATE KEY UPDATE include_in_report=VALUES(include_in_report),updated_by=VALUES(updated_by)'
      );
      $selection->execute([$inspectionId,$findingId,$includeInReport ? 1 : 0,$operatorId]);
      $this->db->commit();
      return $findingId;
    } catch (\Throwable $error) {
      $this->db->rollBack();
      throw $error;
    }
  }
}
