<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Contract;

interface WebsiteOpportunityGatewayInterface {

  public function configuredOwnerUid(): int;

  public function ownerIsActive(int $ownerUid): bool;

  public function findOpportunityIdByTitle(string $title): ?int;

  /** @param array<string,mixed> $values */
  public function createOpportunity(string $title, int $ownerUid, array $values): int;

}
