<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Contract;

/** Storage boundary for administration memberships and onboarding state. */
interface AdministrationAccessStoreInterface {

  /** @return array<string, array<string, mixed>> */
  public function memberships(int $userId): array;

  /** @param array<string, array<string, mixed>> $memberships */
  public function saveMemberships(int $userId, array $memberships): void;

  /** @return array<string, mixed> */
  public function onboardingState(int $userId): array;

  /** @param array<string, mixed> $state */
  public function saveOnboardingState(int $userId, array $state): void;

}
