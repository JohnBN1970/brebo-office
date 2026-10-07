<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/** Issues administration-aware document numbers from project-derived context. */
final class ProjectDocumentNumberIssuer {

  public function __construct(
    private readonly AdministrationContextResolver $context,
    private readonly AdministrationNumberIssuer $numbers,
  ) {}

  /** @return array<string, mixed> */
  public function issueForContextNode(
    int $contextNodeId,
    string $series,
    string $ownerType,
    string $ownerId,
    ?int $year = NULL,
  ): array {
    $administrationCode = $this->context->codeForContextNode($contextNodeId);
    $receipt = $this->numbers->issue($administrationCode, $series, $ownerType, $ownerId, $year);
    $receipt['context_node_id'] = $contextNodeId;
    return $receipt;
  }

  public function issueQuotation(int $calculationId, int $version, ?int $year = NULL): array {
    if ($this->context->bundleForContextNode($calculationId) !== 'brebo_calculation') {
      throw new \InvalidArgumentException('Quotation numbering requires a BREBO calculation context.');
    }
    return $this->issueForContextNode($calculationId, 'quotation', 'offer_version', $calculationId . ':v' . $version, $year);
  }

  public function issueSalesInvoice(int $projectId, int|string $draftId, ?int $year = NULL): array {
    if ($this->context->bundleForContextNode($projectId) !== 'brebo_project') {
      throw new \InvalidArgumentException('Sales invoice numbering requires a BREBO project context.');
    }
    return $this->issueForContextNode($projectId, 'sales_invoice', 'sales_invoice_draft', (string) $draftId, $year);
  }

  public function issueAssignment(int $projectId, string $ownerId, ?int $year = NULL): array {
    if ($this->context->bundleForContextNode($projectId) !== 'brebo_project') {
      throw new \InvalidArgumentException('Assignment numbering requires a BREBO project context.');
    }
    return $this->issueForContextNode($projectId, 'assignment', 'assignment', $ownerId, $year);
  }

}
