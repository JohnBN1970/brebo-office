<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Builds an access-readiness look-ahead for upcoming work packages. */
final class LookAheadAccessReadiness {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly WorkPackageAccessReadiness $workPackageReadiness,
  ) {}

  /**
   * @return array<int, array<string, mixed>>
   */
  public function forProject(int $projectId, int $days = 42): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_work_package')
      ->condition('field_brebo_project_ref', $projectId)
      ->sort('field_brebo_planned_start', 'ASC')
      ->execute();

    $today = new \DateTimeImmutable('today');
    $horizon = $today->modify('+' . max(1, $days) . ' days');
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $package) {
      if (!$package instanceof NodeInterface) {
        continue;
      }
      $start = $this->parseDate($package->hasField('field_brebo_planned_start') ? (string) $package->get('field_brebo_planned_start')->value : '');
      if ($start === NULL || $start < $today || $start > $horizon) {
        continue;
      }
      $assessment = $this->workPackageReadiness->evaluate($package);
      $daysUntil = (int) $today->diff($start)->format('%a');
      $signal = $this->signal($assessment, $daysUntil);
      $rows[] = [
        'package_id' => (int) $package->id(),
        'package' => $package->label(),
        'planned_start' => $start->format('Y-m-d'),
        'days_until_start' => $daysUntil,
        'signal' => $signal,
        'ready' => $assessment['ready'],
        'reason' => $assessment['reason'],
        'percentage' => $assessment['summary']['percentage'] ?? NULL,
        'attention' => $assessment['summary']['attention'] ?? 0,
      ];
    }
    return $rows;
  }

  private function signal(array $assessment, int $daysUntil): string {
    if (!$assessment['applicable'] || $assessment['ready']) {
      return 'groen';
    }
    // Red means unresolved access is close enough to threaten execution.
    return $daysUntil <= 7 ? 'rood' : 'oranje';
  }

  private function parseDate(string $value): ?\DateTimeImmutable {
    $value = trim($value);
    if ($value === '') {
      return NULL;
    }
    foreach (['!Y-m-d', '!d-m-Y', '!d/m/Y'] as $format) {
      $date = \DateTimeImmutable::createFromFormat($format, $value);
      if ($date instanceof \DateTimeImmutable) {
        return $date;
      }
    }
    try {
      return new \DateTimeImmutable($value);
    }
    catch (\Throwable) {
      return NULL;
    }
  }
}
