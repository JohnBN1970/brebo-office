<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Infrastructure;

use Drupal\brebo_control\Contract\ControlProjectAnalysisSourceInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/** Drupal adapter for legacy node-based project control analyzers. */
final class DrupalLegacyProjectControlAnalysisSource implements ControlProjectAnalysisSourceInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ?object $controllerActions,
    private readonly ?object $earlyWarning,
    private readonly ?object $projectFinancialControl,
  ) {}

  public function controllerActions(int $projectId): ?array {
    if ($this->controllerActions === NULL || !method_exists($this->controllerActions, 'analyze')) {
      return NULL;
    }

    $project = $this->loadProject($projectId);
    if ($project === NULL) {
      return NULL;
    }

    $analysis = $this->controllerActions->analyze($project);
    return is_array($analysis) ? $analysis : NULL;
  }

  public function historySnapshot(int $projectId): ?array {
    if (
      $this->earlyWarning === NULL
      || $this->projectFinancialControl === NULL
      || !method_exists($this->earlyWarning, 'analyze')
      || !method_exists($this->projectFinancialControl, 'analyze')
    ) {
      return NULL;
    }

    $project = $this->loadProject($projectId);
    if ($project === NULL) {
      return NULL;
    }

    $warning = $this->earlyWarning->analyze($project);
    $finance = $this->projectFinancialControl->analyze($project);
    if (!is_array($warning) || !is_array($finance)) {
      return NULL;
    }

    return ['warning' => $warning, 'finance' => $finance];
  }

  private function loadProject(int $projectId): ?object {
    $project = $this->entityTypeManager->getStorage('node')->load($projectId);
    return is_object($project) ? $project : NULL;
  }

}
