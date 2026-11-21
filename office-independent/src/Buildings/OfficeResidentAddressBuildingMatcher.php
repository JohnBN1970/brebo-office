<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

require_once __DIR__ . '/OfficeIntakeBuildingResolver.php';

/**
 * Read-only bridge from structured document/mail address proposals to
 * existing canonical Office building identity. No new building is created.
 */
final class OfficeResidentAddressBuildingMatcher {
  public function __construct(private readonly OfficeIntakeBuildingResolver $resolver) {}

  public function match(array $proposal): array {
    if (($proposal['status'] ?? '') !== 'proposal') {
      return ['status' => 'needs_review', 'reason' => 'incomplete_address', 'building_id' => null];
    }
    $address = $proposal['address'] ?? [];
    if (!is_array($address)) return ['status' => 'needs_review', 'reason' => 'invalid_address', 'building_id' => null];
    $street = trim((string)($address['street'] ?? ''));
    $number = trim((string)($address['number'] ?? ''));
    $addition = trim((string)($address['addition'] ?? ''));
    $city = trim((string)($address['city'] ?? ''));
    $postcode = trim((string)($address['postcode'] ?? ''));
    if ($street === '' || $number === '' || $city === '' || $postcode === '') {
      return ['status' => 'needs_review', 'reason' => 'insufficient_building_identity', 'building_id' => null];
    }
    $line = trim($street . ' ' . $number . ($addition !== '' ? ' ' . $addition : ''));
    $match = $this->resolver->resolve($line, $postcode, $city);
    if ($match['status'] === 'linked') {
      return ['status' => 'candidate', 'building_id' => $match['building_id'],
        'reason' => 'exact_existing_building_address', 'resident_write_allowed' => false];
    }
    return ['status' => 'needs_review', 'reason' => $match['status'] === 'ambiguous' ? 'ambiguous_building' : 'building_not_found',
      'building_id' => null, 'candidates' => $match['candidates']];
  }
}
