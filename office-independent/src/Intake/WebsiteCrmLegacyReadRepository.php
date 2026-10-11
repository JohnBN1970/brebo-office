<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/**
 * Read-only CRM migration workbench projection. Not the final CRM write model.
 * No Drupal bootstrap or Drupal entity API is required.
 */
final class WebsiteCrmLegacyReadRepository {

  public function __construct(private readonly PDO $db) {}

  /** @return list<array<string,mixed>> */
  public function opportunities(int $limit = 50, int $offset = 0): array {
    $this->pagination($limit, $offset);
    $statement = $this->db->prepare('SELECT legacy_node_id,title,stage,owner_uid,website_request_id FROM office_crm_legacy_opportunity ORDER BY legacy_node_id LIMIT :limit OFFSET :offset');
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @return list<array<string,mixed>> */
  public function parties(string $type, int $limit = 50, int $offset = 0): array {
    if (!in_array($type, ['brebo_contact', 'brebo_organization'], true)) {
      throw new \InvalidArgumentException('Unsupported CRM party type.');
    }
    $this->pagination($limit, $offset);
    $statement = $this->db->prepare('SELECT legacy_node_id,party_type,title FROM office_crm_legacy_party WHERE party_type=:type ORDER BY legacy_node_id LIMIT :limit OFFSET :offset');
    $statement->bindValue(':type', $type);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @return list<array<string,mixed>> */
  public function history(int $legacyOpportunityId): array {
    if ($legacyOpportunityId < 1) {
      throw new \InvalidArgumentException('Invalid CRM opportunity ID.');
    }
    $statement = $this->db->prepare('SELECT legacy_event_node_id,legacy_opportunity_node_id,from_stage,to_stage,occurred_at FROM office_crm_legacy_stage_event WHERE legacy_opportunity_node_id=:id ORDER BY occurred_at,legacy_event_node_id');
    $statement->execute(['id' => $legacyOpportunityId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @return list<array<string,mixed>> */
  public function relations(int $legacyOpportunityId): array {
    if ($legacyOpportunityId < 1) {
      throw new \InvalidArgumentException('Invalid CRM opportunity ID.');
    }
    $statement = $this->db->prepare('SELECT source_field,legacy_target_type,legacy_target_id,office_target_id FROM office_crm_legacy_relation WHERE legacy_opportunity_id=:id ORDER BY source_field,legacy_target_id');
    $statement->execute(['id' => $legacyOpportunityId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  private function pagination(int $limit, int $offset): void {
    if ($limit < 1 || $limit > 200 || $offset < 0) {
      throw new \InvalidArgumentException('Invalid CRM pagination.');
    }
  }
}
