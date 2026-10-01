<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\SalesInvoiceDebtorSourceRepositoryInterface;
use Drupal\brebo_finance\Contract\OrganizationReferenceGatewayInterface;

/** Resolves the canonical debtor relation for a finalized sales invoice. */
final class SalesInvoiceDebtorResolver {

  public function __construct(
    private readonly SalesInvoiceDebtorSourceRepositoryInterface $sources,
    private readonly OrganizationReferenceGatewayInterface $organizations,
  ) {}

  /** @return array{organization_id:int,name:string,email:string,draft_id:int} */
  public function resolve(int $salesInvoiceId): array {
    $source = $this->sources->source($salesInvoiceId);
    if ($source === NULL) {
      throw new \RuntimeException('Definitieve verkoopfactuur is niet beschikbaar.');
    }
    $draftId = (int) $source['draft_id'];
    if ($draftId <= 0) {
      throw new \RuntimeException('Canonieke bron van deze verkoopfactuur kon niet worden teruggevonden.');
    }

    $organizationId = (int) $source['organization_id'];
    $organization = $this->organizations->get($organizationId);
    if ($organization === NULL) {
      throw new \RuntimeException('Canonieke debiteurrelatie ontbreekt.');
    }
    $email = trim((string) $organization['email']);
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) {
      throw new \RuntimeException('Debiteur heeft geen geldig centraal e-mailadres.');
    }

    return ['organization_id' => $organizationId, 'name' => (string) $organization['name'], 'email' => $email, 'draft_id' => $draftId];
  }
}
