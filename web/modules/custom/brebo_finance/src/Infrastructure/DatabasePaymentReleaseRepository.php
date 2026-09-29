<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\PaymentReleaseRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabasePaymentReleaseRepository implements PaymentReleaseRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function invoice(int $invoiceId): ?array {
    $row = $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i')
      ->condition('id', $invoiceId)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function release(int $releaseId): ?array {
    $row = $this->database->select('brebo_finance_payment_release', 'r')
      ->fields('r')
      ->condition('id', $releaseId)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function approvedGAccountInstruction(int $invoiceId): ?array {
    $row = $this->database->select('brebo_finance_g_account_instruction', 'g')
      ->fields('g', ['g_account_amount', 'effective_from', 'effective_until'])
      ->condition('direction', 'outgoing')
      ->condition('source_type', 'purchase_invoice')
      ->condition('source_id', $invoiceId)
      ->condition('status', 'approved')
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function insertRelease(array $values): int {
    return (int) $this->database->insert('brebo_finance_payment_release')->fields($values)->execute();
  }

  public function updateRelease(int $releaseId, array $values): void {
    $this->database->update('brebo_finance_payment_release')->fields($values)->condition('id', $releaseId)->execute();
  }

  public function updateInvoice(int $invoiceId, array $values): void {
    $this->database->update('brebo_finance_purchase_invoice')->fields($values)->condition('id', $invoiceId)->execute();
  }

  public function insertAudit(array $values): void {
    $this->database->insert('brebo_finance_audit')->fields($values)->execute();
  }

}
