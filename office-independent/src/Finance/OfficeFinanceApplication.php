<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

use Brebo\Finance\Service\InvoicePerformanceBlockerResolver;
use Brebo\Finance\Service\PurchaseInvoiceControlViewBuilder;
use Brebo\Finance\Service\PayablesWorkQueueBuilder;
use PDO;

final class OfficeFinanceApplication {
  public function __construct(private readonly PDO $db) {}

  public function read(string $operatorId, ?int $invoiceId = null): array {
    $access = new OfficeFinanceAccess($this->db, $operatorId);
    if (!$access->allows()) throw new \DomainException('Finance access required');
    $repository = new OfficeFinanceReadRepository($this->db, $access);
    $control = new PurchaseInvoiceControlViewBuilder($repository, new InvoicePerformanceBlockerResolver($repository));
    if ($invoiceId !== null) {
      if ($repository->invoice($invoiceId) === null) throw new \OutOfBoundsException('Invoice unavailable');
      return $control->build($invoiceId);
    }
    return (new PayablesWorkQueueBuilder($repository, new OfficeFinanceProjects((int)$operatorId, $access->visibleProjects()), $control))->build((int)$operatorId);
  }
}
