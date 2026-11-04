<?php
declare(strict_types=1);

namespace Brebo\Office\Inspection;

use InvalidArgumentException;
use PDO;

/**
 * Evidence metadata only. Never exposes storage keys in a public URL.
 * Caller must first verify inspection read permission through Office.
 */
final class OfficeInspectionEvidenceRepository {
  public function __construct(private PDO $db) {}

  public function forInspection(string $inspectionId): array {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D', $inspectionId)) {
      throw new InvalidArgumentException('invalid_inspection_identity');
    }
    $stmt=$this->db->prepare(
      'SELECT e.id,e.finding_id,e.mime_type,e.caption,e.created_at
       FROM office_inspection_evidence e
       INNER JOIN office_inspection_findings f ON f.id=e.finding_id
       WHERE f.inspection_id=?
       ORDER BY e.finding_id,e.id'
    );
    $stmt->execute([$inspectionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
