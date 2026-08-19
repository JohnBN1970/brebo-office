<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\Core\Database\Connection;

/** Verifies whether resolved management actions actually fixed the underlying risk. */
final class ClosedLoopControlService {

  public function __construct(
    private readonly Connection $database,
    private readonly ManagementControlCenterService $controlCenter,
  ) {}

  /** @return array<string, mixed> */
  public function verifyResolved(?int $now = NULL, int $verificationDelayDays = 30): array {
    $now ??= time();
    $cutoff = $now - max(1, $verificationDelayDays) * 86400;
    $dashboard = $this->controlCenter->dashboard($now);
    $headline = (array) ($dashboard['headline'] ?? []);

    $rows = $this->database->select('brebo_management_action', 'a')->fields('a')
      ->condition('status', 'resolved')
      ->condition('resolved_at', 0, '>')
      ->condition('resolved_at', $cutoff, '<=')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $verified = 0;
    $reopened = 0;
    $results = [];
    foreach ($rows as $row) {
      $isResolved = $this->signalIsResolved((string) $row['action_key'], $headline);
      if ($isResolved) {
        $this->database->update('brebo_management_action')->fields([
          'status' => 'verified_closed',
          'verified_at' => $now,
          'verification_result' => 'Onderliggend controlsignaal bleef weg tijdens de verificatieperiode.',
        ])->condition('id', (int) $row['id'])->execute();
        $verified++;
        $results[] = ['action_id' => (int) $row['id'], 'result' => 'verified_closed'];
      }
      else {
        $this->database->update('brebo_management_action')->fields([
          'status' => 'reopened',
          'due_at' => $now + $this->reopenDueSeconds((string) $row['severity']),
          'verified_at' => $now,
          'verification_result' => 'Onderliggend controlsignaal is teruggekeerd of onvoldoende opgelost.',
        ])->condition('id', (int) $row['id'])->execute();
        $reopened++;
        $results[] = ['action_id' => (int) $row['id'], 'result' => 'reopened'];
      }
    }

    return [
      'checked' => count($rows),
      'verified_closed' => $verified,
      'reopened' => $reopened,
      'verification_delay_days' => $verificationDelayDays,
      'results' => $results,
    ];
  }

  /** @param array<string, mixed> $headline */
  private function signalIsResolved(string $key, array $headline): bool {
    return match ($key) {
      'critical_cases' => (int) ($headline['critical_controller_cases'] ?? 0) === 0,
      'blocked_payments' => (float) ($headline['blocked_payment_value'] ?? 0) < 100000,
      'portfolio_risk' => (int) ($headline['portfolio_risk_score'] ?? 0) < 75,
      'supplier_risk' => (int) ($headline['suppliers_below_c_rating'] ?? 0) === 0,
      'overdue_obligations' => (int) ($headline['overdue_contract_obligations'] ?? 0) === 0,
      default => FALSE,
    };
  }

  private function reopenDueSeconds(string $severity): int {
    return match ($severity) {
      'critical' => 86400,
      'high' => 2 * 86400,
      'medium' => 3 * 86400,
      default => 7 * 86400,
    };
  }
}
