<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use InvalidArgumentException;
use RuntimeException;

/**
 * Pure verification contract for bridging a Drupal OnSite invitation to
 * an existing Office person. No implicit Drupal UID == Office ID assumption.
 */
final class OnSiteActivationIdentityBridge {
  /**
   * @param array<string,int> $verifiedMap from OfficeInzetIdentityMappingGuard
   * @param array{uid:int,created:int} $invitation
   */
  public static function officePerson(array $verifiedMap, array $invitation, int $now): int {
    $uid = $invitation['uid'] ?? null;
    $created = $invitation['created'] ?? null;
    if (!is_int($uid) || $uid < 1 || !is_int($created)
      || $created < 1 || $created > $now || $now - $created >= 604800) {
      throw new InvalidArgumentException('Expired or invalid Drupal activation');
    }
    return OfficeInzetIdentityMappingGuard::requireOfficePerson(
      $verifiedMap, 'drupal', (string)$uid
    );
  }
}
