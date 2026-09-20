<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Project;

use Drupal\node\NodeInterface;

/** Canonical project-status adapter for UI, filters and migrations. */
final class ProjectLifecycleStatus {

  public static function fieldName(NodeInterface $project): ?string {
    foreach (['field_brebo_status', 'field_brebo_project_status'] as $fieldName) {
      if ($project->hasField($fieldName)) {
        return $fieldName;
      }
    }
    return NULL;
  }

  public static function value(NodeInterface $project): string {
    $fieldName = self::fieldName($project);
    if ($fieldName === NULL || $project->get($fieldName)->isEmpty()) {
      return ProjectLifecycle::CONCEPT;
    }
    return ProjectLifecycle::normalize((string) $project->get($fieldName)->value);
  }

  public static function label(NodeInterface $project): string {
    return ProjectLifecycle::label(self::value($project));
  }

  /** @return array<string, string> */
  public static function options(): array {
    return ProjectLifecycle::options();
  }

  public static function isActive(NodeInterface $project): bool {
    return ProjectLifecycle::isActive(self::value($project));
  }

  public static function isExecution(NodeInterface $project): bool {
    return ProjectLifecycle::isExecution(self::value($project));
  }

}
