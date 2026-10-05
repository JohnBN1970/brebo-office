<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\ReceivablesWorkspaceReadRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseReceivablesWorkspaceReadRepository implements ReceivablesWorkspaceReadRepositoryInterface {
  public function __construct(private readonly Connection $database) {}

  public function salesInvoiceDrafts(int $limit = 50): array {
    if (!$this->database->schema()->tableExists('brebo_finance_sales_invoice_draft')) return [];
    return array_values($this->database->select('brebo_finance_sales_invoice_draft','d')
      ->fields('d',['id','draft_number','project_nid','invoice_date','due_date','status','amount_inc_vat'])
      ->orderBy('created','DESC')->range(0,$limit)->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function salesInvoices(int $limit = 50): array {
    if (!$this->database->schema()->tableExists('brebo_finance_sales_invoice')) return [];
    return array_values($this->database->select('brebo_finance_sales_invoice','i')
      ->fields('i',['id','invoice_number','project_nid','invoice_date','due_date','status','amount_inc_vat','paid_amount_inc_vat'])
      ->orderBy('due_date','ASC')->range(0,$limit)->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function salesInvoiceIds(int $limit = 250): array {
    if (!$this->database->schema()->tableExists('brebo_finance_sales_invoice')) return [];
    return array_map('intval', $this->database->select('brebo_finance_sales_invoice','i')
      ->fields('i',['id'])->orderBy('due_date')->range(0,$limit)->execute()->fetchCol());
  }
}
