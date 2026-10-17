<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Reconciles supplier-invoiced minutes against BREBO-approved OnSite minutes.
 * A supplier is the invoice counterparty; workers are only evidence rows.
 * No automatic invoice approval or payment decision is made.
 */
final class OnSiteSupplierHoursReconciliation {
  /**
   * @param list<array{person_id:int,approved_minutes:int}> $approved
   * @param list<array{person_id:int,invoiced_minutes:int}> $invoiced
   * @return array{status:string,approved_minutes:int,invoiced_minutes:int,difference_minutes:int,rows:list<array{person_id:int,approved_minutes:int,invoiced_minutes:int,difference_minutes:int}>}
   */
  public function compare(array $approved, array $invoiced): array {
    $people = [];
    foreach ($approved as $row) {
      $id = $row['person_id'] ?? null;
      $minutes = $row['approved_minutes'] ?? null;
      if (!is_int($id) || $id < 1 || !is_int($minutes) || $minutes < 0) {
        throw new \InvalidArgumentException('Ongeldige goedgekeurde urenregel.');
      }
      $people[$id] ??= ['person_id'=>$id,'approved_minutes'=>0,'invoiced_minutes'=>0];
      $people[$id]['approved_minutes'] += $minutes;
    }
    foreach ($invoiced as $row) {
      $id = $row['person_id'] ?? null;
      $minutes = $row['invoiced_minutes'] ?? null;
      if (!is_int($id) || $id < 1 || !is_int($minutes) || $minutes < 0) {
        throw new \InvalidArgumentException('Ongeldige factuururenregel.');
      }
      $people[$id] ??= ['person_id'=>$id,'approved_minutes'=>0,'invoiced_minutes'=>0];
      $people[$id]['invoiced_minutes'] += $minutes;
    }
    ksort($people, SORT_NUMERIC);
    $rows = [];
    $approvedTotal = 0;
    $invoicedTotal = 0;
    foreach ($people as $person) {
      $person['difference_minutes'] = $person['invoiced_minutes'] - $person['approved_minutes'];
      $approvedTotal += $person['approved_minutes'];
      $invoicedTotal += $person['invoiced_minutes'];
      $rows[] = $person;
    }
    $hasDifference = array_filter($rows, static fn(array $row): bool => $row['difference_minutes'] !== 0) !== [];
    return [
      'status'=>$rows === [] ? 'no_evidence' : ($hasDifference ? 'requires_review' : 'hours_match'),
      'approved_minutes'=>$approvedTotal,
      'invoiced_minutes'=>$invoicedTotal,
      'difference_minutes'=>$invoicedTotal - $approvedTotal,
      'rows'=>$rows,
    ];
  }
}
