<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Infrastructure;

use Drupal\brebo_office_core\Contract\AdministrationAccessStoreInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/** Drupal KeyValue adapter for administration access state. */
final class DrupalAdministrationAccessStore implements AdministrationAccessStoreInterface {

  private const COLLECTION = 'brebo_office_core.administration_access';

  public function __construct(private readonly KeyValueFactoryInterface $keyValue) {}

  public function memberships(int $userId): array {
    $value = $this->store()->get('user:' . $userId, []);
    return is_array($value) ? $value : [];
  }

  public function saveMemberships(int $userId, array $memberships): void {
    $this->store()->set('user:' . $userId, $memberships);
  }

  public function onboardingState(int $userId): array {
    $value = $this->store()->get('onboarding:' . $userId, []);
    return is_array($value) ? $value : [];
  }

  public function saveOnboardingState(int $userId, array $state): void {
    $this->store()->set('onboarding:' . $userId, $state);
  }

  private function store() {
    return $this->keyValue->get(self::COLLECTION);
  }

}
