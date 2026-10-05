<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\PaymentCenterReadRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for payment-center read models. */
final class DatabasePaymentCenterReadRepository implements PaymentCenterReadRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function paymentBatches(int $limit = 50): array {
    if (!$this->database->schema()->tableExists('brebo_finance_payment_batch')) {
      return [];
    }
    return array_values($this->database->select('brebo_finance_payment_batch', 'b')
      ->fields('b')
      ->orderBy('created', 'DESC')
      ->range(0, $limit)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function bankReconciliations(int $limit = 50): array {
    if (!$this->database->schema()->tableExists('brebo_finance_bank_reconciliation')) {
      return [];
    }
    return array_values($this->database->select('brebo_finance_bank_reconciliation', 'r')
      ->fields('r')
      ->orderBy('created', 'DESC')
      ->range(0, $limit)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function approvedPaymentReleases(int $limit = 100): array {
    if (!$this->database->schema()->tableExists('brebo_finance_payment_release')) {
      return [];
    }
    $query = $this->database->select('brebo_finance_payment_release', 'r');
    if ($this->database->schema()->tableExists('brebo_finance_purchase_invoice')) {
      $query->leftJoin('brebo_finance_purchase_invoice', 'i', 'i.id = r.invoice_id');
      $query->addField('i', 'invoice_number');
      $query->addField('i', 'supplier_name');
    }
    $query->fields('r')
      ->condition('r.status', 'approved')
      ->orderBy('r.created', 'DESC')
      ->range(0, $limit);
    return array_values($query->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

}
