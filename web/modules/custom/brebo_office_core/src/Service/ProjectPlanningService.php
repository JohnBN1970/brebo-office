<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\brebo_office_core\Contract\ProjectPlanningRepositoryInterface;

/** Central published project planning interface for BREBO Office modules. */
final class ProjectPlanningService {

  public function __construct(
    private readonly ProjectPlanningRepositoryInterface $planningRepository,
  ) {}

  /**
   * Publishes a reviewed planning version as the single project truth.
   *
   * @param array<int,array<string,mixed>> $activities
   */
  public function publish(int $projectNid, string $sourceType, ?string $sourceReference, array $activities, int $uid): int {
    $sourceType = trim($sourceType);
    if ($projectNid <= 0 || $sourceType === '') {
      throw new \InvalidArgumentException('Project en planningsbron zijn verplicht.');
    }

    $normalizedActivities = [];
    foreach ($activities as $activity) {
      $plannedDate = trim((string) ($activity['planned_date'] ?? ''));
      if ($plannedDate === '' || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $plannedDate)) {
        throw new \InvalidArgumentException('Iedere planningsactiviteit moet een geldige datum JJJJ-MM-DD hebben.');
      }

      $normalizedActivities[] = [
        'activity_code' => trim((string) ($activity['activity_code'] ?? '')),
        'location_reference' => trim((string) ($activity['location_reference'] ?? '')),
        'activity_type' => trim((string) ($activity['activity_type'] ?? '')),
        'planned_date' => $plannedDate,
        'quantity' => max(0.0, (float) ($activity['quantity'] ?? 0)),
        'material_key' => isset($activity['material_key']) && trim((string) $activity['material_key']) !== ''
          ? trim((string) $activity['material_key'])
          : NULL,
      ];
    }

    $sourceReference = trim((string) $sourceReference);

    return $this->planningRepository->publish(
      $projectNid,
      $sourceType,
      $sourceReference !== '' ? $sourceReference : NULL,
      $normalizedActivities,
      $uid,
      time(),
    );
  }

  /** Quantity that must remain reserved from today through a required date. */
  public function reservedQuantity(int $projectNid, string $materialKey, string $throughDate): float {
    $materialKey = trim($materialKey);
    if ($projectNid <= 0 || $materialKey === '' || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $throughDate)) {
      return 0.0;
    }

    $today = date('Y-m-d');
    if ($throughDate < $today) {
      return 0.0;
    }

    return $this->planningRepository->reservedQuantity(
      $projectNid,
      $materialKey,
      $today,
      $throughDate,
    );
  }

}
