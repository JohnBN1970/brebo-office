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
  /**
   * The private, reviewed map is configuration, never request data. It records
   * verification provenance; it is not a personnel register or credential store.
   * @return array<string,int>
   */
  public static function loadVerifiedMap(string $path): array {
    if ($path === '' || $path[0] !== '/' || !is_file($path) || is_link($path)
      || filesize($path) > 65536) {
      throw new RuntimeException('Verified identity map unavailable');
    }
    $document = json_decode((string)file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($document) || ($document['version'] ?? null) !== 1
      || !is_string($document['verified_by'] ?? null) || trim($document['verified_by']) === ''
      || !is_string($document['verified_at'] ?? null)
      || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $document['verified_at'])
      || strtotime($document['verified_at']) === false || strtotime($document['verified_at']) > time()
      || !is_array($document['mappings'] ?? null) || !array_is_list($document['mappings'])) {
      throw new RuntimeException('Identity verification provenance missing');
    }
    return OfficeInzetIdentityMappingGuard::validate($document['mappings']);
  }

  /** Resolve a selected existing Office person through exactly one verified source. */
  public static function verifiedPerson(array $verifiedMap, int $personId): int {
    $sources = array_keys($verifiedMap, $personId, true);
    if ($personId < 1 || count($sources) !== 1) {
      throw new RuntimeException('Personnel identity has no unique verified Office mapping');
    }
    [$system, $sourceId] = explode("\0", $sources[0], 2);
    return OfficeInzetIdentityMappingGuard::requireOfficePerson($verifiedMap, $system, $sourceId);
  }

}
