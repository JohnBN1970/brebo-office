<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationDocumentSetStoreInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationDocumentSetStore implements CalculationDocumentSetStoreInterface {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  public function currentTime(): int {
    return $this->time->getRequestTime();
  }

  public function createSet(array $values): int {
    return (int) $this->database
      ->insert('brebo_calculation_document_set')
      ->fields($values)
      ->execute();
  }

  public function projectDocuments(int $projectId): array {
    $query = $this->database->select('brebo_document_context', 'c');
    $query->innerJoin('brebo_document', 'd', 'd.id = c.document_id');
    $query->fields('d', ['id', 'title', 'document_type', 'document_family', 'original_filename', 'mime_type']);
    $query
      ->condition('c.context_type', 'project')
      ->condition('c.context_id', $projectId)
      ->condition('d.lifecycle_status', 'deleted', '<>')
      ->distinct()
      ->orderBy('d.id', 'DESC');

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  public function createItem(array $values): int {
    return (int) $this->database
      ->insert('brebo_calculation_document_set_item')
      ->fields($values)
      ->execute();
  }

}
