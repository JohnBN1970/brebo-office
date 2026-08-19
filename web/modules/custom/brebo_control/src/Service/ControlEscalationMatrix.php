<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Service;

/**
 * Determines who must be involved when a controller action escalates.
 */
final class ControlEscalationMatrix {

  /**
   * @param array<string, mixed> $action
   * @return array<string, mixed>
   */
  public function determine(array $action, int $now): array {
    $points = (int) ($action['risk_points'] ?? 0);
    $value = abs((float) ($action['source_value'] ?? 0));
    $dueAt = (int) ($action['due_at'] ?? 0);
    $ageHours = $dueAt > 0 && $now > $dueAt ? ($now - $dueAt) / 3600 : 0.0;
    $code = (string) ($action['driver_code'] ?? '');

    $severity = 0;
    if ($points >= 20) {
      $severity += 2;
    }
    elseif ($points >= 10) {
      $severity += 1;
    }
    if ($value >= 50000) {
      $severity += 2;
    }
    elseif ($value >= 10000) {
      $severity += 1;
    }
    if ($ageHours >= 72) {
      $severity += 2;
    }
    elseif ($ageHours >= 24) {
      $severity += 1;
    }
    if (in_array($code, ['expected_loss', 'margin_leakage'], TRUE)) {
      $severity += 2;
    }

    $level = match (TRUE) {
      $severity >= 5 => 3,
      $severity >= 3 => 2,
      default => 1,
    };

    $recipients = match ($level) {
      3 => ['Projectleider', 'Controller', 'Directie'],
      2 => ['Projectleider', 'Controller'],
      default => [$this->primaryRole((string) ($action['owner_role'] ?? 'Projectleider')), 'Projectleider'],
    };
    $recipients = array_values(array_unique($recipients));

    return [
      'level' => $level,
      'recipients' => $recipients,
      'reason' => $this->reason($points, $value, $ageHours, $code),
      'age_hours' => round($ageHours, 1),
      'financial_exposure' => round($value, 2),
    ];
  }

  private function primaryRole(string $owner): string {
    $parts = preg_split('/\s*\/\s*/', $owner) ?: [];
    return trim((string) ($parts[0] ?? 'Projectleider'));
  }

  private function reason(int $points, float $value, float $ageHours, string $code): string {
    $parts = ['risicoscore ' . $points];
    if ($value > 0) {
      $parts[] = 'financiële blootstelling € ' . number_format($value, 2, ',', '.');
    }
    if ($ageHours > 0) {
      $parts[] = number_format($ageHours, 1, ',', '.') . ' uur over deadline';
    }
    if (in_array($code, ['expected_loss', 'margin_leakage'], TRUE)) {
      $parts[] = 'direct resultaat-/margerisico';
    }
    return implode('; ', $parts) . '.';
  }

}
