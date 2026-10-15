<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/**
 * Read-only boundary for reconciling staging leads before CRM cutover.
 * Never treats staging opportunity IDs as canonical Office CRM identifiers.
 */
final class WebsiteIntakeReconciliation {

  public function __construct(private readonly PDO $db) {}

  /** @return array<string,int> */
  public function summary(): array {
    $sql = "SELECT
      (SELECT COUNT(*) FROM office_website_intake) AS intake_count,
      (SELECT COUNT(*) FROM office_website_opportunity) AS staging_lead_count,
      (SELECT COUNT(*) FROM office_website_intake i LEFT JOIN office_website_opportunity o ON o.request_id = i.request_id WHERE o.id IS NULL) AS missing_staging_leads,
      (SELECT COUNT(*) FROM office_website_opportunity o LEFT JOIN office_website_intake i ON i.request_id = o.request_id WHERE i.request_id IS NULL) AS orphan_staging_leads,
      (SELECT COUNT(*) FROM office_website_opportunity WHERE requires_review <> 1 OR stage <> 'Lead') AS unexpected_review_state";
    $row = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
    return array_map('intval', $row ?: []);
  }

  /** @return list<array{request_id:string,staging_id:int,title:string,stage:string}> */
  public function stagedLeads(int $limit = 100, int $offset = 0): array {
    if ($limit < 1 || $limit > 1000 || $offset < 0) {
      throw new \InvalidArgumentException('Invalid reconciliation page.');
    }
    $stmt = $this->db->prepare('SELECT request_id, id AS staging_id, title, stage FROM office_website_opportunity ORDER BY id LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return array_map(static fn (array $row): array => [
      'request_id' => $row['request_id'],
      'staging_id' => (int) $row['staging_id'],
      'title' => $row['title'],
      'stage' => $row['stage'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
  }
}
