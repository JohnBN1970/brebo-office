<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationAccessGatewayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Transitional Drupal implementation of calculation update access.
 */
final class DrupalCalculationAccessGateway implements CalculationAccessGatewayInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function assertCanEditWorkbench(int $calculationId, int $actorId): void {
    $account = $this->entityTypeManager->getStorage('user')->load($actorId);
    $calculation = $this->entityTypeManager->getStorage('node')->load($calculationId);

    if (!$account instanceof AccountInterface
      || !$account->hasPermission('edit brebo calculation workbench')
      || !$calculation instanceof NodeInterface
      || $calculation->bundle() !== 'brebo_calculation'
      || !$calculation->access('update', $account)) {
      throw new \RuntimeException('Calculation update access denied.');
    }
  }

}
