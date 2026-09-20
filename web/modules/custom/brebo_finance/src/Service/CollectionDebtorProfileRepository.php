<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/** Stores structured legal/collection debtor data keyed to the canonical relation. */
final class CollectionDebtorProfileRepository {

  private const STORE = 'brebo_finance.collection_debtor_profile';

  public function __construct(private readonly KeyValueFactoryInterface $keyValueFactory) {}

  /** @return array<string,mixed> */
  public function get(int $organizationId): array {
    $value = $this->keyValueFactory->get(self::STORE)->get((string) $organizationId, []);
    return is_array($value) ? $value : [];
  }

  /** @param array<string,mixed> $profile */
  public function save(int $organizationId, array $profile, int $actorUid): void {
    if ($organizationId <= 0) throw new \InvalidArgumentException('Organization id is required.');
    $normalized = [
      'type' => in_array((string) ($profile['type'] ?? 'business'), ['business', 'individual'], TRUE) ? (string) $profile['type'] : 'business',
      'company_name' => trim((string) ($profile['company_name'] ?? '')),
      'first_name' => trim((string) ($profile['first_name'] ?? '')),
      'last_name' => trim((string) ($profile['last_name'] ?? '')),
      'customer_number' => trim((string) ($profile['customer_number'] ?? '')),
      'email' => trim((string) ($profile['email'] ?? '')),
      'address' => [
        'street' => trim((string) ($profile['street'] ?? '')),
        'house_number' => trim((string) ($profile['house_number'] ?? '')),
        'postal_code' => trim((string) ($profile['postal_code'] ?? '')),
        'city' => trim((string) ($profile['city'] ?? '')),
        'country_code' => strtoupper(trim((string) ($profile['country_code'] ?? 'NL'))),
      ],
      'changed' => time(),
      'changed_by' => $actorUid,
    ];
    $this->keyValueFactory->get(self::STORE)->set((string) $organizationId, $normalized);
  }

  public function complete(int $organizationId): bool {
    $profile = $this->get($organizationId);
    $address = is_array($profile['address'] ?? NULL) ? $profile['address'] : [];
    foreach (['street', 'house_number', 'postal_code', 'city'] as $field) {
      if (trim((string) ($address[$field] ?? '')) === '') return FALSE;
    }
    if ((string) ($profile['type'] ?? 'business') === 'individual') return trim((string) ($profile['last_name'] ?? '')) !== '';
    return trim((string) ($profile['company_name'] ?? '')) !== '';
  }
}
