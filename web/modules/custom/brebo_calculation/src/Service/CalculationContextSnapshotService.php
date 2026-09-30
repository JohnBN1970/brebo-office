<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;

/** Builds the versioned Office-owned source context consumed by external Calc. */
final class CalculationContextSnapshotService {

  public function __construct(
    private readonly Connection $database,
  ) {}

  /** @return array<string,mixed> */
  public function latest(int $calculationId): array {
    if ($calculationId <= 0) {
      throw new \InvalidArgumentException('Calculatie is verplicht.');
    }

    $set = $this->database->select('brebo_calculation_document_set', 's')
      ->fields('s', ['id', 'project_id', 'calculation_id', 'status', 'selection_version', 'created', 'changed'])
      ->condition('s.calculation_id', $calculationId)
      ->orderBy('s.id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$set) {
      return [
        'calculation_id' => $calculationId,
        'project_id' => NULL,
        'document_set' => NULL,
        'documents' => [],
        'facts' => [],
        'takeoff' => [],
        'review' => [
          'has_context' => FALSE,
          'proposed_documents' => 0,
          'proposed_facts' => 0,
          'unresolved' => ['Er is nog geen CalculationDocumentSet voor deze calculatie.'],
        ],
      ];
    }

    $setId = (int) $set['id'];

    $documents = [];
    $query = $this->database->select('brebo_calculation_document_set_item', 'i');
    $query->leftJoin('brebo_document', 'd', 'd.id = i.document_id');
    $query->fields('i', ['id', 'document_id', 'role', 'relevance', 'selection_source', 'review_status', 'exclusion_reason', 'created', 'changed']);
    $query->addField('d', 'title', 'document_title');
    $query->addField('d', 'original_filename');
    $query->addField('d', 'document_type');
    $query->addField('d', 'document_family');
    $query->addField('d', 'mime_type');
    $query->condition('i.set_id', $setId);
    $query->orderBy('i.id', 'ASC');
    foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
      $documents[] = [
        'item_id' => (int) $row['id'],
        'document_id' => (int) $row['document_id'],
        'title' => (string) ($row['original_filename'] ?: $row['document_title'] ?: ('Document ' . $row['document_id'])),
        'document_type' => $row['document_type'],
        'document_family' => $row['document_family'],
        'mime_type' => $row['mime_type'],
        'role' => $row['role'],
        'relevance' => (float) $row['relevance'],
        'selection_source' => (string) $row['selection_source'],
        'review_status' => (string) $row['review_status'],
        'exclusion_reason' => $row['exclusion_reason'],
      ];
    }

    $facts = [];
    $query = $this->database->select('brebo_calculation_fact', 'f');
    $query->fields('f', ['id', 'document_id', 'fact_type', 'position_ref', 'value_text', 'value_number', 'unit', 'source_page', 'source_fragment', 'extraction_method', 'confidence', 'review_status', 'created']);
    $query->condition('f.set_id', $setId);
    $query->orderBy('f.position_ref', 'ASC');
    $query->orderBy('f.id', 'ASC');
    foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
      $facts[] = [
        'id' => (int) $row['id'],
        'document_id' => (int) $row['document_id'],
        'fact_type' => (string) $row['fact_type'],
        'position_ref' => $row['position_ref'] !== NULL ? (string) $row['position_ref'] : NULL,
        'value_text' => $row['value_text'],
        'value_number' => $row['value_number'] !== NULL ? (float) $row['value_number'] : NULL,
        'unit' => $row['unit'],
        'source_page' => $row['source_page'] !== NULL ? (int) $row['source_page'] : NULL,
        'source_fragment' => $row['source_fragment'],
        'extraction_method' => $row['extraction_method'],
        'confidence' => (float) $row['confidence'],
        'review_status' => (string) $row['review_status'],
      ];
    }

    $takeoff = [];
    $query = $this->database->select('brebo_calculation_takeoff', 't');
    $query->fields('t', ['id', 'position_ref', 'quantity', 'width_mm', 'height_mm', 'area_m2', 'perimeter_m', 'top_m', 'bottom_m', 'left_m', 'right_m', 'created']);
    $query->condition('t.set_id', $setId);
    $query->orderBy('t.position_ref', 'ASC');
    foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
      $takeoff[] = [
        'id' => (int) $row['id'],
        'position_ref' => (string) $row['position_ref'],
        'quantity' => (float) $row['quantity'],
        'width_mm' => $row['width_mm'] !== NULL ? (float) $row['width_mm'] : NULL,
        'height_mm' => $row['height_mm'] !== NULL ? (float) $row['height_mm'] : NULL,
        'area_m2' => $row['area_m2'] !== NULL ? (float) $row['area_m2'] : NULL,
        'perimeter_m' => $row['perimeter_m'] !== NULL ? (float) $row['perimeter_m'] : NULL,
        'top_m' => $row['top_m'] !== NULL ? (float) $row['top_m'] : NULL,
        'bottom_m' => $row['bottom_m'] !== NULL ? (float) $row['bottom_m'] : NULL,
        'left_m' => $row['left_m'] !== NULL ? (float) $row['left_m'] : NULL,
        'right_m' => $row['right_m'] !== NULL ? (float) $row['right_m'] : NULL,
      ];
    }

    $proposedDocuments = count(array_filter($documents, static fn(array $d): bool => $d['review_status'] === 'proposed'));
    $proposedFacts = count(array_filter($facts, static fn(array $f): bool => $f['review_status'] === 'proposed'));
    $unresolved = [];
    if ($proposedDocuments > 0) {
      $unresolved[] = $proposedDocuments . ' document(en) wachten nog op review.';
    }
    if ($proposedFacts > 0) {
      $unresolved[] = $proposedFacts . ' feit(en) wachten nog op review.';
    }
    if (!$takeoff) {
      $unresolved[] = 'Er is nog geen geometrische uittrekstaat beschikbaar.';
    }

    return [
      'calculation_id' => (int) $set['calculation_id'],
      'project_id' => (int) $set['project_id'],
      'document_set' => [
        'id' => $setId,
        'status' => (string) $set['status'],
        'selection_version' => (string) $set['selection_version'],
        'created' => (int) $set['created'],
        'changed' => (int) $set['changed'],
      ],
      'documents' => $documents,
      'facts' => $facts,
      'takeoff' => $takeoff,
      'review' => [
        'has_context' => TRUE,
        'proposed_documents' => $proposedDocuments,
        'proposed_facts' => $proposedFacts,
        'unresolved' => $unresolved,
      ],
    ];
  }

}
