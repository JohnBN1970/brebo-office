<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/**
 * Stages immutable Drupal CRM funnel events without modifying their source.
 * Legacy IDs remain provenance, not Office entity identities.
 */
final class WebsiteCrmLegacyStageEventStore {

  public function __construct(private readonly PDO $db) {}

  public static function schema(): string {
    return "CREATE TABLE IF NOT EXISTS office_crm_legacy_stage_event (
      legacy_event_node_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
      legacy_opportunity_node_id BIGINT UNSIGNED NOT NULL,
      from_stage VARCHAR(80) NOT NULL,
      to_stage VARCHAR(80) NOT NULL,
      occurred_at VARCHAR(64) NOT NULL,
      source_json LONGTEXT NOT NULL,
      imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY legacy_opportunity_events (legacy_opportunity_node_id)
    )";
  }

  /** @param list<array<string,mixed>> $events */
  public function import(array $events): int {
    $inserted = 0;
    $this->db->beginTransaction();
    try {
      $lookup = $this->db->prepare('SELECT source_json FROM office_crm_legacy_stage_event WHERE legacy_event_node_id=:id FOR UPDATE');
      $insert = $this->db->prepare('INSERT INTO office_crm_legacy_stage_event (legacy_event_node_id,legacy_opportunity_node_id,from_stage,to_stage,occurred_at,source_json) VALUES (:id,:opp,:from_stage,:to_stage,:occurred,:source)');
      $seen = [];
      foreach ($events as $event) {
        $id = $event['legacy_event_node_id'] ?? null;
        $opp = $event['legacy_opportunity_node_id'] ?? null;
        $from = $event['from_stage'] ?? null;
        $to = $event['to_stage'] ?? null;
        $occurred = $event['occurred_at'] ?? null;
        if (!is_int($id) || $id < 1 || !is_int($opp) || $opp < 1
          || !is_string($from) || strlen($from) > 80
          || !is_string($to) || trim($to) === '' || strlen($to) > 80
          || !is_string($occurred) || trim($occurred) === '' || strlen($occurred) > 64
          || isset($seen[$id])) {
          throw new \InvalidArgumentException('Invalid or duplicate CRM stage event.');
        }
        $seen[$id] = true;
        $source = json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $lookup->execute(['id' => $id]);
        $existing = $lookup->fetchColumn();
        if ($existing !== false) {
          if ($existing !== $source) {
            throw new \RuntimeException('Conflicting legacy CRM stage event; refusing overwrite.');
          }
          continue;
        }
        $insert->execute(['id' => $id, 'opp' => $opp, 'from_stage' => $from, 'to_stage' => $to, 'occurred' => $occurred, 'source' => $source]);
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
