<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use RuntimeException;

/**
 * Creates reproducible supplier scores from financial source evidence.
 */
final class SupplierPerformanceScorecard {

  private const array METRICS = ['delivery', 'quality', 'invoice', 'price', 'failure_cost'];

  public function __construct(
    private readonly Connection $database,
    private readonly VatCalculator $decimal,
  ) {}

  /**
   * @param array<string, mixed> $policy
   */
  public function snapshot(
    int $projectNid,
    string $supplierRef,
    string $supplierName,
    string $policyVersion,
    array $policy,
    int $userId,
    ?string $snapshotDate = NULL,
  ): int {
    foreach ([$supplierRef, $supplierName, $policyVersion] as $required) {
      if (trim($required) === '') {
        throw new InvalidArgumentException('Supplier identity and scoring-policy version are required.');
      }
    }
    if ($userId <= 0) {
      throw new InvalidArgumentException('Supplier score requires a responsible human user.');
    }
    $weights = $this->validatePolicy($policy);
    $date = $snapshotDate ?? date('Y-m-d');

    $exists = (int) $this->database->select('brebo_finance_supplier_score_snapshot', 's')
      ->condition('project_nid', $projectNid)
      ->condition('supplier_ref', trim($supplierRef))
      ->condition('snapshot_date', $date)
      ->condition('policy_version', trim($policyVersion))
      ->countQuery()
      ->execute()
      ->fetchField();
    if ($exists > 0) {
      throw new RuntimeException('This immutable daily supplier score already exists.');
    }

    $orders = $this->orders($projectNid, $supplierRef);
    $receipts = $this->receipts($projectNid, array_column($orders, 'id'));
    $invoices = $this->invoices($projectNid, $supplierRef);
    $invoiceIds = array_map('intval', array_column($invoices, 'id'));
    $variance = $this->invoiceVariance($invoiceIds);
    $failureCost = $this->failureCost($projectNid, $supplierRef);
    $purchaseAmount = $this->sumRows($orders, 'amount_ex_vat');
    $invoiceAmount = $this->sumRows($invoices, 'amount_ex_vat');

    $deliveryEligible = array_filter(
      $receipts,
      static fn (array $receipt): bool => !empty($receipt['delivery_date']),
    );
    $onTime = count(array_filter(
      $deliveryEligible,
      static fn (array $receipt): bool => $receipt['performance_date'] <= $receipt['delivery_date'],
    ));
    $qualityAccepted = count(array_filter(
      $receipts,
      static fn (array $receipt): bool => (int) $receipt['quality_accepted'] === 1,
    ));
    $matchedInvoices = count(array_filter(
      $invoices,
      static fn (array $invoice): bool => $invoice['match_status'] === 'matched',
    ));

    $scores = [
      'delivery' => $this->ratioScore($onTime, count($deliveryEligible)),
      'quality' => $this->ratioScore($qualityAccepted, count($receipts)),
      'invoice' => $this->ratioScore($matchedInvoices, count($invoices)),
      'price' => $this->inverseFinancialRatio($variance, $invoiceAmount),
      'failure_cost' => $this->inverseFinancialRatio($failureCost, $purchaseAmount),
    ];
    $weightedScore = $this->weightedScore($scores, $weights);
    $confidence = $this->confidenceClass(
      max(count($orders), count($receipts), count($invoices)),
      $policy['confidence_min_samples'],
    );

    $policyJson = json_encode($policy, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    $evidence = [
      'order_ids' => array_map('intval', array_column($orders, 'id')),
      'receipt_ids' => array_map('intval', array_column($receipts, 'id')),
      'invoice_ids' => $invoiceIds,
      'order_count' => count($orders),
      'delivery_sample_count' => count($deliveryEligible),
      'on_time_count' => $onTime,
      'receipt_count' => count($receipts),
      'quality_accepted_count' => $qualityAccepted,
      'invoice_count' => count($invoices),
      'matched_invoice_count' => $matchedInvoices,
      'purchase_amount_ex_vat' => $purchaseAmount,
      'invoice_amount_ex_vat' => $invoiceAmount,
      'invoice_variance_ex_vat' => $variance,
      'failure_cost_ex_vat' => $failureCost,
      'scores' => $scores,
    ];
    $evidenceJson = json_encode($evidence, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    $canonical = [
      'project_nid' => $projectNid,
      'supplier_ref' => trim($supplierRef),
      'supplier_name' => trim($supplierName),
      'snapshot_date' => $date,
      'policy_version' => trim($policyVersion),
      'policy_hash' => hash('sha256', $policyJson),
      'evidence_hash' => hash('sha256', $evidenceJson),
      'weighted_score' => $weightedScore,
      'confidence_class' => $confidence,
    ];
    $contentHash = hash(
      'sha256',
      json_encode($canonical, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
    );

    return (int) $this->database->insert('brebo_finance_supplier_score_snapshot')
      ->fields([
        'project_nid' => $projectNid,
        'supplier_ref' => trim($supplierRef),
        'supplier_name' => trim($supplierName),
        'snapshot_date' => $date,
        'policy_version' => trim($policyVersion),
        'delivery_score' => $scores['delivery'],
        'quality_score' => $scores['quality'],
        'invoice_score' => $scores['invoice'],
        'price_score' => $scores['price'],
        'failure_cost_score' => $scores['failure_cost'],
        'weighted_score' => $weightedScore,
        'confidence_class' => $confidence,
        'order_count' => count($orders),
        'receipt_count' => count($receipts),
        'invoice_count' => count($invoices),
        'purchase_amount_ex_vat' => $purchaseAmount,
        'invoice_variance_ex_vat' => $variance,
        'failure_cost_ex_vat' => $failureCost,
        'policy_payload' => $policyJson,
        'evidence_payload' => $evidenceJson,
        'content_hash' => $contentHash,
        'created' => time(),
        'created_by' => $userId,
      ])
      ->execute();
  }

  /**
   * @param array<string, mixed> $policy
   *
   * @return array<string, string>
   */
  private function validatePolicy(array $policy): array {
    if (!isset($policy['weights'], $policy['confidence_min_samples'])
      || !is_array($policy['weights'])
      || !is_array($policy['confidence_min_samples'])
    ) {
      throw new InvalidArgumentException('Scoring policy requires weights and confidence sample thresholds.');
    }

    $weights = [];
    $total = '0.0000';
    foreach (self::METRICS as $metric) {
      $weight = (string) ($policy['weights'][$metric] ?? '');
      if ($weight === '' || $this->decimal->compare($weight, '0') < 0) {
        throw new InvalidArgumentException("A non-negative weight is required for $metric.");
      }
      $weights[$metric] = $weight;
      $total = $this->decimal->add($total, $weight);
    }
    if ($this->decimal->compare($total, '1.0000') !== 0) {
      throw new InvalidArgumentException('Supplier-score weights must total exactly 1.0000.');
    }

    $previous = PHP_INT_MAX;
    foreach (['A', 'B', 'C', 'D'] as $class) {
      $minimum = $policy['confidence_min_samples'][$class] ?? NULL;
      if (!is_int($minimum) || $minimum < 1 || $minimum >= $previous) {
        throw new InvalidArgumentException('Confidence thresholds A-D must be positive and strictly descending.');
      }
      $previous = $minimum;
    }
    return $weights;
  }

  /**
   * @param array<string, string|null> $scores
   * @param array<string, string> $weights
   */
  private function weightedScore(array $scores, array $weights): ?string {
    $weighted = '0.0000';
    $availableWeight = '0.0000';
    foreach (self::METRICS as $metric) {
      if ($scores[$metric] === NULL) {
        continue;
      }
      $weighted = $this->decimal->add(
        $weighted,
        $this->decimal->multiply($scores[$metric], $weights[$metric]),
      );
      $availableWeight = $this->decimal->add($availableWeight, $weights[$metric]);
    }
    if ($this->decimal->compare($availableWeight, '0') === 0) {
      return NULL;
    }
    $percentage = $this->decimal->percentage($weighted, $availableWeight);
    return $percentage !== NULL ? $this->decimal->multiply($percentage, '0.0100') : NULL;
  }

  private function ratioScore(int $accepted, int $sample): ?string {
    if ($sample === 0) {
      return NULL;
    }
    return $this->decimal->percentage((string) $accepted, (string) $sample);
  }

  private function inverseFinancialRatio(string $negativeAmount, string $basisAmount): ?string {
    if ($this->decimal->compare($basisAmount, '0') <= 0) {
      return NULL;
    }
    $penalty = $this->decimal->percentage($negativeAmount, $basisAmount);
    if ($penalty === NULL || $this->decimal->compare($penalty, '100') >= 0) {
      return '0.0000';
    }
    return $this->decimal->subtract('100.0000', $penalty);
  }

  /**
   * @param array<string, int> $thresholds
   */
  private function confidenceClass(int $sample, array $thresholds): string {
    foreach (['A', 'B', 'C', 'D'] as $class) {
      if ($sample >= $thresholds[$class]) {
        return $class;
      }
    }
    return 'E';
  }

  /**
   * @return list<array<string, mixed>>
   */
  private function orders(int $projectNid, string $supplierRef): array {
    return $this->database->select('brebo_finance_commitment', 'c')
      ->fields('c', ['id', 'amount_ex_vat', 'delivery_date'])
      ->condition('project_nid', $projectNid)
      ->condition('supplier_ref', trim($supplierRef))
      ->condition('status', 'cancelled', '<>')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * @param list<int|string> $commitmentIds
   *
   * @return list<array<string, mixed>>
   */
  private function receipts(int $projectNid, array $commitmentIds): array {
    if ($commitmentIds === []) {
      return [];
    }
    $query = $this->database->select('brebo_finance_performance_receipt', 'r');
    $query->join('brebo_finance_commitment_line', 'l', 'l.id = r.commitment_line_id');
    $query->join('brebo_finance_commitment', 'c', 'c.id = l.commitment_id');
    $query->fields('r', ['id', 'performance_date', 'quality_accepted']);
    $query->addField('c', 'delivery_date');
    $query->condition('r.project_nid', $projectNid);
    $query->condition('r.status', 'verified');
    $query->condition('c.id', $commitmentIds, 'IN');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * @return list<array<string, mixed>>
   */
  private function invoices(int $projectNid, string $supplierRef): array {
    return $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i', ['id', 'amount_ex_vat', 'match_status'])
      ->condition('project_nid', $projectNid)
      ->condition('supplier_ref', trim($supplierRef))
      ->condition('status', 'cancelled', '<>')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * @param list<int> $invoiceIds
   */
  private function invoiceVariance(array $invoiceIds): string {
    if ($invoiceIds === []) {
      return '0.0000';
    }
    $query = $this->database->select('brebo_finance_purchase_invoice_line', 'l');
    $query->condition('invoice_id', $invoiceIds, 'IN');
    $query->addExpression('COALESCE(SUM(ABS(variance_amount_ex_vat)), 0)', 'total');
    return (string) $query->execute()->fetchField();
  }

  private function failureCost(int $projectNid, string $supplierRef): string {
    $query = $this->database->select('brebo_finance_failure_cost', 'f');
    $query->condition('project_nid', $projectNid);
    $query->condition('responsible_party_ref', trim($supplierRef));
    $query->condition('status', ['validated', 'recovery_pending', 'closed'], 'IN');
    $query->addExpression('COALESCE(SUM(net_failure_cost_ex_vat), 0)', 'total');
    return (string) $query->execute()->fetchField();
  }

  /**
   * @param list<array<string, mixed>> $rows
   */
  private function sumRows(array $rows, string $field): string {
    $total = '0.0000';
    foreach ($rows as $row) {
      $total = $this->decimal->add($total, (string) $row[$field]);
    }
    return $total;
  }

}
