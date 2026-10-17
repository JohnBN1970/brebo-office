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
    $personLabels = $this->sourceLabels('person_label');
    $projectLabels = $this->sourceLabels('project_label');
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
  private function sourceLabels(string $column): array {
    if (!in_array($column, ['person_label', 'project_label'], true)) {
      throw new \InvalidArgumentException('Unsupported source label.');
    }
    // Read from immutable imported source rows, not optional empty candidate tables.
    // No truncation: the UI can decide how many labels to display.
    $stmt = $this->db->query('SELECT DISTINCT TRIM(' . $column . ') AS label
      FROM inzet_source_workbench
      WHERE ' . $column . " IS NOT NULL AND TRIM(" . $column . ") <> ''
      ORDER BY label COLLATE NOCASE ASC");
    return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
  }
}
