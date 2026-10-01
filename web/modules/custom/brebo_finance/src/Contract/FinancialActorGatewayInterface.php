<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Provides Finance with actor identity and permissions without exposing Drupal users. */
interface FinancialActorGatewayInterface {

  /** @return list<array{uid:int,display_name:string,mail:string,roles:list<string>}> */
  public function activeActors(): array;

  public function hasPermission(int $actorUid, string $permission): bool;

}
