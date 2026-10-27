<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

use Brebo\Finance\Contract\PayablesWorkQueueRepositoryInterface;
use Brebo\Finance\Contract\PurchaseInvoiceControlRepositoryInterface;
use Brebo\Finance\Contract\InvoicePerformanceSourceRepositoryInterface;
use PDO;

/** Read-only adapter over the existing Finance tables, scoped before domain aggregation. */
final class OfficeFinanceReadRepository implements PayablesWorkQueueRepositoryInterface, PurchaseInvoiceControlRepositoryInterface, InvoicePerformanceSourceRepositoryInterface {
  private array $projects;
  private bool $unassigned;

  public function __construct(private readonly PDO $db, OfficeFinanceAccess $access) {
    if (!$access->allows()) throw new \DomainException('Finance access required');
    $this->projects = array_keys($access->visibleProjects());
    $this->unassigned = $access->allows('unassigned_read');
  }

  private function scope(): string {
    // IDs originate in the verified database mapping, never in request parameters.
    $ids = implode(',', array_map('intval', $this->projects));
    $projectScope = $ids === '' ? '1=0' : 'i.project_nid IN (' . $ids . ')';
    return '(' . $projectScope . ($this->unassigned ? ' OR COALESCE(i.project_nid,0)=0' : '') . ')';
  }

  private function rows(string $sql, array $args = []): array {
    $q = $this->db->prepare($sql);
    $q->execute($args);
    return $q->fetchAll(PDO::FETCH_ASSOC);
  }

  public function purchaseInvoiceTableExists(): bool {
    $this->db->query('SELECT id FROM brebo_finance_purchase_invoice WHERE 1=0');
    return true;
  }
  public function available(): bool { return $this->purchaseInvoiceTableExists(); }
  public function invoiceLineTableExists(): bool {
    $this->db->query('SELECT id FROM brebo_finance_purchase_invoice_line WHERE 1=0');
    return true;
  }
  public function performanceTableExists(): bool {
    $this->db->query('SELECT id FROM brebo_finance_performance_receipt WHERE 1=0');
    return true;
  }
  public function openInvoices(): array {
    return $this->rows("SELECT i.* FROM brebo_finance_purchase_invoice i WHERE " . $this->scope() . " AND i.status NOT IN ('paid','cancelled') ORDER BY i.due_date,i.id");
  }
  public function invoice(int $invoiceId): ?array {
    return $this->rows('SELECT i.* FROM brebo_finance_purchase_invoice i WHERE i.id=? AND ' . $this->scope(), [$invoiceId])[0] ?? null;
  }
  public function lines(int $invoiceId): array {
    if ($this->invoice($invoiceId) === null) return [];
    return $this->rows('SELECT l.* FROM brebo_finance_purchase_invoice_line l WHERE l.invoice_id=? ORDER BY l.line_number,l.id', [$invoiceId]);
  }
  public function latestPaymentRelease(int $invoiceId): ?array {
    if ($this->invoice($invoiceId) === null) return null;
    return $this->rows('SELECT * FROM brebo_finance_payment_release WHERE invoice_id=? ORDER BY id DESC LIMIT 1', [$invoiceId])[0] ?? null;
  }
  public function latestGAccountInstruction(int $invoiceId): ?array {
    if ($this->invoice($invoiceId) === null) return null;
    return $this->rows("SELECT * FROM brebo_finance_g_account_instruction WHERE source_type='purchase_invoice' AND direction='outgoing' AND source_id=? ORDER BY id DESC LIMIT 1", [$invoiceId])[0] ?? null;
  }
  public function invoiceLine(int $invoiceLineId): ?array {
    return $this->rows('SELECT l.*,i.invoice_number,i.supplier_name,i.due_date,i.invoice_date FROM brebo_finance_purchase_invoice_line l INNER JOIN brebo_finance_purchase_invoice i ON i.id=l.invoice_id WHERE l.id=? AND ' . $this->scope(), [$invoiceLineId])[0] ?? null;
  }
  public function performances(int $commitmentLineId): array {
    // Do not disclose performance receipts from an unauthorized project, even if a source line is miscoded.
    return $this->rows('SELECT r.* FROM brebo_finance_performance_receipt r
      INNER JOIN brebo_finance_commitment_line cl ON cl.id=r.commitment_line_id
      INNER JOIN brebo_finance_commitment c ON c.id=cl.commitment_id
      WHERE r.commitment_line_id=? AND EXISTS (
        SELECT 1 FROM brebo_finance_purchase_invoice_line l
        INNER JOIN brebo_finance_purchase_invoice i ON i.id=l.invoice_id
        WHERE l.commitment_line_id=r.commitment_line_id AND c.project_nid=i.project_nid AND r.project_nid=i.project_nid AND ' . $this->scope() . ')
      ORDER BY r.changed DESC,r.id', [$commitmentLineId]);
  }
}
