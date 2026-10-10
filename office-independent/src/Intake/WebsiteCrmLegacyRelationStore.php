<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/**
 * Independent, additive staging storage for reviewed CRM entity relations.
 * No Drupal entity IDs are treated as Office IDs.
 */
final class WebsiteCrmLegacyRelationStore {

  public function __construct(private readonly PDO $db) {}

  public static function schema(): string {
    return "CREATE TABLE IF NOT EXISTS office_crm_legacy_relation (
      legacy_opportunity_id BIGINT UNSIGNED NOT NULL,
      source_field VARCHAR(128) NOT NULL,
      legacy_target_type VARCHAR(80) NOT NULL,
      legacy_target_id BIGINT UNSIGNED NOT NULL,
      office_target_id VARCHAR(128) NOT NULL,
      imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (legacy_opportunity_id, source_field, legacy_target_type, legacy_target_id)
    )";
  }

  /**
   * @param list<array<string,int|string>> $resolved
   * @return int number of newly persisted relations
   */
  public function persist(array $resolved): int {
    $inserted = 0;
    $this->db->beginTransaction();
    try {
      $lookup = $this->db->prepare('SELECT office_target_id FROM office_crm_legacy_relation WHERE legacy_opportunity_id=:source AND source_field=:field AND legacy_target_type=:type AND legacy_target_id=:target FOR UPDATE');
      $insert = $this->db->prepare('INSERT INTO office_crm_legacy_relation (legacy_opportunity_id,source_field,legacy_target_type,legacy_target_id,office_target_id) VALUES (:source,:field,:type,:target,:office)');
      foreach ($resolved as $row) {
        $source = $row['legacy_node_id'] ?? null;
        $target = $row['target_id'] ?? null;
        $office = $row['office_target_id'] ?? null;
        $field = $row['field'] ?? null;
        $type = $row['target_type'] ?? null;
        if (!is_int($source) || $source < 1 || !is_int($target) || $target < 1
          || !is_string($field) || $field === '' || strlen($field) > 128
          || !is_string($type) || $type === '' || $type === 'unresolved' || strlen($type) > 80
          || (!is_int($office) && !is_string($office)) || trim((string) $office) === '' || (string) $office === '0'
          || strlen((string) $office) > 128) {
          throw new \InvalidArgumentException('Invalid reviewed CRM relation.');
        }
        $params = ['source' => $source, 'field' => $field, 'type' => $type, 'target' => $target];
        $lookup->execute($params);
        $existing = $lookup->fetchColumn();
        if ($existing !== false) {
          if ((string) $existing !== (string) $office) {
            throw new \RuntimeException('Conflicting CRM relation mapping; refusing overwrite.');
          }
          continue;
        }
        $insert->execute($params + ['office' => (string) $office]);
        $inserted++;
      }
      $this->db->commit();
      return $inserted;
    }
    catch (\Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      throw $e;
    }
  }
}
