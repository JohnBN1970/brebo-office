<?php

declare(strict_types=1);

namespace Drupal\brebo_client_portal\Contract;

interface PortalPublicationStoreInterface {

  public function saveDraft(
    int $portalProjectId,
    string $sourceType,
    string $sourceId,
    string $publicationType,
    array $payload,
  ): int;

  public function publish(int $publicationId): void;

  public function revoke(int $publicationId): void;

}
