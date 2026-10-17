<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Pure subcontractor invoice matching calculation. Amounts are integer cents,
 * never floating-point currency. This is a review proposal, not payment approval.
 */
final class OnSiteSubcontractorInvoiceMatch {
  /**
   * @param list<array{project_id:int,person_id:int,eligible:bool,seconds:int}> $hours
   * @return array{matched:bool,reason:string,approved_seconds:int,expected_cents:int,difference_cents:int}
   */
  public function compare(
    array $hours,
    int $projectId,
    int $personId,
    int $rateCentsPerHour,
    int $invoicedCents,
    int $toleranceCents = 0
  ): array {
    if ($projectId < 1 || $personId < 1 || $rateCentsPerHour < 0
      || $invoicedCents < 0 || $toleranceCents < 0) {
      throw new \InvalidArgumentException('Ongeldige opdracht- of factuurgegevens.');
    }
    $seconds = 0;
    foreach ($hours as $entry) {
      if (($entry['project_id'] ?? null) !== $projectId
        || ($entry['person_id'] ?? null) !== $personId) {
        throw new \DomainException('Uren vallen buiten de opdracht of medewerker.');
      }
      if (($entry['eligible'] ?? false) !== true
        || !isset($entry['seconds']) || !is_int($entry['seconds'])
        || $entry['seconds'] < 0 || $entry['seconds'] > 86400) {
        return ['matched'=>false,'reason'=>'hours_not_approved','approved_seconds'=>0,
          'expected_cents'=>0,'difference_cents'=>0];
      }
      $seconds += $entry['seconds'];
    }
    if ($hours === []) {
      return ['matched'=>false,'reason'=>'no_approved_hours','approved_seconds'=>0,
        'expected_cents'=>0,'difference_cents'=>0];
    }
    // Nearest cent, integer arithmetic; guard against overflow.
    if ($rateCentsPerHour > 0 && $seconds > intdiv(PHP_INT_MAX - 1800, $rateCentsPerHour)) {
      throw new \OverflowException('Urenbedrag te groot.');
    }
    $expected = intdiv($seconds * $rateCentsPerHour + 1800, 3600);
    $difference = $invoicedCents - $expected;
    return ['matched'=>abs($difference) <= $toleranceCents,
      'reason'=>abs($difference) <= $toleranceCents ? 'amount_matches' : 'amount_difference',
      'approved_seconds'=>$seconds,'expected_cents'=>$expected,'difference_cents'=>$difference];
  }
}
