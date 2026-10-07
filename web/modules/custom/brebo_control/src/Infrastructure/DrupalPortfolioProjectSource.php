<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Infrastructure;

use Drupal\brebo_control\Contract\PortfolioProjectSourceInterface;
use Drupal\brebo_control\Service\ControlHistoryService;
use Drupal\brebo_office_core\Service\ProjectEarlyWarningService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Drupal entity adapter for portfolio project control facts. */
final class DrupalPortfolioProjectSource implements PortfolioProjectSourceInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ?ProjectEarlyWarningService $earlyWarning,
    private readonly ControlHistoryService $history,
  ) {}

  public function activeProjectFacts(): array {
    if ($this->earlyWarning === NULL) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'brebo_project')
      ->condition('status', 1)
      ->execute();

    $facts = [];
    foreach ($storage->loadMultiple($ids) as $project) {
      if (!$project instanceof NodeInterface) {
        continue;
      }

      $projectId = (int) $project->id();
      $facts[] = [
        'project_id' => $projectId,
        'project' => (string) $project->label(),
        'warning' => $this->earlyWarning->analyze($project),
        'trend' => $this->history->trend($projectId),
      ];
    }

    return $facts;
  }

}
