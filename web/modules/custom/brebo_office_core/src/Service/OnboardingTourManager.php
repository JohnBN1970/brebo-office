<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\user\UserInterface;

/** Stores resumable guided onboarding tours per user. */
final class OnboardingTourManager {

  private const COLLECTION = 'brebo_office_core.onboarding_tours';

  public function __construct(private readonly KeyValueFactoryInterface $keyValue) {}

  /** @return array<string, mixed> */
  public function state(UserInterface $user, string $tourId): array {
    $state = $this->keyValue->get(self::COLLECTION)->get($this->key($user, $tourId), []);
    return is_array($state) ? $state : [];
  }

  public function status(UserInterface $user, string $tourId): string {
    return (string) ($this->state($user, $tourId)['status'] ?? 'not_started');
  }

  public function start(UserInterface $user, string $tourId, int $step = 0): void {
    $this->save($user, $tourId, 'in_progress', max(0, $step));
  }

  public function advance(UserInterface $user, string $tourId, int $step): void {
    $this->save($user, $tourId, 'in_progress', max(0, $step));
  }

  public function complete(UserInterface $user, string $tourId): void {
    $this->save($user, $tourId, 'completed', 0);
  }

  public function skip(UserInterface $user, string $tourId, int $step = 0): void {
    $this->save($user, $tourId, 'skipped', max(0, $step));
  }

  private function save(UserInterface $user, string $tourId, string $status, int $step): void {
    $this->keyValue->get(self::COLLECTION)->set($this->key($user, $tourId), [
      'status' => $status,
      'step' => $step,
      'updated_at' => gmdate(DATE_ATOM),
    ]);
  }

  private function key(UserInterface $user, string $tourId): string {
    return 'user:' . $user->id() . ':tour:' . $tourId;
  }

}
