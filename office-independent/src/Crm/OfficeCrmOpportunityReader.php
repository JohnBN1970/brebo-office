<?php
declare(strict_types=1);

namespace Brebo\Office\Crm;

use PDO;

/**
 * Read-only opportunity list for CRM views. Uses existing CRM tables and does
 * not infer building access from a company relationship.
 */
final class OfficeCrmOpportunityReader {
  public function __construct(private PDO $db) {}

  /** @return list<array{id:int,title:string,stage:string,source:string,value:?string,currency:string,companies:string}> */
  public function list(int $limit = 200): array {
    $limit = max(1, min(200, $limit));
    $sql = "SELECT o.id, o.title, o.stage, o.source, o.estimated_value, o.currency,
      GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS companies
      FROM office_crm_opportunities o
      LEFT JOIN office_crm_opportunity_companies oc ON oc.opportunity_id = o.id
      LEFT JOIN office_crm_companies c ON c.id = oc.company_id
      GROUP BY o.id, o.title, o.stage, o.source, o.estimated_value, o.currency
      ORDER BY o.updated_at DESC, o.id DESC LIMIT " . $limit;
    $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $row): array => [
      'id' => (int)$row['id'],
      'title' => (string)$row['title'],
      'stage' => (string)$row['stage'],
      'source' => (string)($row['source'] ?? ''),
      'value' => $row['estimated_value'] === null ? null : (string)$row['estimated_value'],
      'currency' => (string)$row['currency'],
      'companies' => (string)($row['companies'] ?? ''),
    ], $rows);
  }
}
