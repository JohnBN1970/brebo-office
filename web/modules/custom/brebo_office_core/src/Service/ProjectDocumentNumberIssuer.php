<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\node\NodeInterface;

/** Issues administration-aware document numbers from project-derived context. */
final class ProjectDocumentNumberIssuer {

  public function __construct(
    private readonly AdministrationContextResolver $context,
    private readonly AdministrationNumberIssuer $numbers,
  ) {}

  /**
   * Issues an immutable number for a project-derived node/context owner.
   *
   * @return array<string, mixed>
   */
  public function issueForNode(
    NodeInterface $contextNode,
    string $series,
    string $ownerType,
    string $ownerId,
    ?int $year = NULL,
  ): array {
    $administrationCode = $this->context->codeForNode($contextNode);
    $receipt = $this->numbers->issue($administrationCode, $series, $ownerType, $ownerId, $year);
    $receipt['context_node_id'] = (int) $contextNode->id();
    return $receipt;
  }

  /** Convenience method for versioned offers created from a calculation. */
  public function issueQuotation(NodeInterface $calculation, int $version, ?int $year = NULL): array {
    if ($calculation->bundle() !== 'brebo_calculation') {
      throw new \InvalidArgumentException('Quotation numbering requires a BREBO calculation context.');
    }
    return $this->issueForNode(
      $calculation,
      'quotation',
      'offer_version',
      (string) $calculation->id() . ':v' . $version,
      $year,
    );
  }

  /** Issues one immutable sales-invoice number for a project invoice draft. */
  public function issueSalesInvoice(NodeInterface $project, int|string $draftId, ?int $year = NULL): array {
    if ($project->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('Sales invoice numbering requires a BREBO project context.');
    }
    return $this->issueForNode(
      $project,
      'sales_invoice',
      'sales_invoice_draft',
      (string) $draftId,
      $year,
    );
  }

  /** Issues one immutable assignment/order number in project context. */
  public function issueAssignment(NodeInterface $project, string $ownerId, ?int $year = NULL): array {
    if ($project->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('Assignment numbering requires a BREBO project context.');
    }
    return $this->issueForNode($project, 'assignment', 'assignment', $ownerId, $year);
  }

}
