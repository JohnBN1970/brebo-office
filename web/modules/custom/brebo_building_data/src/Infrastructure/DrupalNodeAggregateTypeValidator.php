<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Infrastructure;

use Drupal\brebo_building_data\Contract\AggregateTypeValidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use InvalidArgumentException;

/**
 * Transitional Drupal adapter for BREBO aggregate identity validation.
 *
 * Repositories depend only on AggregateTypeValidatorInterface. This adapter
 * preserves the current node/bundle validation while Drupal is still the
 * identity provider for top-level buildings and projects.
 */
final class DrupalNodeAggregateTypeValidator implements AggregateTypeValidatorInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function assertType(int $id, string $type): void {
    $node = $this->entityTypeManager->getStorage('node')->load($id);
    if (!$node instanceof NodeInterface || $node->bundle() !== $type) {
      throw new InvalidArgumentException(sprintf('Object %d is not %s.', $id, $type));
    }
  }

}
