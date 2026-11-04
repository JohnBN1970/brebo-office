<?php
declare(strict_types=1);
namespace Brebo\Office\Inspection;
use PDO;
use InvalidArgumentException;
use RuntimeException;

/** Only returns bytes after a project-scoped, active-operator permission check. */
final class OfficeInspectionEvidenceReadAction {
  public function __construct(private PDO $db, private string $storageRoot) {}
  public function locate(string $inspectionId,string $evidenceId,string $operatorId): array {
    foreach ([$inspectionId,$evidenceId,$operatorId] as $id) {
      if (!preg_match('/^[1-9][0-9]{0,18}$/D',$id)) throw new InvalidArgumentException('invalid_evidence_identity');
    }
    $stmt=$this->db->prepare(
      "SELECT e.storage_key,e.mime_type
       FROM office_inspection_evidence e
       INNER JOIN office_inspection_findings f ON f.id=e.finding_id
       INNER JOIN office_inspections i ON i.id=f.inspection_id
       INNER JOIN office_project_buildings pb ON pb.project_id=i.project_id AND pb.building_id=i.building_id
       INNER JOIN office_project_memberships m ON m.project_id=pb.project_id
       INNER JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
       WHERE e.id=? AND i.id=? AND m.operator_id=? AND m.permission IN ('read','manage')
       LIMIT 1"
    );
    $stmt->execute([$evidenceId,$inspectionId,$operatorId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if ($row===false) throw new InvalidArgumentException('evidence_not_found');
    $key=$row['storage_key'];
    if (!is_string($key) || !preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/D',$key)) throw new RuntimeException('invalid_evidence_storage_key');
    $mime=$row['mime_type'];
    if (!in_array($mime,['image/jpeg','image/png','image/webp'],true)) throw new RuntimeException('invalid_evidence_mime');
    $root=realpath($this->storageRoot);
    if ($root===false) throw new RuntimeException('evidence_storage_unavailable');
    $path=realpath($root.DIRECTORY_SEPARATOR.$key);
    if ($path===false || dirname($path)!==$root || !is_file($path) || !is_readable($path)) throw new RuntimeException('evidence_file_unavailable');
    return ['path'=>$path,'mime_type'=>$mime,'size'=>filesize($path)];
  }
}
