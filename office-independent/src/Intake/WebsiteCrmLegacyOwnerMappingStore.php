<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/**
 * Explicit, operator-approved legacy owner mapping.
 * This staging map never assumes Drupal UID equals an Office user identity.
 */
final class WebsiteCrmLegacyOwnerMappingStore {

  public function __construct(
    private readonly PDO $db,
    private readonly ?WebsiteCrmOfficeUserDirectoryInterface $officeUsers = null,
  ) {}

  public static function schema(): string {
    return "CREATE TABLE IF NOT EXISTS office_crm_legacy_owner_mapping (
      legacy_uid BIGINT UNSIGNED NOT NULL PRIMARY KEY,
      office_user_id VARCHAR(128) NOT NULL UNIQUE,
      approved_by VARCHAR(128) NOT NULL,
      approved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )";
  }

  /** @param list<array{legacy_uid:int,office_user_id:string,approved_by:string}> $mappings */
  public function persist(array $mappings): int {
    $inserted = 0;
    $this->db->beginTransaction();
    try {
      $lookup = $this->db->prepare('SELECT office_user_id,approved_by FROM office_crm_legacy_owner_mapping WHERE legacy_uid=:uid FOR UPDATE');
      $insert = $this->db->prepare('INSERT INTO office_crm_legacy_owner_mapping (legacy_uid,office_user_id,approved_by) VALUES (:uid,:office,:approver)');
      $seen = [];
      foreach ($mappings as $mapping) {
        $uid = $mapping['legacy_uid'] ?? null;
        $office = $mapping['office_user_id'] ?? null;
        $approver = $mapping['approved_by'] ?? null;
        if (!is_int($uid) || $uid < 1 || !is_string($office) || trim($office) === ''
          || strlen($office) > 128 || !is_string($approver) || trim($approver) === ''
          || strlen($approver) > 128 || isset($seen[$uid])) {
          throw new \InvalidArgumentException('Invalid or duplicate approved CRM owner mapping.');
        }
        if ($this->officeUsers === null || !$this->officeUsers->isActiveOfficeUser($office)) {
          throw new \RuntimeException('Office user not verified; refusing CRM owner mapping.');
        }
        $seen[$uid] = true;
        $lookup->execute(['uid' => $uid]);
        $existing = $lookup->fetch(PDO::FETCH_ASSOC);
        if ($existing !== false) {
          if ($existing['office_user_id'] !== $office || $existing['approved_by'] !== $approver) {
            throw new \RuntimeException('Conflicting CRM owner mapping; refusing overwrite.');
          }
          continue;
        }
        $insert->execute(['uid' => $uid, 'office' => $office, 'approver' => $approver]);
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
