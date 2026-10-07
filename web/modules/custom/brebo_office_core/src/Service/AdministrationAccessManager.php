<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\brebo_office_core\Contract\AdministrationAccessStoreInterface;

/**
 * Controls which approved administrations a user may work in.
 *
 * Drupal roles answer WHAT a user may do. This matrix answers WHERE and
 * whether that access has been explicitly released.
 */
final class AdministrationAccessManager {

  public function __construct(
    private readonly AdministrationRegistry $administrations,
    private readonly AdministrationAccessStoreInterface $store,
  ) {}

  /** @return array<string, array<string, mixed>> */
  public function memberships(int $userId): array {
    return $this->store->memberships($userId);
  }

  /** @return array<string, array<string, mixed>> */
  public function availableAdministrations(int $userId): array {
    $available = [];
    foreach ($this->memberships($userId) as $code => $membership) {
      if (($membership['status'] ?? '') !== 'released') {
        continue;
      }
      try {
        $administration = $this->administrations->get((string) $code);
      }
      catch (\InvalidArgumentException) {
        continue;
      }
      if (!($administration['active'] ?? FALSE)) {
        continue;
      }
      $available[(string) $code] = $administration + ['membership' => $membership];
    }
    return $available;
  }

  public function hasAccess(int $userId, string $administrationCode): bool {
    return isset($this->availableAdministrations($userId)[$administrationCode]);
  }

  /** @param string[] $roles */
  public function setMembership(int $userId, string $administrationCode, array $roles, string $status, int $actorUid): void {
    $this->administrations->get($administrationCode);
    if (!in_array($status, ['pending', 'released', 'blocked', 'revoked'], TRUE)) {
      throw new \InvalidArgumentException('Unsupported administration membership status.');
    }

    $memberships = $this->memberships($userId);
    $existing = $memberships[$administrationCode] ?? [];
    $memberships[$administrationCode] = [
      'administration_code' => $administrationCode,
      'roles' => array_values(array_unique(array_filter(array_map('strval', $roles)))),
      'status' => $status,
      'requested_at' => $existing['requested_at'] ?? gmdate(DATE_ATOM),
      'released_at' => $status === 'released' ? gmdate(DATE_ATOM) : NULL,
      'released_by' => $status === 'released' ? $actorUid : NULL,
      'updated_at' => gmdate(DATE_ATOM),
      'updated_by' => $actorUid,
    ];
    $this->store->saveMemberships($userId, $memberships);
  }

  public function onboardingRequired(int $userId): bool {
    $state = $this->store->onboardingState($userId);
    return ($state['status'] ?? '') !== 'completed';
  }

  public function completeOnboarding(int $userId, int $actorUid): void {
    if ($this->availableAdministrations($userId) === []) {
      throw new \LogicException('Onboarding cannot complete without a released administration.');
    }
    $this->store->saveOnboardingState($userId, [
      'status' => 'completed',
      'completed_at' => gmdate(DATE_ATOM),
      'completed_by' => $actorUid,
    ]);
  }

}
