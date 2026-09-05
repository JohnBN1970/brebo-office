<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\brebo_data_intake\Contract\IntakeDestinationInterface;
use InvalidArgumentException;

/** Normalizes all inbound channels before handing off to domain workflows. */
final class SourceNeutralIntakeManager {

  /** @param iterable<IntakeDestinationInterface> $destinations */
  public function __construct(private readonly iterable $destinations) {}

  /**
   * @param array<string, mixed> $input
   *   Source payload plus classification and canonical references.
   *
   * @return array<string, mixed>
   */
  public function intake(array $input): array {
    $envelope = $this->normalize($input);

    foreach ($this->destinations as $destination) {
      if (!$destination->supports($envelope['classification'])) {
        continue;
      }
      $result = $destination->route($envelope);
      return [
        'state' => (string) ($result['state'] ?? 'routed'),
        'source' => $envelope['source'],
        'classification' => $envelope['classification'],
        'source_record_id' => $envelope['source_record_id'],
        'canonical' => $envelope['canonical'],
        'destination' => $result,
      ];
    }

    return [
      'state' => 'review_required',
      'reason' => 'no_destination_for_classification',
      'source' => $envelope['source'],
      'classification' => $envelope['classification'],
      'source_record_id' => $envelope['source_record_id'],
      'canonical' => $envelope['canonical'],
    ];
  }

  /** @return array<string, mixed> */
  private function normalize(array $input): array {
    $source = strtolower(trim((string) ($input['source'] ?? '')));
    $classification = strtolower(trim((string) ($input['classification'] ?? '')));
    if ($source === '' || $classification === '') {
      throw new InvalidArgumentException('Source and classification are required for BREBO intake.');
    }

    $allowedSources = ['email', 'upload', 'api', 'webhook', 'portal', 'website', 'bank', 'moneybird', 'whatsapp', 'mobile', 'manual', 'generated'];
    if (!in_array($source, $allowedSources, TRUE)) {
      throw new InvalidArgumentException(sprintf('Unsupported BREBO intake source: %s.', $source));
    }

    $sourceRecordId = trim((string) ($input['source_record_id'] ?? ''));
    if ($sourceRecordId === '') {
      $sourceRecordId = hash('sha256', json_encode([
        'source' => $source,
        'classification' => $classification,
        'payload' => $input['payload'] ?? [],
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    $canonical = is_array($input['canonical'] ?? NULL) ? $input['canonical'] : [];
    foreach (['relationship_id', 'project_nid', 'building_nid', 'supplier_ref', 'contact_id'] as $key) {
      if (isset($canonical[$key]) && $canonical[$key] === '') {
        unset($canonical[$key]);
      }
    }

    return [
      'source' => $source,
      'source_record_id' => $sourceRecordId,
      'classification' => $classification,
      'confidence' => isset($input['confidence']) ? max(0.0, min(1.0, (float) $input['confidence'])) : NULL,
      'canonical' => $canonical,
      'payload' => is_array($input['payload'] ?? NULL) ? $input['payload'] : [],
      'attachments' => is_array($input['attachments'] ?? NULL) ? array_values($input['attachments']) : [],
      'received_at' => isset($input['received_at']) ? (int) $input['received_at'] : time(),
      'actor_uid' => isset($input['actor_uid']) ? (int) $input['actor_uid'] : 0,
    ];
  }

}
