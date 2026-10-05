<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Infrastructure;

use Drupal\brebo_control\Contract\ControlProjectSourceInterface;
use Drupal\brebo_office_core\Service\ProjectControllerActionService;
use Drupal\brebo_office_core\Service\ProjectEarlyWarningService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Drupal project/entity adapter for Control source analysis. */
final class DrupalControlProjectSource implements ControlProjectSourceInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ?ProjectControllerActionService $controllerActions,
    private readonly ?ProjectEarlyWarningService $earlyWarning,
    private readonly ?object $projectFinancialControl,
  ) {}

  public function activeProjects(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_project')
      ->condition('status', 1)
      ->execute();

    $projects = [];
    foreach ($storage->loadMultiple($ids) as $project) {
      if (!$project instanceof NodeInterface) {
        continue;
      }

      $projects[] = [
        'project_id' => (int) $project->id(),
        'project_label' => (string) $project->label(),
        'controller_actions' => $this->controllerActions?->analyze($project),
        'early_warning' => $this->earlyWarning?->analyze($project),
        'financial_control' => $this->projectFinancialControl?->analyze($project),
      ];
    }

    return $projects;
  }

}
