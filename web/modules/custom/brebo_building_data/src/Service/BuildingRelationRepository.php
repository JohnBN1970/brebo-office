<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Duplicate-safe storage and lookup for high-volume building relations.
 */
final class BuildingRelationRepository {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Adds or refreshes an address relation for a BREBO building.
   *
   * @param array<string, mixed> $address
   *
   * @return array{state:string,id:int,normalized_key:string}
   */
  public function upsertAddress(int $buildingNid, array $address): array {
    $this->assertBuilding($buildingNid);
    $normalizedKey = $this->normalizeAddressKey($address);
    if ($normalizedKey === '') {
      throw new \InvalidArgumentException('Voor een gebouwadres is minimaal een bruikbare adresidentiteit vereist.');
    }

    $existingId = $this->database->select('brebo_building_address', 'a')
      ->fields('a', ['id'])
      ->condition('building_nid', $buildingNid)
      ->condition('normalized_key', $normalizedKey)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    $now = $this->time->getRequestTime();
    $fields = [
      'street' => $this->clean($address['street'] ?? $address['straatnaam'] ?? NULL),
      'house_number' => $this->clean($address['house_number'] ?? $address['huisnummer'] ?? NULL),
      'house_letter' => $this->clean($address['house_letter'] ?? $address['huisletter'] ?? NULL),
      'addition' => $this->clean($address['addition'] ?? $address['huisnummertoevoeging'] ?? NULL),
      'postal_code' => strtoupper($this->clean($address['postal_code'] ?? $address['postcode'] ?? NULL)),
      'city' => $this->clean($address['city'] ?? $address['woonplaatsnaam'] ?? NULL),
      'country' => $this->clean($address['country'] ?? NULL) ?: 'Nederland',
      'is_primary' => !empty($address['is_primary']) ? 1 : 0,
      'source' => $this->clean($address['source'] ?? NULL),
      'source_ref' => $this->clean($address['source_ref'] ?? NULL),
      'changed' => $now,
    ];

    if ($existingId) {
      $this->database->update('brebo_building_address')
        ->fields($fields)
        ->condition('id', (int) $existingId)
        ->execute();
      return ['state' => 'updated', 'id' => (int) $existingId, 'normalized_key' => $normalizedKey];
    }

    $id = (int) $this->database->insert('brebo_building_address')
      ->fields(['building_nid' => $buildingNid, 'normalized_key' => $normalizedKey, 'created' => $now] + $fields)
      ->execute();
    return ['state' => 'created', 'id' => $id, 'normalized_key' => $normalizedKey];
  }

  /**
   * Adds or refreshes a BAG identity relation for a BREBO building.
   *
   * @return array{state:string,id:int,conflict_building_ids:int[]}
   */
  public function upsertBagIdentity(int $buildingNid, string $bagType, string $bagId, array $metadata = []): array {
    $this->assertBuilding($buildingNid);
    $bagType = strtolower($this->clean($bagType));
    $bagId = $this->clean($bagId);
    if ($bagType === '' || $bagId === '') {
      throw new \InvalidArgumentException('BAG type en BAG id zijn verplicht.');
    }

    $conflictIds = array_map('intval', $this->database->select('brebo_building_bag_identity', 'b')
      ->fields('b', ['building_nid'])
      ->condition('bag_type', $bagType)
      ->condition('bag_id', $bagId)
      ->condition('building_nid', $buildingNid, '<>')
      ->distinct()
      ->execute()
      ->fetchCol());

    $existingId = $this->database->select('brebo_building_bag_identity', 'b')
      ->fields('b', ['id'])
      ->condition('building_nid', $buildingNid)
      ->condition('bag_type', $bagType)
      ->condition('bag_id', $bagId)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    $now = $this->time->getRequestTime();
    $fields = [
      'status' => $this->clean($metadata['status'] ?? NULL),
      'is_primary' => !empty($metadata['is_primary']) ? 1 : 0,
      'source' => $this->clean($metadata['source'] ?? NULL),
      'source_ref' => $this->clean($metadata['source_ref'] ?? NULL),
      'retrieved_at' => $this->clean($metadata['retrieved_at'] ?? NULL),
      'changed' => $now,
    ];

    if ($existingId) {
      $this->database->update('brebo_building_bag_identity')
        ->fields($fields)
        ->condition('id', (int) $existingId)
        ->execute();
      return ['state' => 'updated', 'id' => (int) $existingId, 'conflict_building_ids' => $conflictIds];
    }

    $id = (int) $this->database->insert('brebo_building_bag_identity')
      ->fields([
        'building_nid' => $buildingNid,
        'bag_type' => $bagType,
        'bag_id' => $bagId,
        'created' => $now,
      ] + $fields)
      ->execute();

    return ['state' => 'created', 'id' => $id, 'conflict_building_ids' => $conflictIds];
  }

  /** @return int[] */
  public function findBuildingIdsByAddress(array $address): array {
    $normalizedKey = $this->normalizeAddressKey($address);
    if ($normalizedKey === '') {
      return [];
    }
    return array_values(array_unique(array_map('intval', $this->database->select('brebo_building_address', 'a')
      ->fields('a', ['building_nid'])
      ->condition('normalized_key', $normalizedKey)
      ->distinct()
      ->execute()
      ->fetchCol())));
  }

  /** @return int[] */
  public function findBuildingIdsByBagIdentity(string $bagType, string $bagId): array {
    $bagType = strtolower($this->clean($bagType));
    $bagId = $this->clean($bagId);
    if ($bagType === '' || $bagId === '') {
      return [];
    }
    return array_values(array_unique(array_map('intval', $this->database->select('brebo_building_bag_identity', 'b')
      ->fields('b', ['building_nid'])
      ->condition('bag_type', $bagType)
      ->condition('bag_id', $bagId)
      ->distinct()
      ->execute()
      ->fetchCol())));
  }

  /**
   * Resolves one PDOK candidate to a unique known BREBO building when possible.
   *
   * BAG identity is preferred over address identity. Multiple building matches
   * are returned as ambiguous and must never be guessed away.
   *
   * @param array<string, mixed> $candidate
   *
   * @return array{state:string,building_id:?int,candidate_building_ids:int[],basis:string}
   */
  public function resolveBuildingCandidate(array $candidate): array {
    $properties = is_array($candidate['properties'] ?? NULL) ? $candidate['properties'] : [];
    $bagKeys = [
      'pand_id' => 'pand',
      'adresseerbaarobject_id' => 'adresseerbaarobject',
      'verblijfsobject_id' => 'verblijfsobject',
      'nummeraanduiding_id' => 'nummeraanduiding',
    ];

    foreach ($bagKeys as $propertyKey => $bagType) {
      $bagId = $this->clean($properties[$propertyKey] ?? NULL);
      if ($bagId === '') {
        continue;
      }
      $ids = $this->findBuildingIdsByBagIdentity($bagType, $bagId);
      if (count($ids) === 1) {
        return ['state' => 'matched', 'building_id' => $ids[0], 'candidate_building_ids' => $ids, 'basis' => sprintf('Exacte BAG-%s-identiteit.', $bagType)];
      }
      if (count($ids) > 1) {
        return ['state' => 'ambiguous', 'building_id' => NULL, 'candidate_building_ids' => $ids, 'basis' => sprintf('BAG-%s-identiteit is aan meerdere BREBO-gebouwen gekoppeld.', $bagType)];
      }
    }

    $ids = $this->findBuildingIdsByAddress([
      'street' => $properties['straatnaam'] ?? NULL,
      'house_number' => $properties['huisnummer'] ?? NULL,
      'house_letter' => $properties['huisletter'] ?? NULL,
      'addition' => $properties['huisnummertoevoeging'] ?? NULL,
      'postal_code' => $properties['postcode'] ?? NULL,
      'city' => $properties['woonplaatsnaam'] ?? NULL,
    ]);
    if (count($ids) === 1) {
      return ['state' => 'matched', 'building_id' => $ids[0], 'candidate_building_ids' => $ids, 'basis' => 'Exact bekend gebouwadres.'];
    }
    if (count($ids) > 1) {
      return ['state' => 'ambiguous', 'building_id' => NULL, 'candidate_building_ids' => $ids, 'basis' => 'Hetzelfde genormaliseerde adres is aan meerdere BREBO-gebouwen gekoppeld.'];
    }

    return ['state' => 'unmatched', 'building_id' => NULL, 'candidate_building_ids' => [], 'basis' => 'Geen bestaande BAG- of adresidentiteit gevonden.'];
  }

  /** @return array<int, array<string, mixed>> */
  public function addressesForBuilding(int $buildingNid): array {
    return $this->database->select('brebo_building_address', 'a')
      ->fields('a')
      ->condition('building_nid', $buildingNid)
      ->orderBy('is_primary', 'DESC')
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC) ?: [];
  }

  /** @return array<int, array<string, mixed>> */
  public function bagIdentitiesForBuilding(int $buildingNid): array {
    return $this->database->select('brebo_building_bag_identity', 'b')
      ->fields('b')
      ->condition('building_nid', $buildingNid)
      ->orderBy('is_primary', 'DESC')
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC) ?: [];
  }

  private function assertBuilding(int $buildingNid): void {
    $node = $this->entityTypeManager->getStorage('node')->load($buildingNid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_building') {
      throw new \InvalidArgumentException(sprintf('Node %d is geen BREBO-gebouw.', $buildingNid));
    }
  }

  /** @param array<string, mixed> $address */
  private function normalizeAddressKey(array $address): string {
    $postcode = strtoupper(preg_replace('/\s+/u', '', $this->clean($address['postal_code'] ?? $address['postcode'] ?? NULL)) ?? '');
    $number = $this->clean($address['house_number'] ?? $address['huisnummer'] ?? NULL);
    $letter = strtoupper($this->clean($address['house_letter'] ?? $address['huisletter'] ?? NULL));
    $addition = strtoupper($this->clean($address['addition'] ?? $address['huisnummertoevoeging'] ?? NULL));
    if ($postcode !== '' && $number !== '') {
      return strtolower(implode('|', array_filter([$postcode, $number, $letter, $addition], static fn (string $v): bool => $v !== '')));
    }

    $street = mb_strtolower($this->clean($address['street'] ?? $address['straatnaam'] ?? NULL));
    $city = mb_strtolower($this->clean($address['city'] ?? $address['woonplaatsnaam'] ?? NULL));
    $fallback = trim(implode('|', array_filter([$street, $number, $letter, $addition, $city], static fn (string $v): bool => $v !== '')), '|');
    return preg_replace('/\s+/u', ' ', $fallback) ?? $fallback;
  }

  private function clean(mixed $value): string {
    return trim((string) ($value ?? ''));
  }

}
