<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationDocumentSetStoreInterface;

/** Builds reviewable calculation document sets from Office project documents. */
final class CalculationDocumentSetService {

  public function __construct(
    private readonly CalculationDocumentSetStoreInterface $store,
  ) {}

  /** @return array<string, mixed> */
  public function propose(int $projectId, int $calculationId): array {
    if ($projectId <= 0 || $calculationId <= 0) {
      throw new \InvalidArgumentException('Project en calculatie zijn verplicht.');
    }

    $now = $this->store->currentTime();
    $setId = (int) $this->store->createSet([
      'project_id' => $projectId,
      'calculation_id' => $calculationId,
      'status' => 'proposed',
      'selection_version' => 'v1',
      'created' => $now,
      'changed' => $now,
    ]);

    $documents = $this->store->projectDocuments($projectId);

    $items = [];
    foreach ($documents as $document) {
      [$role, $relevance] = $this->classify($document);
      $reviewStatus = $relevance >= 0.45 ? 'proposed' : 'excluded';
      $itemId = (int) $this->store->createItem([
        'set_id' => $setId,
        'document_id' => (int) $document['id'],
        'role' => $role,
        'relevance' => $relevance,
        'selection_source' => 'system',
        'review_status' => $reviewStatus,
        'exclusion_reason' => $reviewStatus === 'excluded' ? 'Geen duidelijke calculatierelevantie herkend.' : NULL,
        'created' => $now,
        'changed' => $now,
      ]);
      $items[] = [
        'item_id' => $itemId,
        'document_id' => (int) $document['id'],
        'title' => (string) ($document['original_filename'] ?: $document['title']),
        'document_type' => $document['document_type'],
        'document_family' => $document['document_family'],
        'mime_type' => $document['mime_type'],
        'role' => $role,
        'relevance' => $relevance,
        'review_status' => $reviewStatus,
      ];
    }

    return ['id' => $setId, 'project_id' => $projectId, 'calculation_id' => $calculationId, 'status' => 'proposed', 'selection_version' => 'v1', 'documents' => $items];
  }

  /** @param array<string, mixed> $document @return array{0:string,1:float} */
  private function classify(array $document): array {
    $haystack = strtolower(implode(' ', array_filter([
      (string) ($document['title'] ?? ''),
      (string) ($document['original_filename'] ?? ''),
      (string) ($document['document_type'] ?? ''),
      (string) ($document['document_family'] ?? ''),
    ])));
    $rules = [
      'supplier_quote' => ['offerte', 'quote', 'quotation', 'aanbieding', 'prijs'],
      'window_schedule' => ['kozijnstaat', 'kozijnenstaat', 'window schedule', 'door schedule', 'elementenstaat'],
      'drawing' => ['tekening', 'drawing', 'gevel', 'plattegrond', 'detail'],
      'specification' => ['bestek', 'specification', 'werkomschrijving'],
      'measurement' => ['meetstaat', 'measurement', 'inmeet', 'opname'],
      'technical_advice' => ['advies', 'vta', 'inspectie', 'rapport'],
      'planning' => ['planning', 'kyp'],
      'price_list' => ['prijslijst', 'price list', 'catalog'],
    ];
    foreach ($rules as $role => $needles) {
      foreach ($needles as $needle) {
        if (str_contains($haystack, $needle)) {
          return [$role, 0.80];
        }
      }
    }
    return ['supporting', 0.20];
  }

}
