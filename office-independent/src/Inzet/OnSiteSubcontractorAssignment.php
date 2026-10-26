<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Validates that an approved hour session is covered by a subcontractor
 * assignment and its agreed hourly rate. No employee payroll assumptions.
 */
final class OnSiteSubcontractorAssignment {
  /**
   * @param array<string,mixed> $assignment
   * @param array<string,mixed> $session
   * @return array{eligible:bool,reason:string,rate_cents_per_hour:int}
   */
  public function evaluate(array $assignment, array $session): array {
    $rate = $assignment['rate_cents_per_hour'] ?? null;
    if (!is_int($rate) || $rate < 0 || !is_int($assignment['project_id'] ?? null)
      || !is_int($assignment['person_id'] ?? null)) {
      return ['eligible'=>false,'reason'=>'invalid_assignment','rate_cents_per_hour'=>0];
    }
    if (($assignment['status'] ?? null) !== 'active') {
      return ['eligible'=>false,'reason'=>'assignment_inactive','rate_cents_per_hour'=>0];
    }
    if (($session['project_id'] ?? null) !== $assignment['project_id']
      || ($session['person_id'] ?? null) !== $assignment['person_id']) {
      return ['eligible'=>false,'reason'=>'assignment_mismatch','rate_cents_per_hour'=>0];
    }
    try {
      $start = $this->timestamp((string)($assignment['starts_at'] ?? ''));
      $end = $this->timestamp((string)($assignment['ends_at'] ?? ''));
      $in = $this->timestamp((string)($session['started_at'] ?? ''));
      $out = $this->timestamp((string)($session['ended_at'] ?? ''));
    } catch (\InvalidArgumentException $e) {
      return ['eligible'=>false,'reason'=>'invalid_assignment_dates','rate_cents_per_hour'=>0];
    }
    if ($start >= $end || $in >= $out || $in < $start || $out > $end) {
      return ['eligible'=>false,'reason'=>'outside_assignment_period','rate_cents_per_hour'=>0];
    }
    return ['eligible'=>true,'reason'=>'assignment_matches','rate_cents_per_hour'=>$rate];
  }

  private function timestamp(string $value): int {
    if (!preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})$/', $value)) {
      throw new \InvalidArgumentException('Tijdzone ontbreekt.');
    }
    try {
      return (new \DateTimeImmutable($value))->getTimestamp();
    } catch (\Exception $e) {
      throw new \InvalidArgumentException('Ongeldig tijdstip.', 0, $e);
    }
  }
}
