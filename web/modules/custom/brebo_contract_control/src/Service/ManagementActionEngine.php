<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\Core\Database\Connection;

/** Converts management-control signals into owned, deadline-driven actions. */
final class ManagementActionEngine {

  public function __construct(
    private readonly Connection $database,
    private readonly ManagementControlCenterService $controlCenter,
  ) {}

  /** @return array<string, mixed> */
  public function synchronize(int $defaultOwnerUid, ?int $now = NULL): array {
    $now ??= time();
    $dashboard = $this->controlCenter->dashboard($now);
    $headline = (array) ($dashboard['headline'] ?? []);
    $created = [];
    $rules = [
      ['key' => 'critical_cases', 'trigger' => (int) ($headline['critical_controller_cases'] ?? 0) > 0, 'severity' => 'critical', 'title' => 'Behandel kritieke controllerdossiers', 'due' => 86400, 'source' => 'controller_case'],
      ['key' => 'blocked_payments', 'trigger' => (float) ($headline['blocked_payment_value'] ?? 0) >= 100000, 'severity' => 'high', 'title' => 'Onderzoek geblokkeerde betalingen boven EUR 100.000', 'due' => 2 * 86400, 'source' => 'payment_control'],
      ['key' => 'portfolio_risk', 'trigger' => (int) ($headline['portfolio_risk_score'] ?? 0) >= 75, 'severity' => 'critical', 'title' => 'Voer verplichte portefeuille-projectreview uit', 'due' => 86400, 'source' => 'portfolio_risk'],
      ['key' => 'supplier_risk', 'trigger' => (int) ($headline['suppliers_below_c_rating'] ?? 0) > 0, 'severity' => 'high', 'title' => 'Herbeoordeel leveranciers met verhoogd TCO-risico', 'due' => 3 * 86400, 'source' => 'supplier_risk'],
      ['key' => 'overdue_obligations', 'trigger' => (int) ($headline['overdue_contract_obligations'] ?? 0) > 0, 'severity' => 'medium', 'title' => 'Los verlopen contractverplichtingen op', 'due' => 3 * 86400, 'source' => 'contract_obligation'],
    ];
    foreach ($rules as $rule) {
      if (!$rule['trigger'] || $this->hasOpenAction($rule['key'])) { continue; }
      $id = (int) $this->database->insert('brebo_management_action')->fields([
        'action_key' => $rule['key'], 'source_type' => $rule['source'], 'severity' => $rule['severity'], 'title' => $rule['title'], 'owner_uid' => $defaultOwnerUid,
        'status' => 'open', 'due_at' => $now + $rule['due'], 'context_json' => json_encode($dashboard, JSON_THROW_ON_ERROR), 'created_at' => $now,
      ])->execute();
      $created[] = $id;
    }
    return ['created_action_ids' => $created, 'created_count' => count($created), 'management_status' => $dashboard['management_status'] ?? 'onder_controle'];
  }

  public function resolve(int $actionId, int $resolvedBy, string $resolution, ?int $now = NULL): void {
    if (trim($resolution) === '') { throw new \InvalidArgumentException('Een managementactie kan niet zonder inhoudelijke oplossing worden gesloten.'); }
    $this->database->update('brebo_management_action')->fields(['status' => 'resolved', 'resolved_by' => $resolvedBy, 'resolution' => $resolution, 'resolved_at' => $now ?? time()])->condition('id', $actionId)->condition('status', 'open')->execute();
  }

  /** @return array<int, array<string, mixed>> */
  public function openActions(?string $actionKey = NULL): array {
    $query = $this->database->select('brebo_management_action', 'a')->fields('a')->condition('status', ['open', 'reopened'], 'IN')->orderBy('due_at', 'ASC');
    if ($actionKey !== NULL && $actionKey !== '') { $query->condition('action_key', $actionKey); }
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @return array<int, array<string, mixed>> */
  public function escalateOverdue(?int $now = NULL): array {
    $now ??= time();
    $rows = $this->database->select('brebo_management_action', 'a')->fields('a')->condition('status', ['open', 'reopened'], 'IN')->condition('due_at', $now, '<')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as &$row) { $row['escalation'] = in_array($row['severity'], ['critical', 'high'], TRUE) ? 'directie' : 'management'; }
    unset($row);
    return $rows;
  }

  private function hasOpenAction(string $key): bool {
    return (bool) $this->database->select('brebo_management_action', 'a')->condition('action_key', $key)->condition('status', ['open', 'reopened'], 'IN')->countQuery()->execute()->fetchField();
  }
}
