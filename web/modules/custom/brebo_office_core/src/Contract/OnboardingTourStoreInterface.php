<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Contract;

/** Storage boundary for resumable onboarding tour state. */
interface OnboardingTourStoreInterface {

  /** @return array<string, mixed> */
  public function get(int $userId, string $tourId): array;

  /** @param array<string, mixed> $state */
  public function set(int $userId, string $tourId, array $state): void;

}
