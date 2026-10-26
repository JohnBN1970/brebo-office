<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Checks an already authenticated app identity against canonical personnel
 * and project membership. The app may not choose its own person identity.
 */
final class OnSiteClockEventAuthorization {
  public function __construct(private readonly PDO $db) {}

  public function canClock(int $authenticatedPersonId, int $projectId): bool {
    if ($authenticatedPersonId < 1 || $projectId < 1) return false;
    $stmt = $this->db->prepare(
      'SELECT 1
       FROM office_inzet_people p
       INNER JOIN office_inzet_project_team t ON t.person_id = p.id
       INNER JOIN office_projects project ON project.id = t.project_id
       WHERE p.id = :person_id AND t.project_id = :project_id
         AND p.active = 1 LIMIT 1'
    );
    $stmt->execute([':person_id' => $authenticatedPersonId, ':project_id' => $projectId]);
    return $stmt->fetchColumn() !== false;
  }

  /** @param array{person_id:int,project_id:int} $event */
  public function assertAuthorized(array $event, int $authenticatedPersonId): void {
    if ($event['person_id'] !== $authenticatedPersonId || !$this->canClock($authenticatedPersonId, $event['project_id'])) {
      throw new \DomainException('Geen toestemming om voor deze medewerker en dit project te klokken.');
    }
  }
}
