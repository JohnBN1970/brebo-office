<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\ProjectTransitionFinanceRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseProjectTransitionFinanceRepository implements ProjectTransitionFinanceRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function openAdministration(int $projectId): array {
    return [
      'commitments' => (int) $this->database->select('brebo_finance_commitment', 'c')->condition('project_nid', $projectId)->condition('status', ['cancelled', 'closed'], 'NOT IN')->countQuery()->execute()->fetchField(),
      'purchase_invoices' => (int) $this->database->select('brebo_finance_purchase_invoice', 'i')->condition('project_nid', $projectId)->condition('status', ['paid', 'cancelled'], 'NOT IN')->countQuery()->execute()->fetchField(),
      'billing_instalments' => (int) $this->database->select('brebo_finance_billing_instalment', 'b')->condition('project_nid', $projectId)->condition('status', ['paid', 'cancelled'], 'NOT IN')->countQuery()->execute()->fetchField(),
    ];
  }

  public function appendAudit(array $fields): void {
    $this->database->insert('brebo_finance_audit')->fields($fields)->execute();
  }

}
