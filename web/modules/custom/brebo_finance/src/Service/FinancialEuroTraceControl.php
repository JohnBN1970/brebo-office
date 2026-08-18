<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

/** Detects source-backed financial anomalies in one euro trace. */
final class FinancialEuroTraceControl {
  public function __construct(private readonly FinancialEuroTrace $trace) {}

  /** @return array<string, mixed> */
  public function assess(string $entityType, int $entityId): array {
    $trace = $this->trace->trace($entityType, $entityId);
    $committed = $this->sum($trace['commitment_lines'] ?? [], ['line_amount_ex_vat', 'amount_ex_vat']);
    $performed = $this->sum($trace['performance_receipts'] ?? [], ['accepted_amount_ex_vat', 'amount_ex_vat', 'verified_amount_ex_vat']);
    $invoiced = $this->sum($trace['invoice_lines'] ?? [], ['line_amount_ex_vat', 'amount_ex_vat']);
    $released = $this->sum($trace['payment_releases'] ?? [], ['payment_amount']);
    $executed = $this->sum(array_values(array_filter($trace['payment_releases'] ?? [], static fn(array $r): bool => ($r['status'] ?? '') === 'executed')), ['payment_amount']);

    $findings = [];
    if ($committed > 0 && $invoiced > $committed + 0.01) $findings[] = $this->finding('invoice_above_commitment', 'critical', $invoiced - $committed, 'Gefactureerd bedrag is hoger dan de aantoonbare inkoopverplichting.', 'Blokkeer betaalvrijgave en controleer order, mutatie en factuurregels.');
    if ($performed > 0 && $invoiced > $performed + 0.01) $findings[] = $this->finding('invoice_above_performance', 'critical', $invoiced - $performed, 'Gefactureerd bedrag is hoger dan de aantoonbaar geverifieerde prestatie.', 'Blokkeer betaling totdat de ontbrekende prestatie is aangetoond of de factuur is gecorrigeerd.');
    if ($performed <= 0 && $invoiced > 0) $findings[] = $this->finding('invoice_without_verified_performance', 'critical', $invoiced, 'Er is een factuur maar geen aantoonbaar geverifieerde prestatie in de trace.', 'Geen betaling vrijgeven zonder prestatiebewijs.');
    if ($released > $invoiced + 0.01) $findings[] = $this->finding('release_above_invoice', 'critical', $released - $invoiced, 'Voor betaling vrijgegeven bedrag is hoger dan het gekoppelde factuurbedrag.', 'Blokkeer de vrijgave en controleer de betaalopdracht.');
    if ($performed > 0 && $released > $performed + 0.01) $findings[] = $this->finding('release_above_performance', 'critical', $released - $performed, 'Voor betaling vrijgegeven bedrag is hoger dan de geverifieerde prestatie.', 'Blokkeer de vrijgave totdat prestatie en factuur aantoonbaar aansluiten.');
    if ($executed > $released + 0.01) $findings[] = $this->finding('executed_above_release', 'critical', $executed - $released, 'Uitgevoerde betaling is hoger dan aantoonbaar is vrijgegeven.', 'Escaleren naar finance/directie en bankmutatie direct controleren.');
    if (!($trace['trace_complete'] ?? false)) $findings[] = $this->finding('incomplete_trace', 'warning', 0.0, 'De financiële keten is niet volledig aantoonbaar.', 'Herstel de ontbrekende bronkoppelingen: ' . implode(', ', $trace['missing_links'] ?? []) . '.');

    usort($findings, static fn(array $a, array $b): int => ($b['severity_score'] <=> $a['severity_score']) ?: ($b['exposure_amount'] <=> $a['exposure_amount']));
    $exposure = array_sum(array_map(static fn(array $f): float => (float) $f['exposure_amount'], $findings));
    return [
      'project_nid' => $trace['project_nid'], 'root' => $trace['root'],
      'totals' => ['committed_ex_vat' => $committed, 'verified_performance_ex_vat' => $performed, 'invoiced_ex_vat' => $invoiced, 'released_for_payment' => $released, 'executed_payment' => $executed],
      'control' => ['status' => $findings === [] ? 'clear' : (array_filter($findings, static fn(array $f): bool => $f['severity'] === 'critical') ? 'blocked' : 'attention'), 'finding_count' => count($findings), 'exposure_amount' => number_format($exposure, 2, '.', '')],
      'findings' => $findings, 'trace' => $trace,
      'basis' => 'Deterministic control based only on registered financial source records; no missing amount is estimated.',
    ];
  }

  private function sum(array $rows, array $fields): float {
    $sum = 0.0;
    foreach ($rows as $row) {
      foreach ($fields as $field) {
        if (array_key_exists($field, $row) && $row[$field] !== null && $row[$field] !== '') { $sum += (float) $row[$field]; break; }
      }
    }
    return round($sum, 2);
  }

  /** @return array<string, mixed> */
  private function finding(string $code, string $severity, float $exposure, string $message, string $measure): array {
    return ['code' => $code, 'severity' => $severity, 'severity_score' => $severity === 'critical' ? 100 : 40, 'exposure_amount' => round(max(0, $exposure), 2), 'message' => $message, 'control_measure' => $measure];
  }
}
