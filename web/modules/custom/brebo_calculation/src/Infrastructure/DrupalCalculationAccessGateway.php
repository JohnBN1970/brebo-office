<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationAccessGatewayInterface;
use Drupal\brebo_calculation\Contract\CalculationAccessRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;

/**
 * Transitional actor-permission adapter with BREBO-native calculation lookup.
 */
final class DrupalCalculationAccessGateway implements CalculationAccessGatewayInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CalculationAccessRepositoryInterface $repository,
  ) {}

  public function assertCanEditWorkbench(int $calculationId, int $actorId): void {
    if ($calculationId <= 0 || $actorId <= 0) {
      throw new \RuntimeException('Calculation update access denied.');
    }

    $account = $this->entityTypeManager->getStorage('user')->load($actorId);
    $calculationExists = $this->repository->calculationExists($calculationId);

    if (!$account instanceof UserInterface
      || !$account->isAuthenticated()
      || $account->isBlocked()
      || !$account->hasPermission('edit brebo calculation workbench')
      || !$calculationExists) {
      throw new \RuntimeException('Calculation update access denied.');
    }
  }

}
