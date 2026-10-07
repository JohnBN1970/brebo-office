<?php

declare(strict_types=1);

namespace Drupal\brebo_client_portal\Publication;

use Drupal\brebo_client_portal\Contract\PortalPublicationStoreInterface;

/**
 * Creates and promotes explicit client-portal publications.
 */
final class PortalPublicationManager {

  public function __construct(
    private readonly PortalPublicationStoreInterface $store,
    private readonly PortalPublicationPolicy $policy,
  ) {}

  public function saveDraft(int $portalProjectId, string $sourceType, string $sourceId, string $publicationType, array $payload): int {
    return $this->store->saveDraft(
      $portalProjectId,
      $sourceType,
      $sourceId,
      $publicationType,
      $this->policy->sanitize($publicationType, $payload),
    );
  }

  public function publish(int $publicationId): void {
    $this->store->publish($publicationId);
  }

  public function revoke(int $publicationId): void {
    $this->store->revoke($publicationId);
  }

}
