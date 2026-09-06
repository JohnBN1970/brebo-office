<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Contract;

/** Enriches a normalized source-neutral intake envelope before dispatch. */
interface IntakeEnricherInterface {

  /** Returns TRUE when this enricher can add information to the envelope. */
  public function supports(array $envelope): bool;

  /**
   * Returns the enriched envelope without changing source identity.
   *
   * Enrichers may add normalized payload, canonical references and provenance,
   * but must not mutate source, source_record_id or the original attachments.
   *
   * @param array<string,mixed> $envelope
   *
   * @return array<string,mixed>
   */
  public function enrich(array $envelope): array;

}
