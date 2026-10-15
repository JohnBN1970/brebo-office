<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/** Read-only reconciliation of CRM staging relationships before cutover. */
final class WebsiteCrmLegacyIntegrityAudit {

  public function __construct(
    private readonly PDO $db,
    private readonly ?WebsiteCrmOfficeUserDirectoryInterface $officeUsers = null,
  ) {}

  /** @return array<string,int> */
  public function report(): array {
    $queries = [
      'opportunities' => 'SELECT COUNT(*) FROM office_crm_legacy_opportunity',
      'contacts' => "SELECT COUNT(*) FROM office_crm_legacy_party WHERE party_type='brebo_contact'",
      'organizations' => "SELECT COUNT(*) FROM office_crm_legacy_party WHERE party_type='brebo_organization'",
      'opportunity_owners_pending_mapping' => 'SELECT COUNT(*) FROM office_crm_legacy_opportunity o LEFT JOIN office_crm_legacy_owner_mapping m ON m.legacy_uid=o.owner_uid WHERE o.owner_uid > 0 AND m.legacy_uid IS NULL',
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
    // An approved mapping is not proof that the Office user remains active.
    // Without a trusted directory, all mapped owners remain blocked.
    $mappedOwners = $this->db->query('SELECT DISTINCT m.office_user_id FROM office_crm_legacy_opportunity o INNER JOIN office_crm_legacy_owner_mapping m ON m.legacy_uid=o.owner_uid')->fetchAll(PDO::FETCH_COLUMN);
    $counts['opportunity_owners_unverified_or_inactive'] = 0;
    foreach ($mappedOwners as $officeUserId) {
      if ($this->officeUsers === null || !$this->officeUsers->isActiveOfficeUser((string) $officeUserId)) {
        $counts['opportunity_owners_unverified_or_inactive'] += (int) $this->db->query('SELECT COUNT(*) FROM office_crm_legacy_opportunity o INNER JOIN office_crm_legacy_owner_mapping m ON m.legacy_uid=o.owner_uid WHERE m.office_user_id=' . $this->db->quote((string) $officeUserId))->fetchColumn();
      }
    }
    // Legacy owner UID is not an Office user identity; it requires explicit approved mapping.
    // The current staging contains no canonical Office user/offer registry to verify against.
    // Keep these references blocked until their independent destination adapters exist.
    $counts['blocking_issues'] = $counts['opportunities_missing_legacy_owner'] + $counts['opportunity_owners_pending_mapping'] + $counts['opportunity_owners_unverified_or_inactive'] + $counts['orphan_stage_events'] + $counts['orphan_relation_sources'] + $counts['missing_contact_targets'] + $counts['missing_organization_targets'] + $counts['unverified_target_types'];
    return $counts;
  }
}
