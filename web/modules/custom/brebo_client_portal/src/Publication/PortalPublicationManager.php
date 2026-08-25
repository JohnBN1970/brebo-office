<?php

declare(strict_types=1);

namespace Drupal\brebo_client_portal\Publication;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Creates and promotes explicit client-portal publications.
 */
final class PortalPublicationManager {

  public function __construct(
    private Connection $database,
    private PortalPublicationPolicy $policy,
    private TimeInterface $time,
    private UuidInterface $uuid,
    private AccountProxyInterface $currentUser,
  ) {}

  /**
   * Stores a sanitized draft publication.
   */
  public function saveDraft(int $portalProjectId, string $sourceType, string $sourceId, string $publicationType, array $payload): int {
    $safePayload = $this->policy->sanitize($publicationType, $payload);
    $now = $this->time->getRequestTime();

    return (int) $this->database->insert('brebo_portal_publication')->fields([
      'uuid' => $this->uuid->generate(),
      'portal_project_id' => $portalProjectId,
      'source_type' => $sourceType,
      'source_id' => $sourceId,
      'publication_type' => $publicationType,
      'payload_json' => json_encode($safePayload, JSON_THROW_ON_ERROR),
      'status' => 'draft',
      'created' => $now,
      'changed' => $now,
    ])->execute();
  }

  /**
   * Promotes one existing draft to published after an explicit user action.
   */
  public function publish(int $publicationId): void {
    $record = $this->database->select('brebo_portal_publication', 'p')
      ->fields('p', ['id', 'portal_project_id', 'status'])
      ->condition('id', $publicationId)
      ->execute()
      ->fetchAssoc();

    if ($record === FALSE || (string) $record['status'] !== 'draft') {
      throw new \InvalidArgumentException('Only an existing draft publication can be published.');
    }

    $now = $this->time->getRequestTime();
    $this->database->update('brebo_portal_publication')->fields([
      'status' => 'published',
      'published_by_uid' => (int) $this->currentUser->id(),
      'published_at' => $now,
      'revoked_by_uid' => NULL,
      'revoked_at' => NULL,
      'changed' => $now,
    ])->condition('id', $publicationId)->execute();

    $this->database->update('brebo_portal_project')->fields([
      'last_published_at' => $now,
      'changed' => $now,
    ])->condition('id', (int) $record['portal_project_id'])->execute();
  }

  /**
   * Revokes one published item without deleting its audit history.
   */
  public function revoke(int $publicationId): void {
    $now = $this->time->getRequestTime();
    $updated = $this->database->update('brebo_portal_publication')->fields([
      'status' => 'revoked',
      'revoked_by_uid' => (int) $this->currentUser->id(),
      'revoked_at' => $now,
      'changed' => $now,
    ])->condition('id', $publicationId)->condition('status', 'published')->execute();

    if ($updated === 0) {
      throw new \InvalidArgumentException('Only a published portal item can be revoked.');
    }
  }

}
