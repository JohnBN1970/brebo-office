<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Read-only test import issue context. Labels are unverified Excel source text.
 */
final class OfficeInzetIssueContextReader {
  public function __construct(private PDO $source) {
    if ($source->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
      throw new \InvalidArgumentException('SQLite source required.');
    }
  }

  /** @return list<array<string,mixed>> */
  public function rows(int $limit = 200): array {
    if ($limit < 1 || $limit > 500) {
      throw new \InvalidArgumentException('Invalid page size.');
    }
    $stmt = $this->source->prepare(
      'SELECT i.id AS issue_id, w.sheet_name AS sheet, i.issue_code AS reason,
              i.details AS cell, s.person_label AS person, s.project_label AS project,
              s.row_number AS source_row
       FROM review_issues i
       LEFT JOIN worksheets w ON w.id = i.sheet_id
       LEFT JOIN inzet_source_workbench s ON s.source_row_id = i.source_row_id
       ORDER BY i.id ASC LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
