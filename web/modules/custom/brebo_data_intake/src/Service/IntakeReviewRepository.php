<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\Core\Database\Connection;

/** Read model for the source-neutral intake review workbench. */
final class IntakeReviewRepository {

  public function __construct(private readonly Connection $database) {}

  /**
   * Returns pending records with their source provenance.
   *
   * @return array<int,array<string,mixed>>
   */
  public function pending(int $limit = 50): array {
    $query = $this->database->select('brebo_data_record', 'record');
    $query->innerJoin('brebo_data_ingest_run', 'run', 'run.id = record.run_id');
    $query->innerJoin('brebo_data_source', 'source', 'source.id = run.source_id');
    $query->fields('record', ['id', 'record_type', 'external_key', 'source_reference', 'payload', 'confidence', 'created']);
    $query->addField('source', 'label', 'source_label');
    $query->addField('source', 'source_type', 'source_type');
    $query->addField('source', 'provider_key', 'provider_key');
    $query->condition('record.status', 'review_required');
    $query->orderBy('record.created', 'ASC');
    $query->range(0, max(1, min(200, $limit)));

    return array_map(static function (object $row): array {
      $values = (array) $row;
      try {
        $values['payload'] = json_decode((string) $values['payload'], TRUE, 512, JSON_THROW_ON_ERROR);
      }
      catch (\JsonException) {
        $values['payload'] = [];
      }
      return $values;
    }, $query->execute()->fetchAll());
  }

}
