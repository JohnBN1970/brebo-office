<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Contract;

/** Read boundary for document revision series. */
interface DocumentRevisionReadRepositoryInterface {

  /** @return array<int,array<string,mixed>> */
  public function revisions(string $contextType, int $contextId, string $documentFamily): array;

}
