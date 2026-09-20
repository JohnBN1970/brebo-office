<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\user\UserInterface;

/**
 * Controls which approved administrations a user may work in.
 *
 * Drupal roles answer WHAT a user may do. This matrix answers WHERE and
 * whether that access has been explicitly released.
 */
final class AdministrationAccessManager {

  private const COLLECTION = 'brebo_office_core.administration_access';

  public function __construct(
    private readonly AdministrationRegistry $administrations,
    private readonly KeyValueFactoryInterface $keyValue,
  ) {}

  /**
   * Returns all administration memberships for a user.
   *
   * @return array<string, array<string, mixed>>
   */
  public function memberships(UserInterface $user): array {
    $value = $this->keyValue->get(self::COLLECTION)->get('user:' . $user->id(), []);
    return is_array($value) ? $value : [];
  }

  /**
   * Returns released, active administrations available to a user.
   *
   * @return array<string, array<string, mixed>>
   */
  public function availableAdministrations(UserInterface $user): array {
    $available = [];
    foreach ($this->memberships($user) as $code => $membership) {
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

  public function hasAccess(UserInterface $user, string $administrationCode): bool {
    return isset($this->availableAdministrations($user)[$administrationCode]);
  }

  /**
   * Creates or updates a membership; release is always explicit.
   *
   * @param string[] $roles
   *   Administration-scoped functional roles.
   */
  public function setMembership(UserInterface $user, string $administrationCode, array $roles, string $status, int $actorUid): void {
    $this->administrations->get($administrationCode);
    if (!in_array($status, ['pending', 'released', 'blocked', 'revoked'], TRUE)) {
      throw new \InvalidArgumentException('Unsupported administration membership status.');
    }

    $memberships = $this->memberships($user);
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
    $this->keyValue->get(self::COLLECTION)->set('user:' . $user->id(), $memberships);
  }

  public function onboardingRequired(UserInterface $user): bool {
    $state = $this->keyValue->get(self::COLLECTION)->get('onboarding:' . $user->id(), []);
    return !is_array($state) || ($state['status'] ?? '') !== 'completed';
  }

  public function completeOnboarding(UserInterface $user, int $actorUid): void {
    if ($this->availableAdministrations($user) === []) {
      throw new \LogicException('Onboarding cannot complete without a released administration.');
    }
    $this->keyValue->get(self::COLLECTION)->set('onboarding:' . $user->id(), [
      'status' => 'completed',
      'completed_at' => gmdate(DATE_ATOM),
      'completed_by' => $actorUid,
    ]);
  }

}
