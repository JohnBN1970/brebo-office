<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\BillingControlRepositoryInterface;
use Drupal\Core\Database\Connection;
use RuntimeException;

/** Database adapter for billing instalments and sales-invoice mirror persistence. */
final class DatabaseBillingControlRepository implements BillingControlRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function approvedContract(int $contractId): ?array {
    $row = $this->database->select('brebo_finance_project_contract', 'c')
      ->fields('c', ['id', 'project_nid', 'status'])
      ->condition('id', $contractId)
      ->execute()->fetchAssoc();
    return $row === FALSE ? NULL : [
      'id' => (int) $row['id'],
      'project_nid' => (int) $row['project_nid'],
      'status' => (string) $row['status'],
    ];
  }

  public function createInstalment(array $fields, array $lines, int $projectNid, int $actorUid, int $now): int {
    $transaction = $this->database->startTransaction();
    try {
      $id = (int) $this->database->insert('brebo_finance_billing_instalment')->fields($fields)->execute();
      if ($lines !== []) {
        $this->replaceInstalmentLines($id, $projectNid, $lines, $actorUid, $now);
      }
      unset($transaction);
      return $id;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  public function instalment(int $instalmentId): ?array {
    $row = $this->database->select('brebo_finance_billing_instalment', 'i')
      ->fields('i')->condition('id', $instalmentId)->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function instalmentForProject(int $instalmentId, int $projectNid): ?array {
    $row = $this->database->select('brebo_finance_billing_instalment', 'i')
      ->fields('i')
      ->condition('id', $instalmentId)
      ->condition('project_nid', $projectNid)
      ->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function updateInstalment(int $instalmentId, array $fields): void {
    $this->database->update('brebo_finance_billing_instalment')
      ->fields($fields)->condition('id', $instalmentId)->execute();
  }

  public function salesInvoiceByMoneybirdId(string $moneybirdId): ?array {
    $row = $this->database->select('brebo_finance_sales_invoice', 'i')
      ->fields('i', ['id', 'source_hash', 'recorded_at'])
      ->condition('moneybird_id', $moneybirdId)
      ->execute()->fetchAssoc();
    return $row === FALSE ? NULL : [
      'id' => (int) $row['id'],
      'source_hash' => (string) $row['source_hash'],
      'recorded_at' => (int) $row['recorded_at'],
    ];
  }

  public function persistSalesInvoice(
    ?int $existingId,
    array $fields,
    array $lines,
    int $projectNid,
    ?int $instalmentId,
    string $status,
    int $actorUid,
    int $now,
  ): int {
    $transaction = $this->database->startTransaction();
    try {
      if ($existingId === NULL) {
        $fields['created'] = $now;
        $fields['created_by'] = $actorUid;
        $id = (int) $this->database->insert('brebo_finance_sales_invoice')->fields($fields)->execute();
      }
      else {
        $id = $existingId;
        $this->database->update('brebo_finance_sales_invoice')->fields($fields)->condition('id', $id)->execute();
      }

      if ($lines !== []) {
        $this->replaceSalesInvoiceLines($id, $projectNid, $lines, $actorUid, $now);
      }

      if ($instalmentId !== NULL && in_array($status, ['sent', 'paid', 'overdue', 'disputed'], TRUE)) {
        $this->database->update('brebo_finance_billing_instalment')
          ->fields([
            'status' => $status === 'paid' ? 'paid' : 'invoiced',
            'sales_invoice_id' => $id,
            'changed' => $now,
            'changed_by' => $actorUid,
          ])
          ->condition('id', $instalmentId)
          ->condition('project_nid', $projectNid)
          ->condition('status', ['billable', 'invoiced'], 'IN')
          ->execute();
      }

      unset($transaction);
      return $id;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  private function replaceInstalmentLines(int $instalmentId, int $projectNid, array $lines, int $actorUid, int $now): void {
    if (!$this->database->schema()->tableExists('brebo_finance_billing_instalment_line')) {
      throw new RuntimeException('Billing instalment line storage is not installed. Run database updates.');
    }
    $this->database->delete('brebo_finance_billing_instalment_line')->condition('instalment_id', $instalmentId)->execute();
    foreach ($lines as $delta => $line) {
      $this->database->insert('brebo_finance_billing_instalment_line')->fields([
        'instalment_id' => $instalmentId,
        'project_nid' => $projectNid,
        'line_number' => $delta + 1,
        'description' => $line['description'],
        'amount_ex_vat' => $line['amount_ex_vat'],
        'vat_code' => $line['vat_code'],
        'vat_rate' => $line['vat_rate'],
        'vat_amount' => $line['vat_amount'],
        'amount_inc_vat' => $line['amount_inc_vat'],
        'source_ref' => $line['source_ref'] !== '' ? $line['source_ref'] : NULL,
        'created' => $now,
        'created_by' => $actorUid,
        'changed' => $now,
        'changed_by' => $actorUid,
      ])->execute();
    }
  }

  private function replaceSalesInvoiceLines(int $invoiceId, int $projectNid, array $lines, int $actorUid, int $now): void {
    if (!$this->database->schema()->tableExists('brebo_finance_sales_invoice_line')) {
      throw new RuntimeException('Sales invoice line storage is not installed. Run database updates.');
    }
    $this->database->delete('brebo_finance_sales_invoice_line')->condition('sales_invoice_id', $invoiceId)->execute();
    foreach ($lines as $delta => $line) {
      $this->database->insert('brebo_finance_sales_invoice_line')->fields([
        'sales_invoice_id' => $invoiceId,
        'project_nid' => $projectNid,
        'line_number' => $delta + 1,
        'description' => $line['description'],
        'amount_ex_vat' => $line['amount_ex_vat'],
        'vat_code' => $line['vat_code'],
        'vat_rate' => $line['vat_rate'],
        'vat_amount' => $line['vat_amount'],
        'amount_inc_vat' => $line['amount_inc_vat'],
        'source_ref' => $line['source_ref'] !== '' ? $line['source_ref'] : NULL,
        'created' => $now,
        'created_by' => $actorUid,
        'changed' => $now,
        'changed_by' => $actorUid,
      ])->execute();
    }
  }

}
