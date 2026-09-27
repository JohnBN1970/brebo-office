<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationLegacyGatewayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Transitional Drupal adapter for legacy calculation aggregate writes. */
final class DrupalCalculationLegacyGateway implements CalculationLegacyGatewayInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function markEstablished(int $calculationId): void {
    $calculation = $this->entityTypeManager->getStorage('node')->load($calculationId);
    if (!$calculation instanceof NodeInterface || $calculation->bundle() !== 'brebo_calculation') {
      throw new \RuntimeException('Calculatie-node niet gevonden tijdens vaststellen.');
    }
    if ($calculation->hasField('field_brebo_calc_status')) {
      $calculation->set('field_brebo_calc_status', 'Vastgesteld');
      $calculation->save();
    }
  }

}
