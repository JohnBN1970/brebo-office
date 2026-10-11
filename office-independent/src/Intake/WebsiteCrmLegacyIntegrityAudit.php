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
      'opportunities_missing_legacy_owner' => 'SELECT COUNT(*) FROM office_crm_legacy_opportunity WHERE owner_uid IS NULL OR owner_uid < 1',
      'stage_events' => 'SELECT COUNT(*) FROM office_crm_legacy_stage_event',
      'orphan_stage_events' => 'SELECT COUNT(*) FROM office_crm_legacy_stage_event e LEFT JOIN office_crm_legacy_opportunity o ON o.legacy_node_id=e.legacy_opportunity_node_id WHERE o.legacy_node_id IS NULL',
      'relations' => 'SELECT COUNT(*) FROM office_crm_legacy_relation',
      'orphan_relation_sources' => 'SELECT COUNT(*) FROM office_crm_legacy_relation r LEFT JOIN office_crm_legacy_opportunity o ON o.legacy_node_id=r.legacy_opportunity_id WHERE o.legacy_node_id IS NULL',
      'user_references_pending' => "SELECT COUNT(*) FROM office_crm_legacy_relation WHERE legacy_target_type='user'",
      'offer_references_pending' => "SELECT COUNT(*) FROM office_crm_legacy_relation WHERE legacy_target_type='offer'",
      'unknown_target_types' => "SELECT COUNT(*) FROM office_crm_legacy_relation WHERE legacy_target_type NOT IN ('brebo_contact','brebo_organization','user','offer')",
      'unverified_target_types' => "SELECT COUNT(*) FROM office_crm_legacy_relation WHERE legacy_target_type NOT IN ('brebo_contact','brebo_organization')",
      'missing_contact_targets' => "SELECT COUNT(*) FROM office_crm_legacy_relation r LEFT JOIN office_crm_legacy_party p ON p.party_type='brebo_contact' AND p.legacy_node_id=r.legacy_target_id WHERE r.legacy_target_type='brebo_contact' AND p.legacy_node_id IS NULL",
      'missing_organization_targets' => "SELECT COUNT(*) FROM office_crm_legacy_relation r LEFT JOIN office_crm_legacy_party p ON p.party_type='brebo_organization' AND p.legacy_node_id=r.legacy_target_id WHERE r.legacy_target_type='brebo_organization' AND p.legacy_node_id IS NULL",
    ];
    $counts = [];
    foreach ($queries as $key => $sql) {
      $counts[$key] = (int) $this->db->query($sql)->fetchColumn();
    }
    // The current staging contains no canonical Office user/offer registry to verify against.
    // Keep these references blocked until their independent destination adapters exist.
    $counts['blocking_issues'] = $counts['opportunities_missing_legacy_owner'] + $counts['orphan_stage_events'] + $counts['orphan_relation_sources'] + $counts['missing_contact_targets'] + $counts['missing_organization_targets'] + $counts['unverified_target_types'];
    return $counts;
  }
}
