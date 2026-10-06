<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Service;

use Drupal\brebo_resident_service\Contract\AddressScopeRepositoryInterface;

/** Shared intake pipeline for mail, notes, WhatsApp imports, documents and manual text. */
final class AddressScopeIntake {

  public function __construct(
    private readonly AddressScopeRepositoryInterface $repository,
    private readonly AddressScopeParser $parser,
    private readonly PdokBagResolver $resolver,
  ) {}

  /**
   * Detect, resolve and store address proposals. Materialization remains explicit.
   *
   * @return array<int,array<string,mixed>>
   */
  public function propose(
    string $text,
    string $sourceType,
    ?string $sourceId = NULL,
    ?int $buildingNid = NULL,
    ?int $projectId = NULL,
    ?int $uid = NULL,
  ): array {
    $proposals = [];
    foreach ($this->parser->parse($text) as $scope) {
      if (!$this->isPersistableScope($scope)) {
        continue;
      }

      $id = $this->repository->createIntake(
        $scope,
        $text,
        $sourceType,
        $sourceId,
        $buildingNid,
        $projectId,
        $uid,
        time(),
      );

      try {
        $resolved = $this->resolver->resolve($scope);
        $this->repository->markResolved($id, $resolved, time());
        $proposals[] = [
          'intake_id' => $id,
          'scope' => $scope,
          'addresses' => $resolved,
        ];
      }
      catch (\Throwable $e) {
        $this->repository->markError($id, $e->getMessage(), time());
        $proposals[] = [
          'intake_id' => $id,
          'scope' => $scope,
          'addresses' => [],
          'error' => $e->getMessage(),
        ];
      }
    }

    return $proposals;
  }

  /**
   * Reject implausible parser output instead of truncating it into an address.
   *
   * @param array<string,mixed> $scope
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
    $row = $this->repository->intake($intakeId);
    if ($row === NULL || !in_array((string) ($row['status'] ?? ''), ['resolved', 'materialized'], TRUE)) {
      throw new \InvalidArgumentException('Address scope must be resolved before materialization.');
    }

    $addresses = json_decode(
      (string) ($row['resolution_json'] ?? '[]'),
      TRUE,
      512,
      JSON_THROW_ON_ERROR,
    );

    $count = 0;
    foreach ($addresses as $address) {
      if (!is_array($address)) {
        continue;
      }

      $normalizedKey = mb_strtolower(implode('|', array_filter([
        $address['street'] ?? NULL,
        $address['house_number'] ?? NULL,
        $address['house_letter'] ?? NULL,
        $address['addition'] ?? NULL,
        $address['postal_code'] ?? NULL,
      ])));

      $now = time();
      $buildingAddressId = $this->repository->ensureBuildingAddress(
        $buildingNid,
        $intakeId,
        $normalizedKey,
        $address,
        $now,
      );

      if ($this->repository->residenceExists($buildingNid, $buildingAddressId, $address)) {
        continue;
      }

      $line = trim(implode(' ', array_filter([
        $address['street'] ?? NULL,
        $address['house_number'] ?? NULL,
        $address['house_letter'] ?? NULL,
        $address['addition'] ?? NULL,
      ])));

      $this->repository->createResidence(
        $buildingNid,
        $buildingAddressId,
        $intakeId,
        $address,
        $projectId,
        $line,
        $now,
      );
      $count++;
    }

    $this->repository->markMaterialized($intakeId, $buildingNid, $projectId, time());
    return $count;
  }

}
