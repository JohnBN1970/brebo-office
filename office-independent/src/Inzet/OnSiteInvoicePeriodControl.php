<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Keeps invoice matching within a specified UTC billing period.
 * Cross-boundary shifts must be split/reviewed before matching.
 */
final class OnSiteInvoicePeriodControl {
  /**
   * @param list<array{started_at:string,ended_at:string,eligible:bool}> $sessions
   * @return array{eligible:bool,reason:string}
   */
  public function evaluate(array $sessions, string $periodStart, string $periodEnd): array {
    $start = $this->instant($periodStart);
    $end = $this->instant($periodEnd);
    if ($end <= $start) {
      throw new \InvalidArgumentException('Factuurperiode is ongeldig.');
    }
    if ($sessions === []) {
      return ['eligible'=>false,'reason'=>'no_sessions'];
    }
    foreach ($sessions as $session) {
      if (($session['eligible'] ?? false) !== true) {
        return ['eligible'=>false,'reason'=>'hours_not_approved'];
      }
      $in = $this->instant($session['started_at'] ?? '');
      $out = $this->instant($session['ended_at'] ?? '');
      if ($out <= $in) {
        return ['eligible'=>false,'reason'=>'invalid_session'];
      }
      if ($in < $start || $out > $end) {
        return ['eligible'=>false,'reason'=>'session_outside_period'];
      }
    }
    return ['eligible'=>true,'reason'=>'period_matches'];
  }

  private function instant(string $value): int {
    if (!preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})$/', $value)) {
      throw new \InvalidArgumentException('Tijdstip vereist tijdzone.');
    }
    try {
      return (new \DateTimeImmutable($value))->getTimestamp();
    } catch (\Exception $e) {
      throw new \InvalidArgumentException('Ongeldig tijdstip.', 0, $e);
    }
  }
}
