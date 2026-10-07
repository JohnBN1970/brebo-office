<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\PaymentAnomalyReadRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for payment anomaly reads. */
final class DatabasePaymentAnomalyReadRepository implements PaymentAnomalyReadRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function hasSupplierInvoices(): bool {
    return $this->database->schema()->tableExists('brebo_supplier_invoice');
  }

  public function findThresholdPatterns(int $since): array {
    if (!$this->database->schema()->fieldExists('brebo_supplier_invoice', 'created')) {
      return [];
    }
    $query = $this->database->select('brebo_supplier_invoice', 'i');
    $query->addField('i', 'supplier_name');
    $query->addExpression('COUNT(*)', 'invoice_count');
    $query->addExpression('COALESCE(SUM(gross_amount),0)', 'total_amount');
    $query->condition('created', $since, '>=');
    $or = $query->orConditionGroup();
    foreach ([5000, 10000, 25000, 50000] as $limit) {
      $or->condition('gross_amount', [$limit * 0.95, $limit], 'BETWEEN');
    }
    $query->condition($or);
    $query->groupBy('supplier_name');
    $query->having('COUNT(*) >= 3');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function findRepeatExceptionPatterns(int $since): array {
    $query = $this->database->select('brebo_supplier_invoice', 'i');
    $query->addField('i', 'supplier_name');
    $query->addExpression('COUNT(*)', 'exception_count');
    $query->addExpression('COALESCE(SUM(gross_amount),0)', 'exception_amount');
    if ($this->database->schema()->fieldExists('brebo_supplier_invoice', 'created')) {
      $query->condition('created', $since, '>=');
    }
    $query->condition('match_status', 'matched', '<>');
    $query->groupBy('supplier_name');
    $query->having('COUNT(*) >= 3');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function findDecisionPairPatterns(int $since): array {
    if (!$this->database->schema()->tableExists('brebo_procurement_decision')) {
      return [];
    }
    $query = $this->database->select('brebo_procurement_decision', 'd');
    $query->addField('d', 'selected_supplier');
    $query->addField('d', 'decided_by');
    $query->addField('d', 'approved_by');
    $query->addExpression('COUNT(*)', 'decision_count');
    $query->condition('created', $since, '>=');
    $query->isNotNull('approved_by');
    $query->groupBy('selected_supplier');
    $query->groupBy('decided_by');
    $query->groupBy('approved_by');
    $query->having('COUNT(*) >= 5');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function findRecentBankChangeSignals(int $since): array {
    if (!$this->database->schema()->tableExists('brebo_payment_control_event')) {
      return [];
    }
    $query = $this->database->select('brebo_payment_control_event', 'e');
    $query->fields('e', ['supplier_name', 'amount', 'event_type']);
    $query->condition('created_at', $since, '>=');
    $query->condition('event_type', ['iban_changed', 'g_account_changed'], 'IN');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

}
