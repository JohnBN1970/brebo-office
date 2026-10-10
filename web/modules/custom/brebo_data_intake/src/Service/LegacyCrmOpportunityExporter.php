<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Read-only, paginated inventory for a reviewed legacy CRM migration.
 * This service does not create, update or delete Drupal entities.
 */
final class LegacyCrmOpportunityExporter {

  public function __construct(private readonly EntityTypeManagerInterface $entities) {}

  /**
   * @return list<array<string,mixed>>
   */
  public function exportPage(int $offset = 0, int $limit = 100): array {
    if ($offset < 0 || $limit < 1 || $limit > 500) {
      throw new \InvalidArgumentException('Invalid CRM export pagination.');
    }
    $storage = $this->entities->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_opportunity')
      ->sort('nid', 'ASC')
      ->range($offset, $limit)
      ->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_opportunity') {
        continue;
      }
      $fields = [];
      foreach ($node->getFields() as $name => $field) {
        // Preserve raw field values for explicit mapping and reconciliation.
        // Never export computed entity objects or silently infer full request
        // UUIDs from the short identifier embedded in a Drupal title.
        $fields[$name] = $field->getValue();
      }
      $rows[] = [
        'legacy_node_id' => (int) $node->id(),
        'title' => (string) $node->label(),
        'stage' => $this->fieldValue($node, 'field_brebo_opp_stage'),
        'owner_uid' => (int) $node->getOwnerId(),
        'assigned_owner_uid' => $node->hasField('field_brebo_opp_owner') && !$node->get('field_brebo_opp_owner')->isEmpty()
          ? (int) $node->get('field_brebo_opp_owner')->target_id
          : null,
        'website_request_id' => null,
        'status' => (int) $node->isPublished(),
        'created' => (int) $node->getCreatedTime(),
        'changed' => (int) $node->getChangedTime(),
        'fields' => $fields,
      ];
    }
    return $rows;
  }

  private function fieldValue(NodeInterface $node, string $name): string {
    if (!$node->hasField($name) || $node->get($name)->isEmpty()) {
      return '';
    }
    return (string) ($node->get($name)->value ?? '');
  }
}
