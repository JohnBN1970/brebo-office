<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\Core\Database\Connection;

/** Resolves a management action to its underlying operational source data. */
final class ManagementActionSourceResolver {

  public function __construct(private readonly Connection $database) {}

  /** @return array<string, mixed> */
  public function resolve(int $actionId): array {
    $action = $this->database->select('brebo_management_action', 'a')->fields('a')->condition('id', $actionId)->execute()->fetchAssoc();
    if (!$action) {
      throw new \InvalidArgumentException('Onbekende managementactie.');
    }

    $context = json_decode((string) ($action['context_json'] ?? '{}'), TRUE) ?: [];
    $sourceType = (string) ($action['source_type'] ?? '');
    $items = match ($sourceType) {
      'controller_case' => $this->controllerCases(),
      'payment_control' => $this->blockedInvoices(),
      'contract_obligation' => $this->overdueObligations(),
      'supplier_risk' => array_values((array) ($context['supplier_risk'] ?? [])),
      'portfolio_risk' => [(array) ($context['portfolio'] ?? [])],
      default => [],
    };

    return [
      'action' => $action,
      'source_type' => $sourceType,
      'items' => $items,
      'context' => $context,
    ];
  }

  /** @return array<int, array<string, mixed>> */
  private function controllerCases(): array {
    if (!$this->database->schema()->tableExists('brebo_controller_case')) { return []; }
    return $this->database->select('brebo_controller_case', 'c')->fields('c')
      ->condition('status', 'concluded', '<>')->condition('severity', ['high', 'critical'], 'IN')
      ->orderBy('deadline_at', 'ASC')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @return array<int, array<string, mixed>> */
  private function blockedInvoices(): array {
    if (!$this->database->schema()->tableExists('brebo_supplier_invoice')) { return []; }
    $query = $this->database->select('brebo_supplier_invoice', 'i')->fields('i');
    $or = $query->orConditionGroup()->condition('approval_status', 'approved', '<>')->condition('match_status', 'matched', '<>');
    $query->condition($or);
    return $query->orderBy('id', 'DESC')->range(0, 100)->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @return array<int, array<string, mixed>> */
  private function overdueObligations(): array {
    if (!$this->database->schema()->tableExists('brebo_contract_obligation')) { return []; }
    return $this->database->select('brebo_contract_obligation', 'o')->fields('o')
      ->condition('status', 'completed', '<>')->condition('due_at', 0, '>')->condition('due_at', time(), '<')
      ->orderBy('due_at', 'ASC')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }
}
