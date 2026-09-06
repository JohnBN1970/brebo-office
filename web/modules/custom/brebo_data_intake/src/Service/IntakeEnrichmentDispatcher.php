<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\brebo_data_intake\Contract\IntakeEnricherInterface;
use RuntimeException;

/** Runs source-neutral enrichers before domain destination dispatch. */
final class IntakeEnrichmentDispatcher {

  /** @param iterable<IntakeEnricherInterface> $enrichers */
  public function __construct(private readonly iterable $enrichers) {}

  /** @param array<string,mixed> $envelope */
  public function enrich(array $envelope): array {
    $source = (string) ($envelope['source'] ?? '');
    $sourceRecordId = (string) ($envelope['source_record_id'] ?? '');
    $attachments = $envelope['attachments'] ?? [];

    foreach ($this->enrichers as $enricher) {
      if (!$enricher->supports($envelope)) {
        continue;
      }
      $enriched = $enricher->enrich($envelope);
      if (($enriched['source'] ?? NULL) !== $source || ($enriched['source_record_id'] ?? NULL) !== $sourceRecordId) {
        throw new RuntimeException('BREBO intake enrichers may not change source identity.');
      }
      if (($enriched['attachments'] ?? []) !== $attachments) {
        throw new RuntimeException('BREBO intake enrichers may not replace original attachments.');
      }
      $envelope = $enriched;
    }

    return $envelope;
  }

}
