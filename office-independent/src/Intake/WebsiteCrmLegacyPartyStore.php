<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/**
 * Independent staging of legacy CRM organizations and contacts.
 * Keeps source identity and raw values for review; not yet canonical CRM.
 */
final class WebsiteCrmLegacyPartyStore {

  public function __construct(private readonly PDO $db) {}

  public static function schema(): string {
    return "CREATE TABLE IF NOT EXISTS office_crm_legacy_party (
      legacy_node_id BIGINT UNSIGNED NOT NULL,
      party_type VARCHAR(32) NOT NULL,
      title VARCHAR(255) NOT NULL,
      source_json LONGTEXT NOT NULL,
      imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (party_type, legacy_node_id)
    )";
  }

  /**
   * @param list<array<string,mixed>> $records
   * @return int
   */
  public function import(array $records): int {
    $inserted = 0;
    $this->db->beginTransaction();
    try {
      $lookup = $this->db->prepare('SELECT source_json FROM office_crm_legacy_party WHERE party_type=:type AND legacy_node_id=:id FOR UPDATE');
      $insert = $this->db->prepare('INSERT INTO office_crm_legacy_party (party_type,legacy_node_id,title,source_json) VALUES (:type,:id,:title,:source)');
      $seen = [];
      foreach ($records as $record) {
        $type = $record['party_type'] ?? null;
        $id = $record['legacy_node_id'] ?? null;
        $title = $record['title'] ?? null;
        if (!in_array($type, ['brebo_contact', 'brebo_organization'], true)
          || !is_int($id) || $id < 1 || !is_string($title) || trim($title) === ''
          || !is_array($record['fields'] ?? null)) {
          throw new \InvalidArgumentException('Invalid legacy CRM contact or organization.');
        }
        $key = $type . ':' . $id;
        if (isset($seen[$key])) {
          throw new \InvalidArgumentException('Duplicate CRM party in import batch.');
        }
        $seen[$key] = true;
        $params = ['type' => $type, 'id' => $id];
        $source = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $lookup->execute($params);
        $existing = $lookup->fetchColumn();
        if ($existing !== false) {
          if ($existing !== $source) {
            throw new \RuntimeException('Conflicting legacy CRM party; refusing overwrite.');
          }
          continue;
        }
        $insert->execute($params + ['title' => $title, 'source' => $source]);
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
