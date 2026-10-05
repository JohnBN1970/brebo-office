<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\DigitalControllerRepositoryInterface;
use UnexpectedValueException;

/**
 * Central orchestration role for deterministic and AI financial control.
 */
final class DigitalController {

  public function __construct(
    private readonly DigitalControllerRepositoryInterface $repository,
    private readonly FinancialControlScanner $controlScanner,
    private readonly ControllerBriefingBuilder $briefingBuilder,
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
      'latest_forecast' => $this->repository->latestForecast($projectNid),
      'open_findings' => $this->repository->openFindings($projectNid),
      'pending_ai_assessments' => $this->repository->pendingAiCount($projectNid),
      'payment_exceptions' => $this->repository->paymentExceptions($projectNid),
      'budget_state' => $this->repository->budgetState($projectNid),
      'decision_briefing' => $this->briefingBuilder->build($projectNid),
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
    $date = date('Y-m-d', $now);
    return $this->repository->saveScheduledRun($projectNid, $date, [
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
    ]);
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

}
