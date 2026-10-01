<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\ProjectReferenceGatewayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Transitional Drupal adapter for BREBO project identity.
 *
 * Finance domain services depend on ProjectReferenceGatewayInterface only.
 * Replace this adapter when canonical project storage no longer uses Drupal nodes.
 */
final class DrupalProjectReferenceGateway implements ProjectReferenceGatewayInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public function viewableIds(int $actorUid): array {
    if ($actorUid <= 0) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $account = $this->entityTypeManager->getStorage('user')->load($actorUid);
    if ($account === NULL) {
      return [];
    }

    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_project')
      ->execute();
    $result = [];
    foreach ($storage->loadMultiple($ids) as $project) {
      if ($project->bundle() === 'brebo_project' && $project->access('view', $account)) {
        $result[] = (int) $project->id();
      }
    }
    return $result;
  }

  public function canViewAs(int $projectId, int $actorUid): bool {
    if ($projectId <= 0 || $actorUid <= 0) {
      return FALSE;
    }

    $project = $this->entityTypeManager->getStorage('node')->load($projectId);
    $account = $this->entityTypeManager->getStorage('user')->load($actorUid);
    return $project !== NULL
      && $project->bundle() === 'brebo_project'
      && $account !== NULL
      && $project->access('view', $account);
  }

  public function canView(int $projectId): bool {
    if ($projectId <= 0) {
      return FALSE;
    }

    $project = $this->entityTypeManager->getStorage('node')->load($projectId);
    return $project !== NULL
      && $project->bundle() === 'brebo_project'
      && $project->access('view', $this->currentUser);
  }

  public function label(int $projectId): ?string {
    if ($projectId <= 0) {
      return NULL;
    }

    $project = $this->entityTypeManager->getStorage('node')->load($projectId);
    return $project !== NULL && $project->bundle() === 'brebo_project'
      ? (string) $project->label()
      : NULL;
  }

  public function exists(int $projectId): bool {
    if ($projectId <= 0) {
      return FALSE;
    }

    $project = $this->entityTypeManager->getStorage('node')->load($projectId);
    return $project !== NULL && $project->bundle() === 'brebo_project';
  }

}
