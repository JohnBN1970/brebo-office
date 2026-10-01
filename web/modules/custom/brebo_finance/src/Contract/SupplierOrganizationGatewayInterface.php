<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/**
 * Resolves and safely enriches supplier organizations without exposing storage.
 */
interface SupplierOrganizationGatewayInterface {

  /**
   * @param array<string, mixed> $supplierContact
   * @return array{id:int,name:string,email:string}|null
   */
  public function resolve(string $contactId, string $supplierName, bool $create = TRUE, array $supplierContact = []): ?array;

}
