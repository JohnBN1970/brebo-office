<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationDocumentContextRepositoryInterface;

/** Builds the versioned Office -> Calc document context read model. */
final class CalculationDocumentContextService {

  public function __construct(
    private readonly CalculationDocumentContextRepositoryInterface $repository,
  ) {}

  /** @return array<string,mixed> */
  public function snapshot(int $calculationId): array {
    if ($calculationId <= 0) {
      throw new \InvalidArgumentException('Calculation id is required.');
    }

    $set = $this->repository->latestSet($calculationId);
    if (!$set) {
      throw new \RuntimeException('No CalculationDocumentSet is available for this calculation.');
    }

    $setId = (int) $set['id'];
    $documents = $this->repository->documents($setId);
    $facts = $this->repository->facts($setId);
    $takeoff = $this->repository->takeoff($setId);

    return [
      'contract' => 'brebo-calculation-document-context-v1',
      'calculation_id' => $calculationId,
      'document_set' => [
        'id' => $setId,
        'project_id' => (int) $set['project_id'],
        'status' => (string) $set['status'],
        'selection_version' => (string) $set['selection_version'],
        'created' => (int) $set['created'],
        'changed' => (int) $set['changed'],
      ],
      'documents' => array_map(static fn(array $row): array => [
        'item_id' => (int) $row['id'],
        'document_id' => (int) $row['document_id'],
        'title' => (string) ($row['original_filename'] ?: $row['document_title'] ?: ('Document ' . $row['document_id'])),
        'document_type' => $row['document_type'],
        'document_family' => $row['document_family'],
        'mime_type' => $row['mime_type'],
        'role' => (string) $row['role'],
        'relevance' => (float) $row['relevance'],
        'selection_source' => (string) $row['selection_source'],
        'review_status' => (string) $row['review_status'],
        'exclusion_reason' => $row['exclusion_reason'],
      ], $documents),
      'facts' => array_map(static fn(array $row): array => [
        'id' => (int) $row['id'],
        'document_id' => (int) $row['document_id'],
        'fact_type' => (string) $row['fact_type'],
        'position_ref' => (string) $row['position_ref'],
        'value_text' => $row['value_text'],
        'value_number' => $row['value_number'] === NULL ? NULL : (float) $row['value_number'],
        'unit' => $row['unit'],
        'source_page' => $row['source_page'] === NULL ? NULL : (int) $row['source_page'],
        'source_fragment' => $row['source_fragment'],
        'extraction_method' => $row['extraction_method'],
        'confidence' => (float) $row['confidence'],
        'review_status' => (string) $row['review_status'],
      ], $facts),
      'takeoff' => array_map(static fn(array $row): array => [
        'id' => (int) $row['id'],
        'position_ref' => (string) $row['position_ref'],
        'quantity' => (float) $row['quantity'],
        'width_mm' => $row['width_mm'] === NULL ? NULL : (float) $row['width_mm'],
        'height_mm' => $row['height_mm'] === NULL ? NULL : (float) $row['height_mm'],
        'area_m2' => $row['area_m2'] === NULL ? NULL : (float) $row['area_m2'],
        'perimeter_m' => $row['perimeter_m'] === NULL ? NULL : (float) $row['perimeter_m'],
        'top_m' => $row['top_m'] === NULL ? NULL : (float) $row['top_m'],
        'bottom_m' => $row['bottom_m'] === NULL ? NULL : (float) $row['bottom_m'],
        'left_m' => $row['left_m'] === NULL ? NULL : (float) $row['left_m'],
        'right_m' => $row['right_m'] === NULL ? NULL : (float) $row['right_m'],
      ], $takeoff),
    ];
  }

}
