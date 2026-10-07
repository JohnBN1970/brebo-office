<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\ManagementControlCenterReadRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for management control center reads. */
final class DatabaseManagementControlCenterReadRepository implements ManagementControlCenterReadRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function blockedPaymentValue(): float {
    if (!$this->database->schema()->tableExists('brebo_supplier_invoice')) {
      return 0.0;
    }

    $query = $this->database->select('brebo_supplier_invoice', 'i');
    $query->addExpression('COALESCE(SUM(gross_amount),0)', 'amount');
    $or = $query->orConditionGroup()
      ->condition('approval_status', 'approved', '<>')
      ->condition('match_status', 'matched', '<>');
    $query->condition($or);

    return (float) $query->execute()->fetchField();
  }

  public function overdueObligationCount(int $now): int {
    if (!$this->database->schema()->tableExists('brebo_contract_obligation')) {
      return 0;
    }

    return (int) $this->database
      ->select('brebo_contract_obligation', 'o')
      ->condition('status', 'completed', '<>')
      ->condition('due_at', 0, '>')
      ->condition('due_at', $now, '<')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  public function criticalControllerCaseSummary(): array {
    if (!$this->database->schema()->tableExists('brebo_controller_case')) {
      return ['case_count' => 0, 'exposure' => 0.0];
    }

    $query = $this->database->select('brebo_controller_case', 'c');
    $query->addExpression('COUNT(*)', 'case_count');
    $query->addExpression('COALESCE(SUM(financial_exposure),0)', 'exposure');
    $query->condition('status', 'concluded', '<>');
    $query->condition('severity', ['high', 'critical'], 'IN');
    $row = $query->execute()->fetchAssoc() ?: [];

    return [
      'case_count' => (int) ($row['case_count'] ?? 0),
      'exposure' => (float) ($row['exposure'] ?? 0),
    ];
  }

}
