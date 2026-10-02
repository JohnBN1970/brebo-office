<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Contract;

/**
 * Validates BREBO aggregate identities without coupling repositories to Drupal.
 */
interface AggregateTypeValidatorInterface {

  public function assertType(int $id, string $type): void;

}
