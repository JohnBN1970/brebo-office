<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;

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
    private readonly Connection $database,
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
      $existing = $this->database->select('brebo_finance_control_finding', 'f')->fields('f')
        ->condition('project_nid', $projectNid)->condition('control_code', $controlCode)
        ->condition('status', ['resolved_verified', 'resolved_automatically'], 'NOT IN')
        ->execute()->fetchAssoc();
      $payload = [
        'source' => 'financial_euro_trace', 'entity_type' => $entityType, 'entity_id' => $entityId,
        'trace_finding_code' => $finding['code'], 'exposure_amount' => $finding['exposure_amount'],
        'control_measure' => $finding['control_measure'], 'totals' => $assessment['totals'],
      ];
      if ($existing !== FALSE) {
        $this->database->update('brebo_finance_control_finding')->fields([
          'severity' => $severity, 'finding' => $finding['message'], 'evidence' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
          'changed' => $now, 'changed_by' => $actorUid ?: NULL,
        ])->condition('id', (int) $existing['id'])->execute();
        $findingIds[] = (int) $existing['id'];
      }
      else {
        $findingIds[] = (int) $this->database->insert('brebo_finance_control_finding')->fields([
          'project_nid' => $projectNid, 'control_code' => $controlCode, 'severity' => $severity,
          'finding' => $finding['message'], 'evidence' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
          'status' => 'open', 'owner_uid' => NULL, 'due_date' => NULL, 'resolution_note' => NULL, 'resolution_evidence' => NULL,
          'resolution_submitted_by' => NULL, 'resolution_verified_by' => NULL, 'resolved' => NULL, 'resolved_by' => NULL,
          'created' => $now, 'changed' => $now, 'changed_by' => $actorUid ?: NULL,
        ])->execute();
      }
    }

    $knownCodes = array_values(self::CODE_MAP);
    $query = $this->database->select('brebo_finance_control_finding', 'f')->fields('f', ['id', 'control_code'])
      ->condition('project_nid', $projectNid)->condition('control_code', $knownCodes, 'IN')
      ->condition('status', ['resolved_verified', 'resolved_automatically'], 'NOT IN');
    if ($activeCodes !== []) $query->condition('control_code', $activeCodes, 'NOT IN');
    foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $stale) {
      $this->database->update('brebo_finance_control_finding')->fields([
        'status' => 'resolved_automatically', 'resolution_note' => 'Euro Trace hercontrole: afwijking is niet meer aanwezig in de geregistreerde bronketen.',
        'resolved' => $now, 'resolved_by' => $actorUid ?: NULL, 'changed' => $now, 'changed_by' => $actorUid ?: NULL,
      ])->condition('id', (int) $stale['id'])->execute();
    }

    return $assessment + ['control_finding_ids' => $findingIds, 'synced_at' => $now];
  }
}
