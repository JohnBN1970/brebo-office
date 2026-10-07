<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Service;

use Drupal\brebo_document_data\Contract\DocumentRepositoryInterface;

/**
 * Stable document API delegating persistence to the infrastructure boundary.
 */
final class DocumentRepository implements DocumentRepositoryInterface {

  public function __construct(
    private readonly DocumentRepositoryInterface $repository,
  ) {}

  public function upsertDocument(array $metadata): array {
    return $this->repository->upsertDocument($metadata);
  }

  public function addSource(int $documentId, array $source): array {
    return $this->repository->addSource($documentId, $source);
  }

  public function upsertContext(int $documentId, array $relation): array {
    return $this->repository->upsertContext($documentId, $relation);
  }

  public function addEvidence(int $documentId, array $evidence): int {
    return $this->repository->addEvidence($documentId, $evidence);
  }

  public function revisionsForFamily(string $documentFamily): array {
    return $this->repository->revisionsForFamily($documentFamily);
  }

  public function contextsForDocument(int $documentId): array {
    return $this->repository->contextsForDocument($documentId);
  }

  public function sourcesForDocument(int $documentId): array {
    return $this->repository->sourcesForDocument($documentId);
  }

  public function upsertCommunicationRelation(int $documentId, int $communicationNid, string $role): array {
    return $this->repository->upsertCommunicationRelation($documentId, $communicationNid, $role);
  }

  public function communicationsForDocument(int $documentId): array {
    return $this->repository->communicationsForDocument($documentId);
  }

  public function documentsForCommunication(int $communicationNid): array {
    return $this->repository->documentsForCommunication($communicationNid);
  }

}
