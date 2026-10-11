<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/**
 * Independent CRM opportunity edit drafts; never mutates imported Drupal data.
 * Versioned optimistic locking prevents silent concurrent overwrites.
 */
final class WebsiteCrmOpportunityDraftStore {

  public function __construct(private readonly PDO $db) {}

  public static function schema(): string {
    return "CREATE TABLE IF NOT EXISTS office_crm_opportunity_draft (
      legacy_opportunity_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      stage VARCHAR(80) NOT NULL,
      version INT UNSIGNED NOT NULL DEFAULT 1,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
  }

  /** @return array<string,mixed>|null */
  public function get(int $id): ?array {
    if ($id < 1) {
      throw new \InvalidArgumentException('Invalid CRM opportunity ID.');
    }
    $stmt = $this->db->prepare('SELECT legacy_opportunity_id,title,stage,version,updated_at FROM office_crm_opportunity_draft WHERE legacy_opportunity_id=:id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
  }

  public function save(int $id, string $title, string $stage, int $expectedVersion): int {
    $title = trim($title);
    $stage = trim($stage);
    if ($id < 1 || $title === '' || mb_strlen($title) > 255
      || $stage === '' || mb_strlen($stage) > 80 || $expectedVersion < 0) {
      throw new \InvalidArgumentException('Invalid CRM draft update.');
    }
    $this->db->beginTransaction();
    try {
      $source = $this->db->prepare('SELECT legacy_node_id FROM office_crm_legacy_opportunity WHERE legacy_node_id=:id');
      $source->execute(['id' => $id]);
      if ($source->fetchColumn() === false) {
        throw new \RuntimeException('Source CRM opportunity does not exist.');
      }
      if ($expectedVersion === 0) {
        $stmt = $this->db->prepare('INSERT INTO office_crm_opportunity_draft (legacy_opportunity_id,title,stage,version) VALUES (:id,:title,:stage,1)');
        try {
          $stmt->execute(['id' => $id, 'title' => $title, 'stage' => $stage]);
        }
        catch (\PDOException $e) {
          if ($e->getCode() === '23000') {
            throw new \RuntimeException('CRM draft changed concurrently.', 0, $e);
          }
          throw $e;
        }
        $nextVersion = 1;
      }
      else {
        $stmt = $this->db->prepare('UPDATE office_crm_opportunity_draft SET title=:title,stage=:stage,version=version+1 WHERE legacy_opportunity_id=:id AND version=:version');
        $stmt->execute(['id' => $id, 'title' => $title, 'stage' => $stage, 'version' => $expectedVersion]);
        if ($stmt->rowCount() !== 1) {
          throw new \RuntimeException('CRM draft changed concurrently or is missing.');
        }
        $nextVersion = $expectedVersion + 1;
      }
      $this->db->commit();
      return $nextVersion;
    }
    catch (\Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      throw $e;
    }
  }
}
