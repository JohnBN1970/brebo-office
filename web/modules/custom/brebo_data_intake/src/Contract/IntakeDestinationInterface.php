<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Contract;

use Drupal\brebo_data_intake\ValueObject\IntakeDestinationResult;

/** Receives a classified, source-neutral BREBO intake envelope. */
interface IntakeDestinationInterface {

  /** Returns TRUE when this destination owns the classified business object. */
  public function supports(string $classification): bool;

  /**
   * Routes a validated intake envelope into the canonical business workflow.
   *
   * @param array<string, mixed> $envelope
   *   Normalized intake envelope.
   */
  public function route(array $envelope): IntakeDestinationResult;

}
