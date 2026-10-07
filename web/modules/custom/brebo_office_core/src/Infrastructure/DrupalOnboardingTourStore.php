<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Infrastructure;

use Drupal\brebo_office_core\Contract\OnboardingTourStoreInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/** Drupal KeyValue adapter for onboarding tour state. */
final class DrupalOnboardingTourStore implements OnboardingTourStoreInterface {

  private const COLLECTION = 'brebo_office_core.onboarding_tours';

  public function __construct(private readonly KeyValueFactoryInterface $keyValue) {}

  public function get(int $userId, string $tourId): array {
    $state = $this->store()->get($this->key($userId, $tourId), []);
    return is_array($state) ? $state : [];
  }

  public function set(int $userId, string $tourId, array $state): void {
    $this->store()->set($this->key($userId, $tourId), $state);
  }

  private function store() {
    return $this->keyValue->get(self::COLLECTION);
  }

  private function key(int $userId, string $tourId): string {
    return 'user:' . $userId . ':tour:' . $tourId;
  }

}
