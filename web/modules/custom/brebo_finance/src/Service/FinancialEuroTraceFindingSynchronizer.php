<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\FinancialEuroTraceFindingRepositoryInterface;

/** Persists Euro Trace anomalies as deterministic financial control findings. */
final class FinancialEuroTraceFindingSynchronizer {
  private const CODE_MAP = [
    'invoice_above_commitment' => 'FIN-EUROTRACE-INVOICE-ABOVE-COMMITMENT',
    'invoice_above_performance' => 'FIN-EUROTRACE-INVOICE-ABOVE-PERFORMANCE',
    'invoice_without_verified_performance' => 'FIN-EUROTRACE-NO-PERFORMANCE',
    'release_above_invoice' => 'FIN-EUROTRACE-RELEASE-ABOVE-INVOICE',
    'release_above_performance' => 'FIN-EUROTRACE-RELEASE-ABOVE-PERFORMANCE',
    'executed_above_release' => 'FIN-EUROTRACE-EXECUTED-ABOVE-RELEASE',
    'incomplete_trace' => 'FIN-EUROTRACE-INCOMPLETE',
  ];

  public function __construct(
    private readonly FinancialEuroTraceFindingRepositoryInterface $repository,
    private readonly FinancialEuroTraceControl $control,
  ) {}

  /** @return array<string, mixed> */
  public function sync(string $entityType, int $entityId, int $actorUid = 0): array {
    $assessment = $this->control->assess($entityType, $entityId);
    $projectNid = (int) $assessment['project_nid'];
    $activeCodes = [];
    $findingIds = [];
    $now = time();

    foreach ($assessment['findings'] as $finding) {
      $controlCode = self::CODE_MAP[$finding['code']] ?? 'FIN-EUROTRACE-' . strtoupper(str_replace('_', '-', (string) $finding['code']));
      $activeCodes[] = $controlCode;
      $severity = $finding['severity'] === 'critical' ? 'critical' : 'high';
      $existing = $this->repository->activeFinding($projectNid, $controlCode);
      $payload = [
        'source' => 'financial_euro_trace', 'entity_type' => $entityType, 'entity_id' => $entityId,
        'trace_finding_code' => $finding['code'], 'exposure_amount' => $finding['exposure_amount'],
        'control_measure' => $finding['control_measure'], 'totals' => $assessment['totals'],
      ];
      if ($existing !== NULL) {
        $this->repository->updateFinding((int) $existing['id'], [
          'severity' => $severity, 'finding' => $finding['message'], 'evidence' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
          'changed' => $now, 'changed_by' => $actorUid ?: NULL,
        ]);
        $findingIds[] = (int) $existing['id'];
      }
      else {
        $findingIds[] = $this->repository->createFinding([
          'project_nid' => $projectNid, 'control_code' => $controlCode, 'severity' => $severity,
          'finding' => $finding['message'], 'evidence' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
          'status' => 'open', 'owner_uid' => NULL, 'due_date' => NULL, 'resolution_note' => NULL, 'resolution_evidence' => NULL,
          'resolution_submitted_by' => NULL, 'resolution_verified_by' => NULL, 'resolved' => NULL, 'resolved_by' => NULL,
          'created' => $now, 'changed' => $now, 'changed_by' => $actorUid ?: NULL,
        ]);
      }
    }

    $knownCodes = array_values(self::CODE_MAP);
    foreach ($this->repository->staleFindings($projectNid, $knownCodes, $activeCodes) as $stale) {
      $this->repository->updateFinding((int) $stale['id'], [
        'status' => 'resolved_automatically', 'resolution_note' => 'Euro Trace hercontrole: afwijking is niet meer aanwezig in de geregistreerde bronketen.',
        'resolved' => $now, 'resolved_by' => $actorUid ?: NULL, 'changed' => $now, 'changed_by' => $actorUid ?: NULL,
      ]);
    }

    return $assessment + ['control_finding_ids' => $findingIds, 'synced_at' => $now];
  }
}
