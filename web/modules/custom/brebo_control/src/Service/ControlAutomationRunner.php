<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Service;

use Drupal\brebo_control\Contract\ControlProjectSourceInterface;

/**
 * Runs BREBO Control autonomously across active projects.
 */
final class ControlAutomationRunner {

  public function __construct(
    private readonly ControlProjectSourceInterface $projectSource,
    private readonly ControlActionManager $actionManager,
    private readonly ControlNotificationEngine $notificationEngine,
    private readonly ControlHistoryService $history,
    private readonly ControlTrendActionService $trendActions,
  ) {}

  /** @return array<string, int> */
  public function run(int $now): array {
    $projects = 0;
    $actions = 0;
    $snapshots = 0;
    $trendActions = 0;
    foreach ($this->projectSource->activeProjectIds() as $projectId) {
      $projects++;
      $actions += count($this->actionManager->synchronize($projectId));
      if ($this->history->capture($projectId, $now)) {
        $snapshots++;
      }
      if ($this->trendActions->synchronize($projectId, $now) !== NULL) {
        $trendActions++;
      }
    }

    $escalated = count($this->actionManager->escalateOverdue());
    $notifications = count($this->notificationEngine->scan($now));

    return [
      'projects_scanned' => $projects,
      'actions_seen' => $actions,
      'snapshots_captured' => $snapshots,
      'trend_actions_active' => $trendActions,
      'actions_escalated' => $escalated,
      'notifications_queued' => $notifications,
    ];
  }

}
