<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\brebo_office_core\Contract\OnboardingTourStoreInterface;

/** Stores resumable guided onboarding tours per user. */
final class OnboardingTourManager {

  public function __construct(private readonly OnboardingTourStoreInterface $store) {}

  /** @return array<string, mixed> */
  public function state(int $userId, string $tourId): array {
    return $this->store->get($userId, $tourId);
  }

  public function status(int $userId, string $tourId): string {
    return (string) ($this->state($userId, $tourId)['status'] ?? 'not_started');
  }

  public function start(int $userId, string $tourId, int $step = 0): void {
    $this->save($userId, $tourId, 'in_progress', max(0, $step));
  }

  public function advance(int $userId, string $tourId, int $step): void {
    $this->save($userId, $tourId, 'in_progress', max(0, $step));
  }

  public function complete(int $userId, string $tourId): void {
    $this->save($userId, $tourId, 'completed', 0);
  }

  public function skip(int $userId, string $tourId, int $step = 0): void {
    $this->save($userId, $tourId, 'skipped', max(0, $step));
  }

  private function save(int $userId, string $tourId, string $status, int $step): void {
    $this->store->set($userId, $tourId, [
      'status' => $status,
      'step' => $step,
      'updated_at' => gmdate(DATE_ATOM),
    ]);
  }

}
