<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Builds a read-only whole-project workforce proposal from Office truths.
 */
final class ProjectInzetProposalBuilder {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * @return array{start:?string,end:?string,budget_hours:float,workdays:int,people:int,hours_per_person_day:float,proposed_hours:float,delta_hours:float}
   */
  public function build(NodeInterface $project, array $userIds, ?string $start = NULL, ?string $end = NULL, string $startTime = '07:00', string $endTime = '16:00'): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $packageIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_work_package')
      ->condition('field_brebo_project_ref', (int) $project->id())
      ->execute();

    $detectedStart = NULL;
    $detectedEnd = NULL;
    if ($packageIds !== []) {
      foreach ($storage->loadMultiple($packageIds) as $package) {
        if (!$package instanceof NodeInterface) {
          continue;
        }
        $packageStart = trim((string) ($package->get('field_brebo_planned_start')->value ?? ''));
        $packageEnd = trim((string) ($package->get('field_brebo_planned_end')->value ?? ''));
        if ($packageStart !== '' && ($detectedStart === NULL || $packageStart < $detectedStart)) {
          $detectedStart = $packageStart;
        }
        if ($packageEnd !== '' && ($detectedEnd === NULL || $packageEnd > $detectedEnd)) {
          $detectedEnd = $packageEnd;
        }
      }
    }

    $start = $start ?: $detectedStart;
    $end = $end ?: $detectedEnd;

    $budgetHours = 0.0;
    if ($packageIds !== []) {
      $budgetIds = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'brebo_work_budget')
        ->condition('field_brebo_package_ref', array_values($packageIds), 'IN')
        ->execute();
      if ($budgetIds !== []) {
        $lineIds = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition('type', 'brebo_work_budget_line')
          ->condition('field_brebo_work_budget_ref', array_values($budgetIds), 'IN')
          ->execute();
        foreach ($storage->loadMultiple($lineIds) as $line) {
          if ($line instanceof NodeInterface) {
            $budgetHours += max(0.0, (float) ($line->get('field_brebo_budget_hours')->value ?? 0));
          }
        }
      }
    }

    $workdays = $this->workdays($start, $end);
    $hoursPerDay = $this->durationHours($startTime, $endTime);
    $people = count(array_unique(array_filter(array_map('intval', $userIds))));
    $proposed = $workdays * $people * $hoursPerDay;

    return [
      'start' => $start,
      'end' => $end,
      'budget_hours' => round($budgetHours, 2),
      'workdays' => $workdays,
      'people' => $people,
      'hours_per_person_day' => round($hoursPerDay, 2),
      'proposed_hours' => round($proposed, 2),
      'delta_hours' => round($proposed - $budgetHours, 2),
    ];
  }

  /** @return string[] */
  public function dates(string $start, string $end): array {
    if ($start === '' || $end === '' || $end < $start) {
      return [];
    }
    $dates = [];
    $cursor = new \DateTimeImmutable($start);
    $last = new \DateTimeImmutable($end);
    while ($cursor <= $last) {
      if ((int) $cursor->format('N') <= 5) {
        $dates[] = $cursor->format('Y-m-d');
      }
      $cursor = $cursor->modify('+1 day');
    }
    return $dates;
  }

  private function workdays(?string $start, ?string $end): int {
    if ($start === NULL || $end === NULL) {
      return 0;
    }
    return count($this->dates($start, $end));
  }

  private function durationHours(string $start, string $end): float {
    $from = strtotime('1970-01-01 ' . $start);
    $to = strtotime('1970-01-01 ' . $end);
    if ($from === false || $to === false || $to <= $from) {
      return 0.0;
    }
    return ($to - $from) / 3600;
  }

}
