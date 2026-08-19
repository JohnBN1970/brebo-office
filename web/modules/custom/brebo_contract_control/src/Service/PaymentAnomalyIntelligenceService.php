<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\Core\Database\Connection;

/**
 * Detects longitudinal payment patterns that warrant financial review.
 *
 * Signals are investigative prompts only and must never be treated as proof
 * of fraud or misconduct without human review and supporting evidence.
 */
final class PaymentAnomalyIntelligenceService {

  public function __construct(private readonly Connection $database) {}

  /** @return array<string, mixed> */
  public function analyze(?int $since = NULL): array {
    if (!$this->database->schema()->tableExists('brebo_supplier_invoice')) {
      return ['score' => 0, 'level' => 'laag', 'signals' => [], 'patterns' => [], 'status' => 'no_data'];
    }

    $since ??= strtotime('-365 days') ?: 0;
    $patterns = [];
    $score = 0;

    foreach ($this->thresholdPatterns($since) as $pattern) {
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

    foreach ($this->repeatExceptionPatterns($since) as $pattern) {
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

    foreach ($this->decisionPairPatterns($since) as $pattern) {
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

    foreach ($this->recentBankChangeSignals($since) as $signal) {
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

  /** @return array<int, array<string, mixed>> */
  private function thresholdPatterns(int $since): array {
    if (!$this->database->schema()->fieldExists('brebo_supplier_invoice', 'created')) {
      return [];
    }
    $query = $this->database->select('brebo_supplier_invoice', 'i');
    $query->addField('i', 'supplier_name');
    $query->addExpression('COUNT(*)', 'invoice_count');
    $query->addExpression('COALESCE(SUM(gross_amount),0)', 'total_amount');
    $query->condition('created', $since, '>=');
    $or = $query->orConditionGroup();
    foreach ([5000, 10000, 25000, 50000] as $limit) {
      $or->condition('gross_amount', [$limit * 0.95, $limit], 'BETWEEN');
    }
    $query->condition($or);
    $query->groupBy('supplier_name');
    $query->having('COUNT(*) >= 3');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @return array<int, array<string, mixed>> */
  private function repeatExceptionPatterns(int $since): array {
    $query = $this->database->select('brebo_supplier_invoice', 'i');
    $query->addField('i', 'supplier_name');
    $query->addExpression('COUNT(*)', 'exception_count');
    $query->addExpression('COALESCE(SUM(gross_amount),0)', 'exception_amount');
    if ($this->database->schema()->fieldExists('brebo_supplier_invoice', 'created')) {
      $query->condition('created', $since, '>=');
    }
    $query->condition('match_status', 'matched', '<>');
    $query->groupBy('supplier_name');
    $query->having('COUNT(*) >= 3');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @return array<int, array<string, mixed>> */
  private function decisionPairPatterns(int $since): array {
    if (!$this->database->schema()->tableExists('brebo_procurement_decision')) {
      return [];
    }
    $query = $this->database->select('brebo_procurement_decision', 'd');
    $query->addField('d', 'selected_supplier');
    $query->addField('d', 'decided_by');
    $query->addField('d', 'approved_by');
    $query->addExpression('COUNT(*)', 'decision_count');
    $query->condition('created', $since, '>=');
    $query->isNotNull('approved_by');
    $query->groupBy('selected_supplier');
    $query->groupBy('decided_by');
    $query->groupBy('approved_by');
    $query->having('COUNT(*) >= 5');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @return array<int, array<string, mixed>> */
  private function recentBankChangeSignals(int $since): array {
    if (!$this->database->schema()->tableExists('brebo_payment_control_event')) {
      return [];
    }
    $query = $this->database->select('brebo_payment_control_event', 'e');
    $query->fields('e', ['supplier_name', 'amount', 'event_type']);
    $query->condition('created_at', $since, '>=');
    $query->condition('event_type', ['iban_changed', 'g_account_changed'], 'IN');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }
}
