<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Service;

use Drupal\brebo_project_cockpit\Contract\ProjectMilestoneReadRepositoryInterface;

/** Builds the current project phase and next milestone from route items. */
final class ProjectMilestoneBuilder {

  public function __construct(
    private readonly ProjectMilestoneReadRepositoryInterface $milestoneRepository,
  ) {}

  /** @return array<string, mixed> */
  public function build(int $projectId, ?\DateTimeImmutable $now = NULL): array {
    $now ??= new \DateTimeImmutable('now');
    $items = $this->milestoneRepository->routeItems($projectId);
    if ($items === []) {
      return $this->empty();
    }

    $currentPhase = NULL;
    $next = NULL;
    $overdue = 0;
    $blocked = 0;
    $open = 0;
    $today = $now->format('Y-m-d');

    foreach ($items as $item) {
      $status = $item['status'];
      $done = in_array(mb_strtolower($status), ['gereed', 'n.v.t.', 'nvt'], TRUE);
      if ($done) {
        continue;
      }

      $open++;
      $phase = $item['phase'];
      if ($currentPhase === NULL && $phase !== '') {
        $currentPhase = $phase;
      }
      if (mb_strtolower($status) === 'geblokkeerd') {
        $blocked++;
      }

      $due = $item['due'];
      if ($due !== NULL && $due < $today) {
        $overdue++;
      }

      if ($next === NULL) {
        $next = [
          'id' => $item['id'],
          'label' => $item['label'],
          'kind' => $item['kind'],
          'phase' => $phase,
          'due' => $due,
          'status' => $status,
          'owner' => $item['owner'],
          'evidence' => $item['evidence'],
        ];
      }
    }

    $status = $blocked > 0 || $overdue > 0 ? 'rood' : ($open > 0 ? 'groen' : 'grijs');
    return [
      'current_phase' => $currentPhase,
      'next_milestone' => $next,
      'open_count' => $open,
      'overdue_count' => $overdue,
      'blocked_count' => $blocked,
      'status' => $status,
    ];
  }

  /** @return array<string, mixed> */
  private function empty(): array {
    return [
      'current_phase' => NULL,
      'next_milestone' => NULL,
      'open_count' => 0,
      'overdue_count' => 0,
      'blocked_count' => 0,
      'status' => 'grijs',
    ];
  }
}
