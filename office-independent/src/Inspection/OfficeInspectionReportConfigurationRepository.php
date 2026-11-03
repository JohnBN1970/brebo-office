<?php
declare(strict_types=1);

namespace Brebo\Office\Inspection;

use InvalidArgumentException;
use PDO;

/** Caller enforces Office permissions and CSRF before writes. */
final class OfficeInspectionReportConfigurationRepository {
  public function __construct(private PDO $db) {}

  public function save(string $inspectionId, string $operatorId, array $input): array {
    foreach ([$inspectionId, $operatorId] as $id) {
      if (!preg_match('/^[1-9][0-9]{0,18}$/D', $id)) throw new InvalidArgumentException('invalid_inspection_identity');
    }
    $config = OfficeInspectionReportConfiguration::normalize($input);
    $stmt = $this->db->prepare(
      'INSERT INTO office_inspection_report_configs (inspection_id,report_type,sections_json,notes,updated_by) ' .
      'SELECT id,?,?,?,? FROM office_inspections WHERE id=? AND status=? ' .
      'ON DUPLICATE KEY UPDATE report_type=VALUES(report_type), sections_json=VALUES(sections_json), notes=VALUES(notes), updated_by=VALUES(updated_by)'
    );
    $stmt->execute([$config['report_type'],json_encode($config['sections'],JSON_THROW_ON_ERROR),$config['notes'],$operatorId,$inspectionId,'draft']);
    if ($stmt->rowCount() === 0) throw new InvalidArgumentException('inspection_not_editable');
    return $config;
  }

  public function forInspection(string $inspectionId): ?array {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D', $inspectionId)) throw new InvalidArgumentException('invalid_inspection_identity');
    $stmt = $this->db->prepare('SELECT report_type,sections_json,notes FROM office_inspection_report_configs WHERE inspection_id=?');
    $stmt->execute([$inspectionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) return null;
    return OfficeInspectionReportConfiguration::normalize([
      'report_type'=>$row['report_type'],
      'sections'=>json_decode($row['sections_json'],true,512,JSON_THROW_ON_ERROR),
      'notes'=>$row['notes'] ?? ''
    ]);
  }
}
