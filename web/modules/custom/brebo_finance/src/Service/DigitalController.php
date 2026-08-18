<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use UnexpectedValueException;

/**
 * Central orchestration role for deterministic and AI financial control.
 */
final class DigitalController {

  public function __construct(
    private readonly Connection $database,
    private readonly FinancialControlScanner $controlScanner,
    private readonly AiFinancialAssessmentManager $aiAssessmentManager,
  ) {}

  /**
   * Runs hard controls and prepares a sealed evidence pack for AI analysis.
   *
   * @return array{project_nid: int, generated_at: int, controls: array<string, int>, evidence: array<string, mixed>, evidence_hash: string}
   */
  public function prepareReview(int $projectNid): array {
    $controls = $this->controlScanner->scanProject($projectNid);
    $evidence = [
      'latest_forecast' => $this->latestForecast($projectNid),
      'open_findings' => $this->openFindings($projectNid),
      'pending_ai_assessments' => $this->pendingAiCount($projectNid),
      'payment_exceptions' => $this->paymentExceptions($projectNid),
      'budget_state' => $this->budgetState($projectNid),
    ];
    $generatedAt = time();
    $canonical = [
      'project_nid' => $projectNid,
      'generated_at' => $generatedAt,
      'controls' => $controls,
      'evidence' => $evidence,
    ];

    return $canonical + [
      'evidence_hash' => hash(
        'sha256',
        json_encode($canonical, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
      ),
    ];
  }

  /**
   * Executes and stores one scheduled evidence run.
   */
  public function createScheduledRun(int $projectNid, int $systemUserId = 0): int {
    $package = $this->prepareReview($projectNid);
    $now = time();
    return (int) $this->database->insert('brebo_finance_controller_run')
      ->fields([
        'project_nid' => $projectNid,
        'run_date' => date('Y-m-d', $now),
        'run_type' => 'scheduled',
        'status' => 'evidence_ready',
        'control_counts' => json_encode($package['controls'], JSON_THROW_ON_ERROR),
        'evidence_payload' => json_encode(
          $package,
          JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
        ),
        'evidence_hash' => $package['evidence_hash'],
        'started' => $now,
        'completed' => $now,
        'created' => $now,
        'created_by' => $systemUserId,
        'changed' => $now,
        'changed_by' => $systemUserId,
      ])
      ->execute();
  }

  /**
   * Registers AI analysis only when it refers to the exact evidence package.
   */
  public function registerAiReview(
    array $evidencePackage,
    string $assessmentType,
    string $modelProvider,
    string $modelName,
    ?string $modelVersion,
    string $promptVersion,
    string $confidence,
    string $severity,
    string $title,
    string $analysis,
    string $recommendation,
    array $rawOutput,
    int $systemUserId = 0,
  ): int {
    $expectedHash = $evidencePackage['evidence_hash'] ?? '';
    $canonical = $evidencePackage;
    unset($canonical['evidence_hash']);
    $actualHash = hash(
      'sha256',
      json_encode($canonical, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
    );
    if (!is_string($expectedHash) || !hash_equals($expectedHash, $actualHash)) {
      throw new UnexpectedValueException('AI evidence package was changed after controller preparation.');
    }

    return $this->aiAssessmentManager->record(
      (int) $evidencePackage['project_nid'],
      $assessmentType,
      'project_financial_position',
      (int) $evidencePackage['project_nid'],
      $modelProvider,
      $modelName,
      $modelVersion,
      $promptVersion,
      $confidence,
      $severity,
      $title,
      $analysis,
      $recommendation,
      $evidencePackage,
      $rawOutput,
      $systemUserId,
    );
  }

  private function latestForecast(int $projectNid): ?array {
    $record = $this->database->select('brebo_finance_forecast_snapshot', 'f')
      ->fields('f')
      ->condition('project_nid', $projectNid)
      ->orderBy('snapshot_date', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $record !== FALSE ? $record : NULL;
  }

  /**
   * @return list<array<string, mixed>>
   */
  private function openFindings(int $projectNid): array {
    return $this->database->select('brebo_finance_control_finding', 'f')
      ->fields('f', [
        'id',
        'control_code',
        'origin',
        'severity',
        'source_type',
        'source_id',
        'title',
        'cause',
        'consequence',
        'control_measure',
        'owner_uid',
        'due_date',
        'status',
        'detected',
        'last_seen',
      ])
      ->condition('project_nid', $projectNid)
      ->condition('status', 'open')
      ->orderBy('severity')
      ->orderBy('detected')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  private function pendingAiCount(int $projectNid): int {
    return (int) $this->database->select('brebo_finance_ai_assessment', 'a')
      ->condition('project_nid', $projectNid)
      ->condition('status', 'pending_review')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * @return list<array<string, mixed>>
   */
  private function paymentExceptions(int $projectNid): array {
    $query = $this->database->select('brebo_finance_purchase_invoice', 'i');
    $query->fields('i', [
      'id',
      'supplier_name',
      'invoice_number',
      'due_date',
      'match_status',
      'amount_inc_vat',
      'g_account_amount',
      'regular_account_amount',
    ]);
    $query->condition('project_nid', $projectNid);
    $or = $query->orConditionGroup()
      ->condition('match_status', 'matched', '<>')
      ->condition('status', 'received');
    $query->condition($or);
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * @return array<string, mixed>
   */
  private function budgetState(int $projectNid): array {
    $budget = $this->database->select('brebo_finance_budget', 'b')
      ->fields('b', [
        'id',
        'version',
        'status',
        'source_calculation_id',
        'source_calculation_version',
        'source_content_hash',
        'content_hash',
        'approved',
      ])
      ->condition('project_nid', $projectNid)
      ->condition('budget_type', 'working')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return $budget !== FALSE ? $budget : ['status' => 'missing'];
  }

}
