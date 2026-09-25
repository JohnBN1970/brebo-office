<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Persists raw OnSite IN/OUT observations without booking work hours.
 */
final class OnSitePresenceEvidenceWriter {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly OnSiteAssignmentProvider $assignmentProvider,
  ) {}

  /**
   * @return array{id:int,project_id:string,building_id:string,zone_id:string,kind:string,occurred_at:string}
   */
  public function record(
    int $uid,
    string $projectId,
    string $zoneId,
    string $kind,
    string $occurredAt,
    string $buildingId = '',
  ): array {
    if (!in_array($kind, ['in', 'out'], TRUE)) {
      throw new \InvalidArgumentException('Ongeldig OnSite eventtype.');
    }

    try {
      $occurred = new \DateTimeImmutable($occurredAt);
    }
    catch (\Throwable) {
      throw new \InvalidArgumentException('Ongeldig tijdstip.');
    }

    $projectIdInt = $this->positiveIntOrZero($projectId);
    $zoneIdInt = $this->positiveIntOrZero($zoneId);
    $buildingIdInt = $this->positiveIntOrZero($buildingId);

    $storage = $this->entityTypeManager->getStorage('node');
    $zone = $zoneIdInt > 0 ? $storage->load($zoneIdInt) : NULL;
    if ($zoneIdInt > 0 && (!$zone instanceof NodeInterface || $zone->bundle() !== 'brebo_clock_zone')) {
      throw new \InvalidArgumentException('Ongeldige personeelszone.');
    }

    if ($zone instanceof NodeInterface) {
      $zoneBuildingId = $zone->hasField('field_brebo_building_ref')
        ? (int) ($zone->get('field_brebo_building_ref')->target_id ?? 0) : 0;
      $zoneProjectId = (int) ($zone->get('field_brebo_project_ref')->target_id ?? 0);
      if ($buildingIdInt <= 0) {
        $buildingIdInt = $zoneBuildingId;
      }
      elseif ($zoneBuildingId > 0 && $zoneBuildingId !== $buildingIdInt) {
        throw new \InvalidArgumentException('Personeelszone hoort bij een ander gebouw.');
      }
      if ($projectIdInt <= 0) {
        $projectIdInt = $zoneProjectId;
      }
      elseif ($zoneProjectId > 0 && $zoneProjectId !== $projectIdInt) {
        throw new \InvalidArgumentException('Personeelszone hoort bij een ander project.');
      }
    }

    if ($buildingIdInt <= 0 && $projectIdInt <= 0) {
      throw new \InvalidArgumentException('Gebouw of project ontbreekt.');
    }

    if ($buildingIdInt > 0) {
      $building = $storage->load($buildingIdInt);
      if (!$building instanceof NodeInterface || $building->bundle() !== 'brebo_building') {
        throw new \InvalidArgumentException('Ongeldig gebouw.');
      }
    }
    if ($projectIdInt > 0) {
      $project = $storage->load($projectIdInt);
      if (!$project instanceof NodeInterface || $project->bundle() !== 'brebo_project') {
        throw new \InvalidArgumentException('Ongeldig project.');
      }
    }

    // Raw presence is intentionally NOT gated by project team or planning.
    // Assignment validation belongs to downstream interpretation, because the
    // app must be able to observe an employee at any known BREBO building.
    $node = $storage->create([
      'type' => 'brebo_onsite_presence_event',
      'title' => sprintf(
        'OnSite %s gebruiker %d gebouw %d project %d %s',
        strtoupper($kind),
        $uid,
        $buildingIdInt,
        $projectIdInt,
        $occurred->format('Y-m-d H:i:s'),
      ),
      'field_brebo_clock_user' => ['target_id' => $uid],
      'field_brebo_project_ref' => $projectIdInt > 0 ? ['target_id' => $projectIdInt] : NULL,
      'field_brebo_building_ref' => $buildingIdInt > 0 ? ['target_id' => $buildingIdInt] : NULL,
      'field_brebo_clock_zone_ref' => $zoneIdInt > 0 ? ['target_id' => $zoneIdInt] : NULL,
      'field_brebo_onsite_event_kind' => $kind,
      'field_brebo_onsite_occurred_at' => $occurred->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s'),
      'status' => 1,
    ]);
    $node->save();

    return [
      'id' => (int) $node->id(),
      'project_id' => $projectIdInt > 0 ? (string) $projectIdInt : '',
      'building_id' => $buildingIdInt > 0 ? (string) $buildingIdInt : '',
      'zone_id' => $zoneIdInt > 0 ? (string) $zoneIdInt : '',
      'kind' => $kind,
      'occurred_at' => $occurred->format(DATE_ATOM),
    ];
  }

  private function positiveIntOrZero(string $value): int {
    $value = trim($value);
    if ($value === '') {
      return 0;
    }
    $validated = filter_var($value, FILTER_VALIDATE_INT);
    if ($validated === FALSE || (int) $validated <= 0) {
      throw new \InvalidArgumentException('Ongeldige identifier.');
    }
    return (int) $validated;
  }

}
