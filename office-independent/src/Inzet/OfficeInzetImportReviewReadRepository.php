<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Test-only, read-only SQLite adapter. The caller MUST authenticate and
 * authorize the operator for the entire source import before invoking it.
 * No public endpoint, no production DSN, no write privileges.
 */
final class OfficeInzetImportReviewReadRepository {
  public function __construct(private PDO $db) {
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
      throw new \InvalidArgumentException('Only the isolated SQLite test source is supported.');
    }
  }

  /** @return array<string,mixed> */
  public function summary(): array {
    $personLabels = $this->labels('inzet_person_candidates', 'raw_label');
    $projectLabels = $this->labels('inzet_project_candidates', 'raw_label');
    $rowCount = (int)$this->db->query('SELECT COUNT(*) FROM inzet_source_workbench')->fetchColumn();
    $issueCount = (int)$this->db->query('SELECT COUNT(*) FROM review_issues')->fetchColumn();
    $issues = $this->db->query('SELECT w.sheet_name AS sheet, i.details AS cell, i.issue_code AS reason
      FROM review_issues i LEFT JOIN worksheets w ON w.id = i.sheet_id
      ORDER BY i.id ASC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
    return [
      'row_count'=>$rowCount,
      'person_label_count'=>count($personLabels),
      'project_label_count'=>count($projectLabels),
      'person_labels'=>$personLabels,
      'project_labels'=>$projectLabels,
      'warning_count'=>$issueCount,
      'warnings'=>$issues,
      'warnings_complete'=>$issueCount <= 200,
      'status'=>'review_required',
      'approved'=>false,
      'invoice_eligible'=>false,
    ];
  }

  /** @return list<string> */
  private function labels(string $table, string $column): array {
    // Identifiers are fixed internally; no user-controlled SQL identifiers.
    $stmt = $this->db->query('SELECT ' . $column . ' FROM ' . $table . ' ORDER BY ' . $column . ' ASC LIMIT 500');
    return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
  }
}
