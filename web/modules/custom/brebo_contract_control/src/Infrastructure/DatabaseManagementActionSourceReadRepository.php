<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\ManagementActionSourceReadRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for management-action operational source reads. */
final class DatabaseManagementActionSourceReadRepository implements ManagementActionSourceReadRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  /** @return array<string, mixed>|null */
  public function findAction(int $actionId): ?array {
    $row = $this->database->select('brebo_management_action', 'a')
      ->fields('a')
      ->condition('id', $actionId)
      ->execute()
      ->fetchAssoc();

    return $row ?: NULL;
  }

  /** @return array<int, array<string, mixed>> */
  public function findOpenCriticalControllerCases(): array {
    if (!$this->database->schema()->tableExists('brebo_controller_case')) {
      return [];
    }

    return $this->database->select('brebo_controller_case', 'c')
      ->fields('c')
      ->condition('status', 'concluded', '<>')
      ->condition('severity', ['high', 'critical'], 'IN')
      ->orderBy('deadline_at', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @return array<int, array<string, mixed>> */
  public function findBlockedInvoices(): array {
    if (!$this->database->schema()->tableExists('brebo_supplier_invoice')) {
      return [];
    }

    $query = $this->database->select('brebo_supplier_invoice', 'i')->fields('i');
    $or = $query->orConditionGroup()
      ->condition('approval_status', 'approved', '<>')
      ->condition('match_status', 'matched', '<>');
    $query->condition($or);

    return $query->orderBy('id', 'DESC')
      ->range(0, 100)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @return array<int, array<string, mixed>> */
  public function findOverdueObligations(int $now): array {
    if (!$this->database->schema()->tableExists('brebo_contract_obligation')) {
      return [];
    }

    return $this->database->select('brebo_contract_obligation', 'o')
      ->fields('o')
      ->condition('status', 'completed', '<>')
      ->condition('due_at', 0, '>')
      ->condition('due_at', $now, '<')
      ->orderBy('due_at', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

}
