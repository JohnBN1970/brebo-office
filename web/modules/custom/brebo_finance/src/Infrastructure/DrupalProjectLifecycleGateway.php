<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\ProjectLifecycleGatewayInterface;
use Drupal\brebo_office_core\Project\ProjectLifecycle;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use RuntimeException;
use UnexpectedValueException;

/**
 * Transitional adapter for canonical project lifecycle storage.
 */
final class DrupalProjectLifecycleGateway implements ProjectLifecycleGatewayInterface {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public function transition(int $projectId, string $targetStatus): array {
    $project = $this->entityTypeManager->getStorage('node')->load($projectId);
    if ($project === NULL || $project->bundle() !== 'brebo_project') {
      throw new UnexpectedValueException('BREBO project does not exist.');
    }

    $statusField = $this->resolveStatusField($project);
    $before = (string) $project->get($statusField)->value;
    if (ProjectLifecycle::normalize($before) === $targetStatus) {
      return ['before' => $before, 'changed' => FALSE];
    }

    $project->set($statusField, $targetStatus);
    $project->save();
    return ['before' => $before, 'changed' => TRUE];
  }

  private function resolveStatusField(object $project): string {
    foreach (['field_brebo_project_status', 'field_brebo_status'] as $fieldName) {
      if ($project->hasField($fieldName)) {
        return $fieldName;
      }
    }
    throw new RuntimeException('BREBO project has no canonical project status field; protected transition cannot be performed.');
  }

}
