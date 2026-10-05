<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Service;

use Drupal\brebo_resident_service\Contract\ResidentAccessReadRepositoryInterface;

/** Builds an access-readiness look-ahead for upcoming work packages. */
final class LookAheadAccessReadiness {

  public function __construct(
    private readonly ResidentAccessReadRepositoryInterface $repository,
    private readonly WorkPackageAccessReadiness $workPackageReadiness,
  ) {}

  /** @return array<int,array<string,mixed>> */
  public function forProject(int $projectId, int $days = 42): array {
    $timezoneName = $this->repository->timezoneName();
    $timezone = new \DateTimeZone($timezoneName ?: 'Europe/Brussels');
    $today = new \DateTimeImmutable('today', $timezone);
    $horizon = $today->modify('+' . max(1, $days) . ' days');
    $rows = [];

    foreach ($this->repository->workPackagesForProject($projectId) as $package) {
      $start = $this->parseDate((string) $package['planned_start'], $timezone);
      if ($start === NULL || $start < $today || $start > $horizon) {
        continue;
      }

      $assessment = $this->workPackageReadiness->evaluate((int) $package['id']);
      $daysUntil = (int) $today->diff($start)->format('%a');
      $signal = $this->signal($assessment, $daysUntil);
      $rows[] = [
        'package_id' => (int) $package['id'],
        'package' => (string) $package['label'],
        'planned_start' => $start->format('Y-m-d'),
        'days_until_start' => $daysUntil,
        'signal' => $signal,
        'ready' => $assessment['ready'],
        'reason' => $assessment['reason'],
        'percentage' => $assessment['summary']['percentage'] ?? NULL,
        'attention' => $assessment['summary']['attention'] ?? 0,
        'project_id' => $assessment['project_id'] ?? $projectId,
        'building_nid' => $assessment['building_nid'] ?? NULL,
        'technical_zone_id' => $assessment['technical_zone_id'] ?? NULL,
      ];
    }

    return $rows;
  }

  private function signal(array $assessment, int $daysUntil): string {
    if (!$assessment['applicable'] || $assessment['ready']) {
      return 'groen';
    }
    return $daysUntil <= 7 ? 'rood' : 'oranje';
  }

  private function parseDate(string $value, \DateTimeZone $timezone): ?\DateTimeImmutable {
    $value = trim($value);
    if ($value === '') {
      return NULL;
    }
    foreach (['!Y-m-d', '!d-m-Y', '!d/m/Y'] as $format) {
      $date = \DateTimeImmutable::createFromFormat($format, $value, $timezone);
      if ($date instanceof \DateTimeImmutable) {
        return $date;
      }
    }
    try {
      return new \DateTimeImmutable($value, $timezone);
    }
    catch (\Throwable) {
      return NULL;
    }
  }

}
