<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\ProjectReferenceGatewayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Transitional Drupal adapter for BREBO project identity.
 *
 * Finance domain services depend on ProjectReferenceGatewayInterface only.
 * Replace this adapter when canonical project storage no longer uses Drupal nodes.
 */
final class DrupalProjectReferenceGateway implements ProjectReferenceGatewayInterface {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public function exists(int $projectId): bool {
    if ($projectId <= 0) {
      return FALSE;
    }

    $project = $this->entityTypeManager->getStorage('node')->load($projectId);
    return $project !== NULL && $project->bundle() === 'brebo_project';
  }

}
