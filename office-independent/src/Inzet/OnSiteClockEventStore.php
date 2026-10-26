<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Append-only receipt store for authorized and validated OnSite clock events.
 * Call only after server-side app authentication and project authorization.
 * A repeated event_id returns duplicate without altering the original record.
 */
final class OnSiteClockEventStore {
  public function __construct(private readonly PDO $db) {}

  /** @param array{event_id:string,project_id:int,person_id:int,action:string,occurred_at:string,latitude:?float,longitude:?float,accuracy_m:?float} $event
   * @return 'created'|'duplicate'
   */
  public function receive(array $event): string {
    $stmt = $this->db->prepare(
      'INSERT INTO office_inzet_clock_events
       (event_id, project_id, person_id, action, occurred_at, latitude, longitude, accuracy_m)
       VALUES (:event_id, :project_id, :person_id, :action, :occurred_at, :latitude, :longitude, :accuracy_m)'
    );
    try {
      $stmt->execute([
        ':event_id' => $event['event_id'], ':project_id' => $event['project_id'],
        ':person_id' => $event['person_id'], ':action' => $event['action'],
        ':occurred_at' => $event['occurred_at'], ':latitude' => $event['latitude'],
        ':longitude' => $event['longitude'], ':accuracy_m' => $event['accuracy_m'],
      ]);
      return 'created';
    } catch (\PDOException $exception) {
      // Only an existing idempotency key is a harmless retry. All other DB
      // errors (including unrelated unique constraints) must be reported.
      $lookup = $this->db->prepare('SELECT project_id, person_id, action, occurred_at, latitude, longitude, accuracy_m
        FROM office_inzet_clock_events WHERE event_id = :event_id');
      try {
        $lookup->execute([':event_id' => $event['event_id']]);
        $existing = $lookup->fetch(PDO::FETCH_ASSOC);
      } catch (\PDOException $lookupError) {
        throw $exception;
      }
      if (!is_array($existing)) throw $exception;
      foreach (['project_id', 'person_id', 'action', 'occurred_at', 'latitude', 'longitude', 'accuracy_m'] as $key) {
        if ($existing[$key] === null && $event[$key] === null) continue;
        if (in_array($key, ['latitude','longitude','accuracy_m'], true)) {
          if ($existing[$key] === null || $event[$key] === null || abs((float)$existing[$key] - (float)$event[$key]) > 0.0000001) throw new \DomainException('Gebeurtenisnummer bestaat al met andere inhoud.');
        } elseif ((string)$existing[$key] !== (string)$event[$key]) {
          throw new \DomainException('Gebeurtenisnummer bestaat al met andere inhoud.');
        }
      }
      return 'duplicate';
    }
  }
}
