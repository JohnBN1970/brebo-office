<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Runs BREBO Control autonomously across active projects.
 */
final class ControlAutomationRunner {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ControlActionManager $actionManager,
    private readonly ControlNotificationEngine $notificationEngine,
  ) {}

  /** @return array<string, int> */
  public function run(int $now): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $projectIds = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'brebo_project')
      ->condition('status', 1)
      ->execute();

    $projects = 0;
    $actions = 0;
    foreach ($storage->loadMultiple($projectIds) as $project) {
      if (!$project instanceof NodeInterface) {
        continue;
      }
      $projects++;
      $actions += count($this->actionManager->synchronize($project));
    }

    $escalated = count($this->actionManager->escalateOverdue());
    $notifications = count($this->notificationEngine->scan($now));

    return [
      'projects_scanned' => $projects,
      'actions_seen' => $actions,
      'actions_escalated' => $escalated,
      'notifications_queued' => $notifications,
    ];
  }

}
