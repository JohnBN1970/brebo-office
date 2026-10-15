<?php

declare(strict_types=1);

namespace Brebo\Office\RecentWork;

use PDO;

/**
 * Persists a meaningful work position for an authenticated operator.
 * The caller MUST authorize the referenced resource before recording it.
 * Schema deployment and wiring are separate, feature-flagged steps.
 */
final class PdoRecentWorkRepository {
  public function __construct(private readonly PDO $db) {}

  public function record(string $operatorId, RecentWorkEvent $event): void {
    if ($operatorId === '' || strlen($operatorId) > 128) {
      throw new \InvalidArgumentException('Authenticated operator required.');
    }
    $this->db->beginTransaction();
    try {
      $stmt = $this->db->prepare(
        'INSERT INTO office_user_recent_work
           (operator_id, module, resource_type, resource_id, view_key, position_json, updated_at)
         VALUES (:operator_id, :module, :resource_type, :resource_id, :view_key, :position, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE position_json = VALUES(position_json), updated_at = UTC_TIMESTAMP()'
      );
      $stmt->execute([
        ':operator_id' => $operatorId,
        ':module' => $event->module,
        ':resource_type' => $event->resourceType,
        ':resource_id' => $event->resourceId,
        ':view_key' => $event->viewKey,
        ':position' => $event->positionJson(),
      ]);
      $prune = $this->db->prepare(
        'DELETE FROM office_user_recent_work
         WHERE operator_id = :operator_id
           AND id NOT IN (
             SELECT id FROM (
               SELECT id FROM office_user_recent_work
               WHERE operator_id = :inner_operator_id
               ORDER BY updated_at DESC, id DESC LIMIT 5
             ) AS keep_recent
           )'
      );
      $prune->execute([':operator_id' => $operatorId, ':inner_operator_id' => $operatorId]);
      $this->db->commit();
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      throw $e;
    }
  }
}
