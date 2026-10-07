<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\brebo_contract_control\Contract\PaymentAnomalyReadRepositoryInterface;

/**
 * Detects longitudinal payment patterns that warrant financial review.
 *
 * Signals are investigative prompts only and must never be treated as proof
 * of fraud or misconduct without human review and supporting evidence.
 */
final class PaymentAnomalyIntelligenceService {

  public function __construct(private readonly PaymentAnomalyReadRepositoryInterface $repository) {}

  /** @return array<string, mixed> */
  public function analyze(?int $since = NULL): array {
    if (!$this->repository->hasSupplierInvoices()) {
      return ['score' => 0, 'level' => 'laag', 'signals' => [], 'patterns' => [], 'status' => 'no_data'];
    }

    $since ??= strtotime('-365 days') ?: 0;
    $patterns = [];
    $score = 0;

    foreach ($this->repository->findThresholdPatterns($since) as $pattern) {
      $points = min(20, 6 + ((int) $pattern['invoice_count'] * 2));
      $score += $points;
      $patterns[] = [
        'code' => 'threshold_clustering',
        'points' => $points,
        'supplier_name' => $pattern['supplier_name'],
        'value' => (float) $pattern['total_amount'],
        'message' => $pattern['invoice_count'] . ' facturen van ' . $pattern['supplier_name'] . ' clusteren opvallend dicht bij een goedkeuringsgrens.',
      ];
    }

    foreach ($this->repository->findRepeatExceptionPatterns($since) as $pattern) {
      $points = min(20, 5 + ((int) $pattern['exception_count'] * 2));
      $score += $points;
      $patterns[] = [
        'code' => 'repeated_match_exceptions',
        'points' => $points,
        'supplier_name' => $pattern['supplier_name'],
        'value' => (float) $pattern['exception_amount'],
        'message' => $pattern['supplier_name'] . ' heeft ' . $pattern['exception_count'] . ' factuurafwijkingen in de analyseperiode.',
      ];
    }

    foreach ($this->repository->findDecisionPairPatterns($since) as $pattern) {
      $points = min(15, 4 + ((int) $pattern['decision_count']));
      $score += $points;
      $patterns[] = [
        'code' => 'repeated_decider_approver_pair',
        'points' => $points,
        'supplier_name' => $pattern['selected_supplier'],
        'value' => (int) $pattern['decision_count'],
        'message' => 'Dezelfde beslisser/goedkeurder-combinatie komt ' . $pattern['decision_count'] . ' keer terug bij ' . $pattern['selected_supplier'] . '.',
      ];
    }

    foreach ($this->repository->findRecentBankChangeSignals($since) as $signal) {
      $points = 15;
      $score += $points;
      $patterns[] = [
        'code' => 'recent_bank_change',
        'points' => $points,
        'supplier_name' => $signal['supplier_name'],
        'value' => (float) $signal['amount'],
        'message' => 'Recente bankrekening- of G-rekeningwijziging bij een betaalvoorstel voor ' . $signal['supplier_name'] . ' vereist onafhankelijke verificatie.',
      ];
    }

    $score = min(100, $score);
    usort($patterns, static fn(array $a, array $b): int => $b['points'] <=> $a['points']);
    $level = match (TRUE) {
      $score >= 75 => 'kritiek',
      $score >= 50 => 'hoog',
      $score >= 25 => 'verhoogd',
      default => 'laag',
    };

    return [
      'score' => $score,
      'level' => $level,
      'status' => $score >= 50 ? 'onderzoek_nodig' : ($score >= 25 ? 'review_nodig' : 'onder_controle'),
      'patterns' => $patterns,
      'signals' => array_values(array_unique(array_column($patterns, 'message'))),
      'governance' => 'Anomaliesignalen zijn geen bewijs van fraude. Elk signaal vereist onafhankelijke menselijke beoordeling en onderliggende broncontrole.',
    ];
  }

}
