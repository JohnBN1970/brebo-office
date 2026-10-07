<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Contract;

interface DocumentRepositoryInterface {

  public function upsertDocument(array $metadata): array;

  public function addSource(int $documentId, array $source): array;

  public function upsertContext(int $documentId, array $relation): array;

  public function addEvidence(int $documentId, array $evidence): int;

  public function revisionsForFamily(string $documentFamily): array;

  public function contextsForDocument(int $documentId): array;

  public function sourcesForDocument(int $documentId): array;

  public function upsertCommunicationRelation(int $documentId, int $communicationNid, string $role): array;

  public function communicationsForDocument(int $documentId): array;

  public function documentsForCommunication(int $communicationNid): array;

}
