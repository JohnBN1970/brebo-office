<?php

declare(strict_types=1);

namespace Drupal\brebo_client_portal\Publication;

/**
 * Deny-by-default policy for external portal projections.
 *
 * Internal entities are never serialized directly. Only explicit publication
 * types and explicit payload keys are accepted by this boundary.
 */
final class PortalPublicationPolicy {

  private const ALLOWED_KEYS = [
    'project_summary' => ['name', 'address', 'status', 'message', 'project_contact', 'last_updated'],
    'progress' => ['percentage', 'label', 'last_updated'],
    'planning_item' => ['description', 'period', 'status'],
    'milestone' => ['title', 'period', 'status'],
    'document' => ['title', 'description', 'revision', 'published_at'],
    'photo' => ['caption', 'taken_at', 'published_at'],
    'message' => ['subject', 'body', 'sent_at', 'sender_label'],
    'appointment' => ['title', 'starts_at', 'ends_at', 'status'],
    'issue' => ['title', 'description', 'status', 'reported_at'],
    'punch_item' => ['title', 'description', 'status', 'updated_at'],
  ];

  /**
   * Returns a safe payload containing only allowlisted external keys.
   */
  public function sanitize(string $publicationType, array $payload): array {
    $allowed = self::ALLOWED_KEYS[$publicationType] ?? NULL;
    if ($allowed === NULL) {
      throw new \InvalidArgumentException(sprintf('Publication type "%s" is not allowlisted.', $publicationType));
    }

    return array_intersect_key($payload, array_flip($allowed));
  }

  public function isAllowedType(string $publicationType): bool {
    return isset(self::ALLOWED_KEYS[$publicationType]);
  }

}
