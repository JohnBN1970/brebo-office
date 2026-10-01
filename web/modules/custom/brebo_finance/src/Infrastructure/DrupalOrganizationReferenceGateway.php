<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\OrganizationReferenceGatewayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Transitional adapter for canonical organization storage in Drupal nodes. */
final class DrupalOrganizationReferenceGateway implements OrganizationReferenceGatewayInterface {

  private const BUNDLE = 'brebo_organization';
  private const MONEYBIRD_FIELD = 'field_brebo_moneybird_contact_id';
  private const EMAIL_FIELD = 'field_brebo_org_email';

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public function get(int $organizationId): ?array {
    if ($organizationId <= 0) {
      return NULL;
    }
    $node = $this->entityTypeManager->getStorage('node')->load($organizationId);
    if (!$node instanceof NodeInterface || $node->bundle() !== self::BUNDLE) {
      return NULL;
    }
    $email = $node->hasField(self::EMAIL_FIELD) ? trim((string) $node->get(self::EMAIL_FIELD)->value) : '';
    return [
      'id' => (int) $node->id(),
      'name' => (string) $node->label(),
      'email' => $email,
      'payment_term_days' => $node->hasField('field_brebo_payment_term_days') && is_numeric($node->get('field_brebo_payment_term_days')->value)
        ? max(0, (int) $node->get('field_brebo_payment_term_days')->value)
        : NULL,
    ];
  }

  public function findIdsByMoneybirdContactId(string $contactId): array {
    $contactId = trim($contactId);
    if ($contactId === '') {
      return [];
    }
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE)
      ->condition(self::MONEYBIRD_FIELD, $contactId)
      ->range(0, 10)
      ->execute();
    return array_values(array_map('intval', $ids));
  }

  public function findIdsByExactName(string $name): array {
    $name = trim($name);
    if ($name === '') {
      return [];
    }
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE)
      ->condition('title', $name)
      ->range(0, 10)
      ->execute();
    return array_values(array_map('intval', $ids));
  }

  public function isMoneybirdUnlinked(int $organizationId): bool {
    if ($organizationId <= 0) {
      return FALSE;
    }
    $node = $this->entityTypeManager->getStorage('node')->load($organizationId);
    return $node instanceof NodeInterface
      && $node->bundle() === self::BUNDLE
      && $node->hasField(self::MONEYBIRD_FIELD)
      && $node->get(self::MONEYBIRD_FIELD)->isEmpty();
  }

  public function identityIndex(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_organization')
      ->execute();
    $result = [];
    foreach ($storage->loadMultiple($ids) as $organization) {
      if ($organization->bundle() !== 'brebo_organization') {
        continue;
      }
      $value = static function ($node, string $field): string {
        return $node->hasField($field) && !$node->get($field)->isEmpty()
          ? trim((string) ($node->get($field)->value ?? ''))
          : '';
      };
      $result[] = [
        'id' => (int) $organization->id(),
        'name' => (string) $organization->label(),
        'email' => $value($organization, 'field_brebo_org_email'),
        'moneybird_contact_id' => $value($organization, 'field_brebo_moneybird_contact_id'),
        'kvk' => $value($organization, 'field_brebo_org_kvk'),
        'vat' => $value($organization, 'field_brebo_org_vat'),
      ];
    }
    return $result;
  }


}
