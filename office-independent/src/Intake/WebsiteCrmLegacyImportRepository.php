<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/**
 * Canonical-independent CRM import staging, preserving the original node ID.
 * No destructive changes to Drupal or existing website intake tables.
 */
final class WebsiteCrmLegacyImportRepository {

  public function __construct(
    private readonly PDO $db,
    private readonly WebsiteCrmLegacyExportValidator $validator = new WebsiteCrmLegacyExportValidator(),
  ) {}

  public static function schema(): string {
    return "CREATE TABLE IF NOT EXISTS office_crm_legacy_opportunity (
      legacy_node_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      stage VARCHAR(80) NOT NULL,
      owner_uid BIGINT UNSIGNED NOT NULL,
      website_request_id CHAR(36) NULL UNIQUE,
      source_json LONGTEXT NOT NULL,
      imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )";
  }

  /** @param list<array<string,mixed>> $rows
   * @return array{records:int,inserted:int}
   */
  public function import(array $rows): array {
    $this->validator->validate($rows);
    $inserted = 0;
    $this->db->beginTransaction();
    try {
      $lookup = $this->db->prepare('SELECT source_json FROM office_crm_legacy_opportunity WHERE legacy_node_id = :id FOR UPDATE');
      $insert = $this->db->prepare('INSERT INTO office_crm_legacy_opportunity (legacy_node_id,title,stage,owner_uid,website_request_id,source_json) VALUES (:id,:title,:stage,:owner,:request,:source)');
      foreach ($rows as $row) {
        $source = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $lookup->execute(['id' => $row['legacy_node_id']]);
        $existing = $lookup->fetchColumn();
        if ($existing !== false) {
          if ($existing !== $source) {
            throw new \RuntimeException('Conflicting legacy CRM record; refusing overwrite.');
          }
          continue;
        }
        $insert->execute([
          'id' => $row['legacy_node_id'],
          'title' => $row['title'],
          'stage' => $row['stage'],
          'owner' => $row['owner_uid'],
          'request' => $row['website_request_id'] ?? null,
          'source' => $source,
        ]);
        $inserted++;
      }
      $this->db->commit();
      return ['records' => count($rows), 'inserted' => $inserted];
    }
    catch (\Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      throw $e;
    }
  }
}
