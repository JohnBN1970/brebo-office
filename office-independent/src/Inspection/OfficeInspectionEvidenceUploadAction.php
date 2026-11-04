<?php
declare(strict_types=1);

namespace Brebo\Office\Inspection;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/** Private photo storage for authorized draft findings; HTTP layer enforces session, origin and CSRF. */
final class OfficeInspectionEvidenceUploadAction {
  public function __construct(private PDO $db, private string $storageRoot) {}

  public function upload(string $inspectionId, string $findingId, string $operatorId, array $file, string $caption=''): string {
    foreach ([$inspectionId,$findingId,$operatorId] as $id) {
      if (!preg_match('/^[1-9][0-9]{0,18}$/D',$id)) throw new InvalidArgumentException('invalid_evidence_identity');
    }
    if (mb_strlen($caption)>500) throw new InvalidArgumentException('invalid_evidence_caption');
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK
      || !is_int($file['size'] ?? null) || $file['size']<1 || $file['size']>10*1024*1024
      || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
      throw new InvalidArgumentException('invalid_evidence_upload');
    }
    $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] ?? null;
    if ($ext===null || @getimagesize($file['tmp_name'])===false) throw new InvalidArgumentException('unsupported_evidence_image');
    if ($this->db->inTransaction()) throw new RuntimeException('evidence_requires_own_transaction');
    $permission=$this->db->prepare(
      "SELECT 1 FROM office_inspection_findings f
       INNER JOIN office_inspections i ON i.id=f.inspection_id
       INNER JOIN office_project_buildings pb ON pb.building_id=i.building_id AND pb.project_id=i.project_id
       INNER JOIN office_project_memberships m ON m.project_id=pb.project_id
       INNER JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
       WHERE i.id=? AND f.id=? AND i.status='draft' AND f.status='draft'
         AND m.operator_id=? AND m.permission='manage' LIMIT 1"
    );
    $permission->execute([$inspectionId,$findingId,$operatorId]);
    if ($permission->fetchColumn()===false) throw new InvalidArgumentException('evidence_access_denied');
    $root=realpath($this->storageRoot);
    if ($root===false || !is_dir($root) || !is_writable($root)) throw new RuntimeException('private_evidence_storage_unavailable');
    $key=bin2hex(random_bytes(24)).'.'.$ext;
    $target=$root.DIRECTORY_SEPARATOR.$key;
    if (!move_uploaded_file($file['tmp_name'],$target)) throw new RuntimeException('evidence_storage_failed');
    chmod($target,0600);
    try {
      $this->db->beginTransaction();
      $stmt=$this->db->prepare('INSERT INTO office_inspection_evidence (finding_id,storage_key,mime_type,caption) VALUES (?,?,?,?)');
      $stmt->execute([$findingId,$key,$mime,trim($caption)]);
      $id=$this->db->lastInsertId();
      $this->db->commit();
      return $id;
    } catch (\Throwable $error) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      @unlink($target);
      throw $error;
    }
  }
}
