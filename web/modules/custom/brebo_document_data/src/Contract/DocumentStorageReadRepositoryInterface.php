<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Contract;

/** Read boundary for immutable document storage metadata. */
interface DocumentStorageReadRepositoryInterface {

  /** @return array{id:int,storage_provider:string,storage_key:?string,lifecycle_status:string}|null */
  public function storageRow(int $documentId): ?array;

}
