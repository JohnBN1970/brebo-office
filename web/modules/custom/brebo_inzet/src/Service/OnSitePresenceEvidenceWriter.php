<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Persists raw OnSite IN/OUT observations without booking work hours.
 */
final class OnSitePresenceEvidenceWriter {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly OnSiteAssignmentProvider $assignmentProvider,
  ) {}

  /**
   * @return array{id: int, project_id: string, zone_id: string, kind: string, occurred_at: string}
   */
  public function record(int $uid, string $projectId, string $zoneId, string $kind, string $occurredAt): array {
    if (!in_array($kind, ['in', 'out'], TRUE)) {
      throw new \InvalidArgumentException('Ongeldig OnSite eventtype.');
    }

    $projectIdInt = filter_var($projectId, FILTER_VALIDATE_INT);
    $zoneIdInt = filter_var($zoneId, FILTER_VALIDATE_INT);
    if ($projectIdInt === FALSE || $zoneIdInt === FALSE) {
      throw new \InvalidArgumentException('Ongeldige project- of zone-id.');
    }

    $occurred = new \DateTimeImmutable($occurredAt);
    $eventDate = $occurred->format('Y-m-d');
    $assignment = $this->findAssignment($uid, (int) $projectIdInt, (int) $zoneIdInt, $eventDate);
    if ($assignment === NULL) {
      throw new \RuntimeException('Projectzone is niet toegewezen aan deze medewerker.');
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $node = $storage->create([
      'type' => 'brebo_onsite_presence_event',
      'title' => sprintf(
        'OnSite %s gebruiker %d project %d %s',
        strtoupper($kind),
        $uid,
        (int) $projectIdInt,
        $occurred->format('Y-m-d H:i:s'),
      ),
      'field_brebo_clock_user' => ['target_id' => $uid],
      'field_brebo_project_ref' => ['target_id' => (int) $projectIdInt],
      'field_brebo_clock_zone_ref' => ['target_id' => (int) $zoneIdInt],
      'field_brebo_onsite_event_kind' => $kind,
      'field_brebo_onsite_occurred_at' => $occurred->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s'),
      'status' => 1,
    ]);
    $node->save();

    return [
      'id' => (int) $node->id(),
      'project_id' => (string) $projectIdInt,
      'zone_id' => (string) $zoneIdInt,
      'kind' => $kind,
      'occurred_at' => $occurred->format(DATE_ATOM),
    ];
  }

  /**
   * Returns a matching current assignment payload when project and zone belong together.
   */
  private function findAssignment(int $uid, int $projectId, int $zoneId, string $date): ?array {
    foreach ($this->assignmentProvider->currentForUser($uid, $date) as $project) {
      if ((int) $project['id'] !== $projectId) {
        continue;
      }
      foreach ($project['zones'] as $zone) {
        if ((int) $zone['id'] === $zoneId) {
          return $project;
        }
      }
    }
    return NULL;
  }

}
