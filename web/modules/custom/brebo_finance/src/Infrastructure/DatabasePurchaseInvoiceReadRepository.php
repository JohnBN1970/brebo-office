<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\PurchaseInvoiceReadRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for purchase-invoice read models and guards. */
final class DatabasePurchaseInvoiceReadRepository implements PurchaseInvoiceReadRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function invoices(): array {
    $table = 'brebo_finance_purchase_invoice';
    $schema = $this->database->schema();
    if (!$schema->tableExists($table)) {
      return [];
    }

    $wanted = ['id', 'project_nid', 'supplier_name', 'invoice_number', 'invoice_date', 'due_date', 'status', 'match_status', 'amount_ex_vat', 'vat_amount', 'amount_inc_vat', 'source_system', 'source', 'source_record_id', 'external_id', 'moneybird_id', 'created', 'changed'];
    $fields = array_values(array_filter($wanted, static fn(string $field): bool => $schema->fieldExists($table, $field)));
    if (!in_array('id', $fields, TRUE)) {
      return [];
    }

    $query = $this->database->select($table, 'i')->fields('i', $fields);
    if ($schema->fieldExists($table, 'invoice_date')) {
      $query->orderBy('invoice_date', 'DESC');
    }
    elseif ($schema->fieldExists($table, 'changed')) {
      $query->orderBy('changed', 'DESC');
    }
    else {
      $query->orderBy('id', 'DESC');
    }

    return array_map(static fn(object $row): array => (array) $row, $query->execute()->fetchAll());
  }

  public function invoice(int $invoiceId): ?array {
    if (!$this->database->schema()->tableExists('brebo_finance_purchase_invoice')) {
      return NULL;
    }
    $row = $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i')
      ->condition('id', $invoiceId)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function lines(int $invoiceId): array {
    if (!$this->database->schema()->tableExists('brebo_finance_purchase_invoice_line')) {
      return [];
    }
    return array_values($this->database->select('brebo_finance_purchase_invoice_line', 'l')
      ->fields('l')
      ->condition('invoice_id', $invoiceId)
      ->orderBy('line_number')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function commitmentLinesForProject(int $projectNid): array {
    $schema = $this->database->schema();
    if (!$schema->tableExists('brebo_finance_commitment_line') || !$schema->tableExists('brebo_finance_commitment')) {
      return [];
    }
    $query = $this->database->select('brebo_finance_commitment_line', 'cl');
    $query->join('brebo_finance_commitment', 'c', 'c.id = cl.commitment_id');
    foreach (['id', 'line_number', 'description', 'amount_ex_vat', 'unit_price_ex_vat', 'vat_code'] as $field) {
      if ($schema->fieldExists('brebo_finance_commitment_line', $field)) {
        $query->addField('cl', $field);
      }
    }
    foreach (['id', 'commitment_number', 'supplier_name'] as $field) {
      if ($schema->fieldExists('brebo_finance_commitment', $field)) {
        $query->addField('c', $field, $field === 'id' ? 'commitment_id' : $field);
      }
    }
    $query->condition('c.project_nid', $projectNid);
    if ($schema->fieldExists('brebo_finance_commitment', 'status')) {
      $query->condition('c.status', ['cancelled'], 'NOT IN');
    }
    $query->orderBy('c.id', 'DESC');
    if ($schema->fieldExists('brebo_finance_commitment_line', 'line_number')) {
      $query->orderBy('cl.line_number');
    }
    return array_values($query->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function invoiceLine(int $invoiceId, int $lineId): ?array {
    if (!$this->database->schema()->tableExists('brebo_finance_purchase_invoice_line')) {
      return NULL;
    }
    $row = $this->database->select('brebo_finance_purchase_invoice_line', 'il')
      ->fields('il')
      ->condition('id', $lineId)
      ->condition('invoice_id', $invoiceId)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function receiptBelongsToInvoice(int $invoiceId, int $receiptId): bool {
    $schema = $this->database->schema();
    if (!$schema->tableExists('brebo_finance_performance_receipt') || !$schema->tableExists('brebo_finance_purchase_invoice_line')) {
      return FALSE;
    }
    $query = $this->database->select('brebo_finance_performance_receipt', 'pr');
    $query->innerJoin('brebo_finance_purchase_invoice_line', 'il', 'il.commitment_line_id = pr.commitment_line_id');
    return $query->fields('pr', ['id'])
      ->condition('pr.id', $receiptId)
      ->condition('il.invoice_id', $invoiceId)
      ->range(0, 1)
      ->execute()
      ->fetchField() !== FALSE;
  }

  public function releaseBelongsToInvoice(int $invoiceId, int $releaseId): bool {
    if (!$this->database->schema()->tableExists('brebo_finance_payment_release')) {
      return FALSE;
    }
    return $this->database->select('brebo_finance_payment_release', 'pr')
      ->fields('pr', ['id'])
      ->condition('id', $releaseId)
      ->condition('invoice_id', $invoiceId)
      ->execute()
      ->fetchField() !== FALSE;
  }

}
