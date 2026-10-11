<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/** Read-only reconciliation of CRM staging relationships before cutover. */
final class WebsiteCrmLegacyIntegrityAudit {

  public function __construct(private readonly PDO $db) {}

  /** @return array<string,int> */
  public function report(): array {
    $queries = [
      'opportunities' => 'SELECT COUNT(*) FROM office_crm_legacy_opportunity',
      'contacts' => "SELECT COUNT(*) FROM office_crm_legacy_party WHERE party_type='brebo_contact'",
      'organizations' => "SELECT COUNT(*) FROM office_crm_legacy_party WHERE party_type='brebo_organization'",
      'stage_events' => 'SELECT COUNT(*) FROM office_crm_legacy_stage_event',
      'orphan_stage_events' => 'SELECT COUNT(*) FROM office_crm_legacy_stage_event e LEFT JOIN office_crm_legacy_opportunity o ON o.legacy_node_id=e.legacy_opportunity_node_id WHERE o.legacy_node_id IS NULL',
      'relations' => 'SELECT COUNT(*) FROM office_crm_legacy_relation',
      'orphan_relation_sources' => 'SELECT COUNT(*) FROM office_crm_legacy_relation r LEFT JOIN office_crm_legacy_opportunity o ON o.legacy_node_id=r.legacy_opportunity_node_id WHERE o.legacy_node_id IS NULL',
    ];
    $counts = [];
    foreach ($queries as $key => $sql) {
      $counts[$key] = (int) $this->db->query($sql)->fetchColumn();
    }
    $counts['blocking_issues'] = $counts['orphan_stage_events'] + $counts['orphan_relation_sources'] + $counts['missing_contact_targets'] + $counts['missing_organization_targets'];
    return $counts;
  }
}
