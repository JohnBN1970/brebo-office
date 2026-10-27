<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

use Brebo\Finance\Service\InvoicePerformanceBlockerResolver;
use Brebo\Finance\Service\PurchaseInvoiceControlViewBuilder;
use Brebo\Finance\Service\PayablesWorkQueueBuilder;
use PDO;

final class OfficeFinanceApplication {
  public function __construct(private readonly PDO $db, private readonly ?PDO $source = null, private readonly string $tablePrefix = '') {}

  /** Read-only, access-scoped candidate search. */
  public function duplicateInvoiceCandidates(string $operatorId, string $invoiceNumber, string $supplierName = ''): array {
    $access = new OfficeFinanceAccess($this->db, $operatorId);
    if (!$access->allows()) throw new \DomainException('Finance access required');
    $repository = new OfficeFinanceReadRepository($this->source ?? $this->db, $access, null, $this->tablePrefix);
    return $repository->duplicateInvoiceCandidates($invoiceNumber, $supplierName);
  }

  public function read(string $operatorId, ?int $invoiceId = null, ?int $officeProjectId = null): array {
    $access = new OfficeFinanceAccess($this->db, $operatorId);
    if (!$access->allows()) throw new \DomainException('Finance access required');
    $projects = $access->visibleProjects();
    $scope = null;
    if ($officeProjectId !== null) {
      $authorized = array_filter(
        $access->draftProjects(),
        static fn(array $project): bool => (int)$project['office_project_id'] === $officeProjectId
      );
      if ($authorized === []) throw new \OutOfBoundsException('Project unavailable');

      $projects = array_filter(
        $projects,
        static fn(array $project): bool => (int)$project['office_project_id'] === $officeProjectId
      );

      if ($projects === []) {
        if ($invoiceId !== null) throw new \OutOfBoundsException('Invoice unavailable');

        $scope = reset($authorized);
        $queueNames = array_keys(OfficeFinanceView::QUEUES);

        return [
          'project' => $scope,
          'queues' => array_fill_keys($queueNames, []),
          'counts' => array_fill_keys($queueNames, 0),
          'amounts_inc_vat' => array_fill_keys($queueNames, 0.0),
          'generated_at' => time(),
          'finance_unlinked_project' => true,
        ];
      }

      $scope = reset($projects);
    }
    $repository = new OfficeFinanceReadRepository($this->source ?? $this->db, $access, $officeProjectId, $this->tablePrefix);
    $control = new PurchaseInvoiceControlViewBuilder($repository, new InvoicePerformanceBlockerResolver($repository));
    if ($invoiceId !== null) {
      if ($repository->invoice($invoiceId) === null) throw new \OutOfBoundsException('Invoice unavailable');
      return $control->build($invoiceId) + ['project' => $scope];
    }
    return (new PayablesWorkQueueBuilder($repository, new OfficeFinanceProjects((int)$operatorId, $projects), $control))->build((int)$operatorId) + ['project' => $scope];
  }
}
