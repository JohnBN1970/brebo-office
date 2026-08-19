<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Access;

use Drupal\brebo_calculation\Service\CalculationReadinessInspector;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Connection;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\node\NodeInterface;

/** Blocks offer creation while the current calculation version is BLOCKED. */
final class OfferReadinessAccessCheck implements AccessInterface {

  public function __construct(
    private readonly Connection $database,
    private readonly CalculationReadinessInspector $readinessInspector,
  ) {}

  public function access(NodeInterface $node): AccessResult {
    if ($node->bundle() !== 'brebo_calculation' || $node->id() === NULL) {
      return AccessResult::forbidden('Offer readiness applies only to calculations.');
    }

    $version = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['version'])
      ->condition('calculation_id', (int) $node->id())
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if (!is_string($version) || $version === '') {
      return AccessResult::forbidden('A calculation version is required before an offer can be created.')
        ->addCacheableDependency($node);
    }

    $readiness = $this->readinessInspector->inspect((int) $node->id(), $version);
    if (($readiness['status'] ?? 'blocked') === 'blocked') {
      return AccessResult::forbidden('The calculation contains blocking readiness findings.')
        ->addCacheableDependency($node)
        ->setCacheMaxAge(0);
    }

    return AccessResult::allowed()
      ->addCacheableDependency($node)
      ->setCacheMaxAge(0);
  }

}
