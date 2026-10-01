<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Resolves canonical organization relations without exposing Drupal entities. */
interface OrganizationReferenceGatewayInterface {

  /** @return array{id:int,name:string,email:string,payment_term_days:?int}|null */
  public function get(int $organizationId): ?array;

  /** @return list<int> */
  public function findIdsByMoneybirdContactId(string $contactId): array;

  /** @return list<int> */
  public function findIdsByExactName(string $name): array;

  public function isMoneybirdUnlinked(int $organizationId): bool;

  /** @return array<int,string> */
  public function choices(): array;

  /** @return list<array{id:int,name:string,email:string,moneybird_contact_id:string,kvk:string,vat:string}> */
  public function identityIndex(): array;

}
