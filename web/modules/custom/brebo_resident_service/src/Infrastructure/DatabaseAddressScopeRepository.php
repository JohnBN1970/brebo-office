<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Infrastructure;

use Drupal\brebo_resident_service\Contract\AddressScopeRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for address-scope persistence. */
final class DatabaseAddressScopeRepository implements AddressScopeRepositoryInterface {

  public function __construct(
    private readonly Connection $database,
  ) {}

  public function createIntake(
    array $scope,
    string $sourceText,
    string $sourceType,
    ?string $sourceId,
    ?int $buildingNid,
    ?int $projectId,
    ?int $uid,
    int $createdAt,
  ): int {
    return (int) $this->database->insert('brebo_address_scope_intake')->fields([
      'building_nid' => $buildingNid,
      'project_id' => $projectId,
      'source_type' => $sourceType,
      'source_id' => $sourceId,
      'source_text' => $sourceText,
      'matched_text' => $scope['matched_text'],
      'street' => $scope['street'],
      'range_from' => $scope['range_from'],
      'range_to' => $scope['range_to'],
      'range_parity' => $scope['range_parity'],
      'postal_code' => $scope['postal_code'],
      'city' => $scope['city'],
      'status' => 'resolving',
      'created_by_uid' => $uid,
      'created' => $createdAt,
    ])->execute();
  }

  public function markResolved(int $intakeId, array $resolved, int $resolvedAt): void {
    $this->database->update('brebo_address_scope_intake')->fields([
      'status' => $resolved !== [] ? 'resolved' : 'no_match',
      'result_count' => count($resolved),
      'resolution_json' => json_encode($resolved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'resolved_at' => $resolvedAt,
    ])->condition('id', $intakeId)->execute();
  }

  public function markError(int $intakeId, string $message, int $resolvedAt): void {
    $this->database->update('brebo_address_scope_intake')->fields([
      'status' => 'error',
      'error_message' => $message,
      'resolved_at' => $resolvedAt,
    ])->condition('id', $intakeId)->execute();
  }

  public function intake(int $intakeId): ?array {
    $row = $this->database->select('brebo_address_scope_intake', 'i')
      ->fields('i')
      ->condition('id', $intakeId)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function ensureBuildingAddress(int $buildingNid, int $intakeId, string $normalizedKey, array $address, int $now): int {
    $existing = $this->database->select('brebo_building_address', 'a')
      ->fields('a', ['id'])
      ->condition('building_nid', $buildingNid)
      ->condition('normalized_key', $normalizedKey)
      ->execute()
      ->fetchField();
    if ($existing) {
      return (int) $existing;
    }

    return (int) $this->database->insert('brebo_building_address')->fields([
      'building_nid' => $buildingNid,
      'normalized_key' => $normalizedKey,
      'street' => $address['street'] ?? NULL,
      'house_number' => $address['house_number'] ?? NULL,
      'house_letter' => $address['house_letter'] ?? NULL,
      'addition' => $address['addition'] ?? NULL,
      'postal_code' => $address['postal_code'] ?? NULL,
      'city' => $address['city'] ?? NULL,
      'country' => 'Nederland',
      'is_primary' => 0,
      'source' => 'pdok_bag',
      'source_ref' => (string) $intakeId,
      'created' => $now,
      'changed' => $now,
    ])->execute();
  }

  public function residenceExists(int $buildingNid, int $buildingAddressId, array $address): bool {
    $bagId = $address['bag_verblijfsobject_id'] ?? $address['bag_nummeraanduiding_id'] ?? NULL;
    $query = $this->database->select('brebo_residence', 'r')
      ->fields('r', ['id'])
      ->condition('building_nid', $buildingNid);
    $bagId
      ? $query->condition('bag_verblijfsobject_id', $bagId)
      : $query->condition('building_address_id', $buildingAddressId);
    return (bool) $query->execute()->fetchField();
  }

  public function createResidence(
    int $buildingNid,
    int $buildingAddressId,
    int $intakeId,
    array $address,
    ?int $projectId,
    string $addressLine,
    int $now,
  ): void {
    $this->database->insert('brebo_residence')->fields([
      'project_id' => $projectId,
      'building_nid' => $buildingNid,
      'building_address_id' => $buildingAddressId,
      'bag_nummeraanduiding_id' => $address['bag_nummeraanduiding_id'] ?? NULL,
      'bag_verblijfsobject_id' => $address['bag_verblijfsobject_id'] ?? NULL,
      'address_line' => $addressLine,
      'street' => $address['street'] ?? NULL,
      'house_number' => $address['house_number'] ?? NULL,
      'house_letter' => $address['house_letter'] ?? NULL,
      'addition' => $address['addition'] ?? NULL,
      'postal_code' => $address['postal_code'] ?? NULL,
      'city' => $address['city'] ?? NULL,
      'source' => 'pdok_bag',
      'source_ref' => (string) $intakeId,
      'created' => $now,
      'changed' => $now,
    ])->execute();
  }

  public function markMaterialized(int $intakeId, int $buildingNid, ?int $projectId, int $materializedAt): void {
    $this->database->update('brebo_address_scope_intake')->fields([
      'building_nid' => $buildingNid,
      'project_id' => $projectId,
      'status' => 'materialized',
      'materialized_at' => $materializedAt,
    ])->condition('id', $intakeId)->execute();
  }

}
