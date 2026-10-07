<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\brebo_contract_control\Contract\ControllerCaseRepositoryInterface;

/** Creates auditable controller investigations from financial anomaly signals. */
final class ControllerCaseManagementService {

  public function __construct(
    private readonly ControllerCaseRepositoryInterface $repository,
    private readonly PaymentAnomalyIntelligenceService $anomalyIntelligence,
  ) {}

  /** @return array<string, mixed> */
  public function openFromCurrentSignals(int $ownerUid, ?int $now = NULL): array {
    $now ??= time();
    $analysis = $this->anomalyIntelligence->analyze();
    if (!in_array($analysis['status'] ?? '', ['review_nodig', 'onderzoek_nodig'], TRUE)) {
      return ['created' => FALSE, 'status' => 'no_case_required', 'analysis' => $analysis];
    }

    $patterns = (array) ($analysis['patterns'] ?? []);
    $financialExposure = 0.0;
    foreach ($patterns as $pattern) {
      if (isset($pattern['value']) && is_numeric($pattern['value']) && !in_array($pattern['code'] ?? '', ['repeated_decider_approver_pair'], TRUE)) {
        $financialExposure += max(0.0, (float) $pattern['value']);
      }
    }

    $severity = match ((string) ($analysis['level'] ?? 'laag')) {
      'kritiek' => 'critical',
      'hoog' => 'high',
      'verhoogd' => 'medium',
      default => 'low',
    };
    $deadline = $now + match ($severity) {
      'critical' => 86400,
      'high' => 3 * 86400,
      'medium' => 7 * 86400,
      default => 14 * 86400,
    };

    $id = $this->repository->insert([
      'case_ref' => 'CTRL-' . gmdate('Ymd-His', $now),
      'severity' => $severity,
      'risk_score' => (int) ($analysis['score'] ?? 0),
      'financial_exposure' => round($financialExposure, 2),
      'signals_json' => json_encode($patterns, JSON_THROW_ON_ERROR),
      'owner_uid' => $ownerUid,
      'status' => 'open',
      'deadline_at' => $deadline,
      'created_at' => $now,
    ]);

    return [
      'created' => TRUE,
      'case_id' => $id,
      'severity' => $severity,
      'risk_score' => (int) ($analysis['score'] ?? 0),
      'financial_exposure' => round($financialExposure, 2),
      'deadline_at' => $deadline,
      'status' => 'open',
    ];
  }

  /** @param array<string, mixed> $evidence */
  public function conclude(int $caseId, int $reviewerUid, string $conclusion, array $evidence, ?int $now = NULL): array {
    $now ??= time();
    $case = $this->repository->findById($caseId);
    if (!$case) {
      throw new \InvalidArgumentException('Onbekend controllerdossier.');
    }
    if (trim($conclusion) === '' || $evidence === []) {
      throw new \InvalidArgumentException('Conclusie en onderliggende bewijsstukken zijn verplicht.');
    }
    if ((int) $case['owner_uid'] === $reviewerUid && in_array((string) $case['severity'], ['high', 'critical'], TRUE)) {
      throw new \LogicException('Hoog/kritiek controllerdossier vereist onafhankelijke tweede beoordeling.');
    }

    $this->repository->update($caseId, [
      'status' => 'concluded',
      'reviewer_uid' => $reviewerUid,
      'conclusion' => $conclusion,
      'evidence_json' => json_encode($evidence, JSON_THROW_ON_ERROR),
      'concluded_at' => $now,
    ]);

    return ['case_id' => $caseId, 'status' => 'concluded', 'message' => 'Controllerdossier inhoudelijk afgesloten met bewijs en reviewer.'];
  }
}
