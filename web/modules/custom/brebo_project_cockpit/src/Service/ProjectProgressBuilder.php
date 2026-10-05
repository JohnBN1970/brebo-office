<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Service;

use Drupal\brebo_project_cockpit\Contract\ProjectProgressReadRepositoryInterface;

/** Builds deterministic project progress and elapsed-time indicators. */
final class ProjectProgressBuilder {

  public function __construct(
    private readonly ProjectProgressReadRepositoryInterface $progressRepository,
  ) {}

  /** @return array<string, mixed> */
  public function build(int $projectId, ?\DateTimeImmutable $now = NULL): array {
    $now ??= new \DateTimeImmutable('now');
    $activities = $this->progressRepository->activities($projectId);
    if ($activities === []) {
      return $this->empty();
    }

    $weightedProgress = 0.0;
    $weightTotal = 0.0;
    $simpleProgress = 0.0;
    $count = 0;
    $plannedStart = NULL;
    $plannedEnd = NULL;
    $late = 0;
    $blocked = 0;
    $criticalOpen = 0;

    foreach ($activities as $activity) {
      $progress = $activity['progress'] ?? 0.0;
      $progress = max(0.0, min(100.0, $progress));
      $duration = $activity['duration'];
      $weight = $duration !== NULL && $duration > 0 ? $duration : 1.0;
      $weightedProgress += $progress * $weight;
      $weightTotal += $weight;
      $simpleProgress += $progress;
      $count++;

      $start = $this->dateValue($activity['start']);
      $end = $this->dateValue($activity['baseline_end']) ?? $this->dateValue($activity['end']);
      if ($start !== NULL && ($plannedStart === NULL || $start < $plannedStart)) {
        $plannedStart = $start;
      }
      if ($end !== NULL && ($plannedEnd === NULL || $end > $plannedEnd)) {
        $plannedEnd = $end;
      }

      $status = mb_strtolower(trim($activity['status']));
      $done = $progress >= 100 || $status === 'gereed';
      if (!$done && $end !== NULL && $end < $now->setTime(0, 0)) {
        $late++;
      }
      if ($status === 'geblokkeerd') {
        $blocked++;
      }
      $critical = $activity['critical'];
      if ($critical && !$done) {
        $criticalOpen++;
      }
    }

    $actual = $weightTotal > 0 ? $weightedProgress / $weightTotal : ($count > 0 ? $simpleProgress / $count : NULL);
    $time = $this->elapsedPercent($plannedStart, $plannedEnd, $now);
    $variance = $actual !== NULL && $time !== NULL ? $actual - $time : NULL;
    $status = 'grijs';
    if ($actual !== NULL || $time !== NULL) {
      $status = ($blocked > 0 || $late > 0 || ($variance !== NULL && $variance < -10.0)) ? 'rood'
        : (($criticalOpen > 0 || ($variance !== NULL && $variance < -3.0)) ? 'oranje' : 'groen');
    }

    return [
      'activity_count' => $count,
      'actual_progress_pct' => $actual === NULL ? NULL : round($actual, 1),
      'time_elapsed_pct' => $time === NULL ? NULL : round($time, 1),
      'progress_vs_time_pct' => $variance === NULL ? NULL : round($variance, 1),
      'planned_start' => $plannedStart?->format('Y-m-d'),
      'planned_end' => $plannedEnd?->format('Y-m-d'),
      'late_count' => $late,
      'blocked_count' => $blocked,
      'critical_open_count' => $criticalOpen,
      'status' => $status,
      'basis' => 'Voortgang is duurgewogen op uitvoeringsactiviteiten; tijdsverloop loopt van vroegste geplande start tot laatste baseline-einddatum.',
    ];
  }

  private function elapsedPercent(?\DateTimeImmutable $start, ?\DateTimeImmutable $end, \DateTimeImmutable $now): ?float {
    if ($start === NULL || $end === NULL || $end <= $start) {
      return NULL;
    }
    if ($now <= $start) {
      return 0.0;
    }
    if ($now >= $end) {
      return 100.0;
    }
    $total = $end->getTimestamp() - $start->getTimestamp();
    $elapsed = $now->getTimestamp() - $start->getTimestamp();
    return $total > 0 ? ($elapsed / $total) * 100.0 : NULL;
  }

  private function dateValue(?string $value): ?\DateTimeImmutable {
    if ($value === NULL || $value === '') {
      return NULL;
    }
    try {
      return new \DateTimeImmutable($value);
    }
    catch (\Exception) {
      return NULL;
    }
  }

  /** @return array<string, mixed> */
  private function empty(): array {
    return [
      'activity_count' => 0,
      'actual_progress_pct' => NULL,
      'time_elapsed_pct' => NULL,
      'progress_vs_time_pct' => NULL,
      'planned_start' => NULL,
      'planned_end' => NULL,
      'late_count' => 0,
      'blocked_count' => 0,
      'critical_open_count' => 0,
      'status' => 'grijs',
      'basis' => 'Nog geen bruikbare uitvoeringsplanning beschikbaar.',
    ];
  }
}
