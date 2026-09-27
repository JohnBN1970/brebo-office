<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationAccessGatewayInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Transitional actor-permission adapter with BREBO-native calculation lookup.
 */
final class DrupalCalculationAccessGateway implements CalculationAccessGatewayInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
  ) {}

  public function assertCanEditWorkbench(int $calculationId, int $actorId): void {
    $account = $this->entityTypeManager->getStorage('user')->load($actorId);
    $calculationExists = (bool) $this->database->select('brebo_calculation_version', 'v')
      ->condition('calculation_id', $calculationId)
      ->countQuery()
      ->execute()
      ->fetchField();

    if (!$account instanceof AccountInterface
      || !$account->hasPermission('edit brebo calculation workbench')
      || !$calculationExists) {
      throw new \RuntimeException('Calculation update access denied.');
    }
  }

}
