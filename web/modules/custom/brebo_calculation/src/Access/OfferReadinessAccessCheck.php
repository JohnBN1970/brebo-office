<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Access;

use Drupal\brebo_calculation\Service\CalculationReadinessInspector;
use Drupal\Core\Access\AccessResult;
use Drupal\brebo_calculation\Contract\CalculationAccessRepositoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\node\NodeInterface;

/** Blocks offer creation while the current calculation version is BLOCKED. */
final class OfferReadinessAccessCheck implements AccessInterface {

  public function __construct(
    private readonly CalculationAccessRepositoryInterface $repository,
    private readonly CalculationReadinessInspector $readinessInspector,
  ) {}

  public function access(NodeInterface $node): AccessResult {
    if ($node->bundle() !== 'brebo_calculation' || $node->id() === NULL) {
      return AccessResult::forbidden('Offer readiness applies only to calculations.');
    }

    $version = $this->repository->latestEstablishedVersion((int) $node->id());

    if ($version === NULL) {
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
