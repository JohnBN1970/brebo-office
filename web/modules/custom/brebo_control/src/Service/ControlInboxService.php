<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Service;

use Drupal\Core\Database\Connection;

/**
 * Builds role-aware personal and management control inboxes.
 */
final class ControlInboxService {

  public function __construct(private readonly Connection $database) {}

  /**
   * @param string[] $roleLabels
   * @return array<string, mixed>
   */
  public function personal(array $roleLabels, int $now): array {
    $roles = array_values(array_unique(array_filter(array_map('trim', $roleLabels))));
    $rows = $this->activeRows();
    $items = [];

    foreach ($rows as $row) {
      $ownerRoles = preg_split('/\s*\/\s*/', (string) $row['owner_role']) ?: [];
      $ownerRoles = array_values(array_filter(array_map('trim', $ownerRoles)));
      $isOwner = (bool) array_intersect($roles, $ownerRoles);
      $isEscalatedRecipient = $this->matchesEscalation($roles, (int) $row['escalation_level']);
      if (!$isOwner && !$isEscalatedRecipient) {
        continue;
      }
      $items[] = $this->decorate($row, $now, $isOwner ? 'eigenaar' : 'escalatie');
    }

    usort($items, [$this, 'sortItems']);
    return $this->summary($items);
  }

  /**
   * @return array<string, mixed>
   */
  public function management(int $now): array {
    $items = [];
    foreach ($this->activeRows() as $row) {
      if ((int) $row['escalation_level'] < 2 && (int) $row['risk_points'] < 20 && (string) $row['urgency'] !== 'kritiek') {
        continue;
      }
      $items[] = $this->decorate($row, $now, 'management');
    }
    usort($items, [$this, 'sortItems']);
    return $this->summary($items);
  }

  /** @return array<int, array<string, mixed>> */
  private function activeRows(): array {
    if (!$this->database->schema()->tableExists('brebo_control_action')) {
      return [];
    }
    return $this->database->select('brebo_control_action', 'a')->fields('a')
      ->condition('status', ['open', 'reopened', 'in_progress', 'escalated'], 'IN')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @param string[] $roles */
  private function matchesEscalation(array $roles, int $level): bool {
    if ($level >= 3 && in_array('Directie', $roles, TRUE)) {
      return TRUE;
    }
    if ($level >= 2 && in_array('Controller', $roles, TRUE)) {
      return TRUE;
    }
    return $level >= 1 && in_array('Projectleider', $roles, TRUE);
  }

  /** @param array<string, mixed> $row
   *  @return array<string, mixed>
   */
  private function decorate(array $row, int $now, string $reason): array {
    $dueAt = (int) ($row['due_at'] ?? 0);
    $overdueSeconds = $dueAt > 0 && $now > $dueAt ? $now - $dueAt : 0;
    return $row + [
      'inbox_reason' => $reason,
      'is_overdue' => $overdueSeconds > 0,
      'overdue_hours' => round($overdueSeconds / 3600, 1),
      'due_in_hours' => $dueAt > $now ? round(($dueAt - $now) / 3600, 1) : 0.0,
    ];
  }

  /** @param array<string, mixed>[] $items
   *  @return array<string, mixed>
   */
  private function summary(array $items): array {
    $overdue = 0;
    $critical = 0;
    $escalated = 0;
    foreach ($items as $item) {
      $overdue += !empty($item['is_overdue']) ? 1 : 0;
      $critical += ((string) ($item['urgency'] ?? '') === 'kritiek' || (int) ($item['risk_points'] ?? 0) >= 20) ? 1 : 0;
      $escalated += (int) ($item['escalation_level'] ?? 0) > 0 ? 1 : 0;
    }
    return [
      'total' => count($items),
      'overdue' => $overdue,
      'critical' => $critical,
      'escalated' => $escalated,
      'items' => $items,
    ];
  }

  /** @param array<string, mixed> $a
   *  @param array<string, mixed> $b
   */
  private function sortItems(array $a, array $b): int {
    $rank = ['kritiek' => 4, 'vandaag' => 3, 'deze_week' => 2, 'monitoren' => 1];
    $aRank = $rank[(string) ($a['urgency'] ?? '')] ?? 0;
    $bRank = $rank[(string) ($b['urgency'] ?? '')] ?? 0;
    if ($aRank !== $bRank) {
      return $bRank <=> $aRank;
    }
    $risk = (int) ($b['risk_points'] ?? 0) <=> (int) ($a['risk_points'] ?? 0);
    if ($risk !== 0) {
      return $risk;
    }
    return (int) ($a['due_at'] ?? PHP_INT_MAX) <=> (int) ($b['due_at'] ?? PHP_INT_MAX);
  }

}
