<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Service;

use Drupal\Core\Database\Connection;

/** Shared intake pipeline for mail, notes, WhatsApp imports, documents and manual text. */
final class AddressScopeIntake {

  public function __construct(
    private readonly Connection $database,
    private readonly AddressScopeParser $parser,
    private readonly PdokBagResolver $resolver,
  ) {}

  /**
   * Detect, resolve and store address proposals. Materialization remains explicit.
   *
   * @return array<int, array<string, mixed>>
   */
  public function propose(string $text, string $sourceType, ?string $sourceId = NULL, ?int $buildingNid = NULL, ?int $projectId = NULL, ?int $uid = NULL): array {
    $proposals = [];
    foreach ($this->parser->parse($text) as $scope) {
      // Parser output originates from untrusted free text. A false-positive match
      // must never overflow the persistence schema or make mail intake fail.
      if (!$this->isPersistableScope($scope)) {
        continue;
      }

      $now = time();
      $id = (int) $this->database->insert('brebo_address_scope_intake')->fields([
        'building_nid' => $buildingNid,
        'project_id' => $projectId,
        'source_type' => $sourceType,
        'source_id' => $sourceId,
        'source_text' => $text,
        'matched_text' => $scope['matched_text'],
        'street' => $scope['street'],
        'range_from' => $scope['range_from'],
        'range_to' => $scope['range_to'],
        'range_parity' => $scope['range_parity'],
        'postal_code' => $scope['postal_code'],
        'city' => $scope['city'],
        'status' => 'resolving',
        'created_by_uid' => $uid,
        'created' => $now,
      ])->execute();
      try {
        $resolved = $this->resolver->resolve($scope);
        $this->database->update('brebo_address_scope_intake')->fields([
          'status' => $resolved ? 'resolved' : 'no_match',
          'result_count' => count($resolved),
          'resolution_json' => json_encode($resolved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          'resolved_at' => time(),
        ])->condition('id', $id)->execute();
        $proposals[] = ['intake_id' => $id, 'scope' => $scope, 'addresses' => $resolved];
      }
      catch (\Throwable $e) {
        $this->database->update('brebo_address_scope_intake')->fields(['status' => 'error', 'error_message' => $e->getMessage(), 'resolved_at' => time()])->condition('id', $id)->execute();
        $proposals[] = ['intake_id' => $id, 'scope' => $scope, 'addresses' => [], 'error' => $e->getMessage()];
      }
    }
    return $proposals;
  }

  /**
   * Reject implausible parser output instead of truncating it into an address.
   *
   * Lengths mirror brebo_address_scope_intake exactly. This keeps false-positive
   * matches from arbitrary mail bodies out of the database and prevents SQL
   * exceptions from exposing full source text in operational logs.
   *
   * @param array<string, mixed> $scope
   */
  private function isPersistableScope(array $scope): bool {
    $limits = [
      'matched_text' => 512,
      'street' => 255,
      'range_parity' => 16,
      'postal_code' => 16,
      'city' => 128,
    ];

    foreach ($limits as $key => $limit) {
      $value = $scope[$key] ?? NULL;
      if ($value !== NULL && mb_strlen((string) $value) > $limit) {
        return FALSE;
      }
    }

    return trim((string) ($scope['matched_text'] ?? '')) !== '';
  }

  /** Materialize a resolved proposal into canonical building addresses and residences. */
  public function materialize(int $intakeId, int $buildingNid, ?int $projectId = NULL): int {
    $row = $this->database->select('brebo_address_scope_intake', 'i')->fields('i')->condition('id', $intakeId)->execute()->fetchAssoc();
    if (!$row || !in_array($row['status'], ['resolved', 'materialized'], TRUE)) {
      throw new \InvalidArgumentException('Address scope must be resolved before materialization.');
    }
    $addresses = json_decode((string) ($row['resolution_json'] ?? '[]'), TRUE, 512, JSON_THROW_ON_ERROR);
    $count = 0;
    foreach ($addresses as $address) {
      $normalizedKey = mb_strtolower(implode('|', array_filter([$address['street'] ?? NULL, $address['house_number'] ?? NULL, $address['house_letter'] ?? NULL, $address['addition'] ?? NULL, $address['postal_code'] ?? NULL])));
      $existingAddress = $this->database->select('brebo_building_address', 'a')->fields('a', ['id'])->condition('building_nid', $buildingNid)->condition('normalized_key', $normalizedKey)->execute()->fetchField();
      $buildingAddressId = $existingAddress ? (int) $existingAddress : (int) $this->database->insert('brebo_building_address')->fields([
        'building_nid' => $buildingNid, 'normalized_key' => $normalizedKey, 'street' => $address['street'] ?? NULL, 'house_number' => $address['house_number'] ?? NULL,
        'house_letter' => $address['house_letter'] ?? NULL, 'addition' => $address['addition'] ?? NULL, 'postal_code' => $address['postal_code'] ?? NULL, 'city' => $address['city'] ?? NULL,
        'country' => 'Nederland', 'is_primary' => 0, 'source' => 'pdok_bag', 'source_ref' => (string) $intakeId, 'created' => time(), 'changed' => time(),
      ])->execute();
      $bagId = $address['bag_verblijfsobject_id'] ?? $address['bag_nummeraanduiding_id'] ?? NULL;
      $query = $this->database->select('brebo_residence', 'r')->fields('r', ['id'])->condition('building_nid', $buildingNid);
      $bagId ? $query->condition('bag_verblijfsobject_id', $bagId) : $query->condition('building_address_id', $buildingAddressId);
      if (!$query->execute()->fetchField()) {
        $line = trim(implode(' ', array_filter([$address['street'] ?? NULL, $address['house_number'] ?? NULL, $address['house_letter'] ?? NULL, $address['addition'] ?? NULL])));
        $this->database->insert('brebo_residence')->fields([
          'project_id' => $projectId, 'building_nid' => $buildingNid, 'building_address_id' => $buildingAddressId,
          'bag_nummeraanduiding_id' => $address['bag_nummeraanduiding_id'] ?? NULL, 'bag_verblijfsobject_id' => $address['bag_verblijfsobject_id'] ?? NULL,
          'address_line' => $line, 'street' => $address['street'] ?? NULL, 'house_number' => $address['house_number'] ?? NULL, 'house_letter' => $address['house_letter'] ?? NULL,
          'addition' => $address['addition'] ?? NULL, 'postal_code' => $address['postal_code'] ?? NULL, 'city' => $address['city'] ?? NULL,
          'source' => 'pdok_bag', 'source_ref' => (string) $intakeId, 'created' => time(), 'changed' => time(),
        ])->execute();
        $count++;
      }
    }
    $this->database->update('brebo_address_scope_intake')->fields(['building_nid' => $buildingNid, 'project_id' => $projectId, 'status' => 'materialized', 'materialized_at' => time()])->condition('id', $intakeId)->execute();
    return $count;
  }
}
