<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\SupplierOrganizationGatewayInterface;

/** Resolves Moneybird supplier contacts to canonical BREBO organizations. */
final class MoneybirdSupplierResolver {

  public function __construct(private readonly SupplierOrganizationGatewayInterface $organizations) {}

  /**
   * @param array<string, mixed> $supplierContact
   * @return array{id:int,name:string,email:string}|null
   */
  public function resolve(string $contactId, string $supplierName, bool $create = TRUE, array $supplierContact = []): ?array {
    return $this->organizations->resolve($contactId, $supplierName, $create, $supplierContact);
  }

}
