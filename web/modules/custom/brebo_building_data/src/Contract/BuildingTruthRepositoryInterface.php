<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Contract;

interface BuildingTruthRepositoryInterface {

  public function current(int $buildingNid, string $factKey, ?int $objectId = NULL): ?array;

  public function currentFacts(int $buildingNid, ?int $objectId = NULL): array;

  public function propose(
    int $buildingNid,
    string $factKey,
    mixed $value,
    int $proposedBy,
    ?int $objectId = NULL,
    ?int $sourceProjectNid = NULL,
    string $sourceType = 'project',
    ?string $sourceRef = NULL,
    array $evidence = [],
    ?string $reason = NULL,
  ): int;

  public function acceptProposal(int $proposalId, int $verifiedBy, ?string $verificationNote = NULL): array;

  public function rejectProposal(int $proposalId, int $verifiedBy, string $verificationNote): void;

  public function history(int $buildingNid, string $factKey, ?int $objectId = NULL): array;

  public function pendingProposals(int $buildingNid): array;

}
