<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationDocumentContextRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Database adapter for document-derived calculation context. */
final class DatabaseCalculationDocumentContextRepository implements CalculationDocumentContextRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function latestSet(int $calculationId): ?array {
    $row = $this->database->select('brebo_calculation_document_set', 's')
      ->fields('s')
      ->condition('calculation_id', $calculationId)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function documents(int $setId): array {
    $query = $this->database->select('brebo_calculation_document_set_item', 'i');
    $query->leftJoin('brebo_document', 'd', 'd.id = i.document_id');
    $query->fields('i');
    $query->addField('d', 'title', 'document_title');
    $query->addField('d', 'original_filename', 'original_filename');
    $query->addField('d', 'document_type', 'document_type');
    $query->addField('d', 'document_family', 'document_family');
    $query->addField('d', 'mime_type', 'mime_type');
    $query->condition('i.set_id', $setId);
    $query->orderBy('i.id', 'ASC');
    return array_values($query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: []);
  }

  public function facts(int $setId): array {
    return array_values($this->database->select('brebo_calculation_fact', 'f')
      ->fields('f')
      ->condition('set_id', $setId)
      ->orderBy('position_ref', 'ASC')
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: []);
  }

  public function takeoff(int $setId): array {
    return array_values($this->database->select('brebo_calculation_takeoff', 't')
      ->fields('t')
      ->condition('set_id', $setId)
      ->orderBy('position_ref', 'ASC')
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: []);
  }

}
