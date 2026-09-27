<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/**
 * Compatibility boundary for legacy Drupal calculation structure identities.
 */
interface CalculationStructureLegacyGatewayInterface {

  /** @return array{id:int,sequence:int} */
  public function createMainGroup(int $calculationId, string $code, string $label, int $actorId): array;

  /** @return array{id:int,sequence:int} */
  public function createParagraph(int $calculationId, int $componentId, string $code, string $label, int $actorId): array;

  public function reorder(string $nodeType, int $legacyId, int $sortOrder): void;

}
