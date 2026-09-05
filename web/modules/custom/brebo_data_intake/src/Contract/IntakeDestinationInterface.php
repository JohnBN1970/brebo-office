<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Contract;

/** Receives a classified, source-neutral BREBO intake envelope. */
interface IntakeDestinationInterface {

  /** Returns TRUE when this destination owns the classified business object. */
  public function supports(string $classification): bool;

  /**
   * Routes a validated intake envelope into the canonical business workflow.
   *
   * @param array<string, mixed> $envelope
   *   Normalized intake envelope.
   *
   * @return array<string, mixed>
   *   Destination result, including at least a state key.
   */
  public function route(array $envelope): array;

}
