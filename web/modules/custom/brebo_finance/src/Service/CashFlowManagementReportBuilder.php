<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use DateInterval;
use DateTimeImmutable;
use Drupal\brebo_finance\Contract\CashFlowManagementRepositoryInterface;

/** Builds an organisation-wide cashflow and receivables management view. */
final class CashFlowManagementReportBuilder {

  public function __construct(private readonly CashFlowManagementRepositoryInterface $repository) {}

  /** @return array<string,mixed> */
  public function build(?string $asOf = NULL): array {
    $today = new DateTimeImmutable($asOf ?? date('Y-m-d'));
    $end = $today->add(new DateInterval('P90D'));
    $sales = $this->salesSummary($today);
    $weeks = $this->weeks($today, $end);

    $forecastIncoming = 0.0;
    $forecastOutgoing = 0.0;
    $lowestNetWeek = NULL;
    foreach ($weeks as $week) {
      $forecastIncoming += (float) $week['incoming'];
      $forecastOutgoing += (float) $week['outgoing'];
      if ($lowestNetWeek === NULL || (float) $week['net'] < (float) $lowestNetWeek['net']) $lowestNetWeek = $week;
    }

    $signals = [];
    if ($sales['overdue'] > 0) $signals[] = sprintf('€ %s is vervallen en vraagt debiteurenopvolging.', number_format($sales['overdue'], 2, ',', '.'));
    if ($sales['disputed'] > 0) $signals[] = sprintf('€ %s staat geblokkeerd door geschil.', number_format($sales['disputed'], 2, ',', '.'));
    if ($lowestNetWeek !== NULL && (float) $lowestNetWeek['net'] < 0) $signals[] = sprintf('Week %d heeft een negatieve verwachte kasbeweging van € %s.', $lowestNetWeek['week'], number_format(abs((float) $lowestNetWeek['net']), 2, ',', '.'));
    if ($signals === []) $signals[] = 'Geen directe cashflow- of debiteurensignalen op basis van de huidige brondata.';

    return [
      'as_of' => $today->format('Y-m-d'),
      'sales' => $sales,
      'overdue_invoices' => $this->overdueInvoices($today),
      'projects' => $this->projectReceivables($today),
      'forecast' => [
        'incoming_13w' => round($forecastIncoming, 2),
        'outgoing_13w' => round($forecastOutgoing, 2),
        'net_13w' => round($forecastIncoming - $forecastOutgoing, 2),
        'weeks' => $weeks,
      ],
      'signals' => $signals,
      'note' => 'Verkoopfacturen zijn leidend voor debiteuren; brongebonden cash-events zijn leidend voor de 13-weeks kasbeweging.',
    ];
  }

  /** @return array<string,float|int> */
  private function salesSummary(DateTimeImmutable $today): array {
    $result = ['invoiced' => 0.0, 'received' => 0.0, 'outstanding' => 0.0, 'overdue' => 0.0, 'disputed' => 0.0, 'open_count' => 0];
    $rows = $this->repository->salesInvoices();
    foreach ($rows as $row) {
      $total = (float) $row['amount_inc_vat'];
      $paid = min($total, max(0.0, (float) $row['paid_amount_inc_vat']));
      $open = max(0.0, $total - $paid);
      $result['invoiced'] += $total;
      $result['received'] += $paid;
      $result['outstanding'] += $open;
      if ($open > 0) $result['open_count']++;
      $status = (string) $row['status'];
      if ($status === 'disputed') $result['disputed'] += $open;
      if ($open > 0 && (string) $row['due_date'] < $today->format('Y-m-d')) $result['overdue'] += $open;
    }
    foreach (['invoiced','received','outstanding','overdue','disputed'] as $key) $result[$key] = round((float) $result[$key], 2);
    return $result;
  }

  /** @return list<array<string,mixed>> */
  private function overdueInvoices(DateTimeImmutable $today): array {
    $rows = $this->repository->overdueSalesInvoices($today->format('Y-m-d'));
    $result = [];
    foreach ($rows as $row) {
      $total = (float) $row['amount_inc_vat'];
      $paid = min($total, max(0.0, (float) $row['paid_amount_inc_vat']));
      $open = max(0.0, $total - $paid);
      if ($open <= 0) continue;
      $result[] = [
        'id' => (int) $row['id'],
        'invoice_number' => (string) $row['invoice_number'],
        'project_nid' => (int) $row['project_nid'],
        'due_date' => (string) $row['due_date'],
        'days_overdue' => max(0, (int) $today->diff(new DateTimeImmutable((string) $row['due_date']))->days),
        'status' => (string) $row['status'],
        'outstanding' => round($open, 2),
      ];
    }
    usort($result, static fn(array $a, array $b): int => $b['outstanding'] <=> $a['outstanding']);
    return array_slice($result, 0, 20);
  }

  /** @return list<array<string,mixed>> */
  private function projectReceivables(DateTimeImmutable $today): array {
    $rows = $this->repository->salesInvoices();
    $projects = [];
    foreach ($rows as $row) {
      $project = (int) $row['project_nid'];
      $total = (float) $row['amount_inc_vat'];
      $paid = min($total, max(0.0, (float) $row['paid_amount_inc_vat']));
      $open = max(0.0, $total - $paid);
      if (!isset($projects[$project])) $projects[$project] = ['project_nid' => $project, 'invoiced' => 0.0, 'received' => 0.0, 'outstanding' => 0.0, 'overdue' => 0.0];
      $projects[$project]['invoiced'] += $total;
      $projects[$project]['received'] += $paid;
      $projects[$project]['outstanding'] += $open;
      if ($open > 0 && (string) $row['due_date'] < $today->format('Y-m-d')) $projects[$project]['overdue'] += $open;
    }
    foreach ($projects as &$project) foreach (['invoiced','received','outstanding','overdue'] as $key) $project[$key] = round((float) $project[$key], 2);
    unset($project);
    $result = array_values($projects);
    usort($result, static fn(array $a, array $b): int => $b['outstanding'] <=> $a['outstanding']);
    return $result;
  }

  /** @return list<array<string,mixed>> */
  private function weeks(DateTimeImmutable $start, DateTimeImmutable $end): array {
    $events = $this->repository->cashEventsUntil($end->format('Y-m-d'));

    $weeks = [];
    for ($week = 0; $week < 13; $week++) {
      $weekStart = $start->add(new DateInterval('P' . ($week * 7) . 'D'));
      $weekEnd = $weekStart->add(new DateInterval('P6D'));
      $incoming = $outgoing = $regularIncoming = $regularOutgoing = $gIncoming = $gOutgoing = 0.0;
      $count = 0;
      foreach ($events as $event) {
        $date = (string) $event['due_date'];
        $belongs = $week === 0 ? $date <= $weekEnd->format('Y-m-d') : ($date >= $weekStart->format('Y-m-d') && $date <= $weekEnd->format('Y-m-d'));
        if (!$belongs) continue;
        $amount = (float) $event['amount_inc_vat'];
        $count++;
        if ($event['direction'] === 'incoming') {
          $incoming += $amount;
          if ($event['account_bucket'] === 'g_account') $gIncoming += $amount; else $regularIncoming += $amount;
        }
        else {
          $outgoing += $amount;
          if ($event['account_bucket'] === 'g_account') $gOutgoing += $amount; else $regularOutgoing += $amount;
        }
      }
      $weeks[] = [
        'week' => $week + 1,
        'start_date' => $weekStart->format('Y-m-d'),
        'end_date' => $weekEnd->format('Y-m-d'),
        'incoming' => round($incoming, 2),
        'outgoing' => round($outgoing, 2),
        'net' => round($incoming - $outgoing, 2),
        'regular_incoming' => round($regularIncoming, 2),
        'regular_outgoing' => round($regularOutgoing, 2),
        'g_account_incoming' => round($gIncoming, 2),
        'g_account_outgoing' => round($gOutgoing, 2),
        'event_count' => $count,
      ];
    }
    return $weeks;
  }
}
