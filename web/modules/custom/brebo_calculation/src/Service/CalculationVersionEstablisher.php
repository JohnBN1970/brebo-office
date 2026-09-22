<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/**
 * Establishes a draft calculation version as one immutable canonical truth.
 */
final class CalculationVersionEstablisher {

  public function __construct(
    private readonly Connection $database,
    private readonly CalculationResultService $resultService,
    private readonly CalculationReadinessInspector $readinessInspector,
  ) {}

  /**
   * @return array<string,mixed>
   */
  public function establish(int $calculationId, string $version, AccountInterface $account): array {
    $versionRow = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()
      ->fetchAssoc();

    if (!is_array($versionRow)) {
      throw new \RuntimeException('Calculatieversie niet gevonden.');
    }
    if ((string) $versionRow['status'] !== 'draft' || $versionRow['locked_at'] !== NULL) {
      throw new \RuntimeException('Alleen een open conceptversie kan worden vastgesteld.');
    }

    $readiness = $this->readinessInspector->inspect($calculationId, $version);
    if ((int) ($readiness['blocking'] ?? 0) > 0) {
      throw new \RuntimeException('Calculatie kan niet worden vastgesteld zolang readiness blokkades bevat.');
    }

    $result = $this->resultService->calculate($calculationId, $version);
    $structure = $this->database->select('brebo_calculation_structure', 's')
      ->fields('s')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->orderBy('sort_order')
      ->orderBy('depth')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $hashResult = $result;
    unset($hashResult['content_hash'], $hashResult['status'], $hashResult['locked_at'], $hashResult['source']);
    $hashPayload = [
      'calculation_id' => $calculationId,
      'version' => $version,
      'structure' => $structure,
      'canonical_result' => $hashResult,
    ];
    $contentHash = hash('sha256', json_encode($hashPayload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

    $lockedAt = \Drupal::time()->getCurrentTime();
    $snapshotResult = $result;
    $snapshotResult['content_hash'] = $contentHash;
    $payload = [
      'schema' => 'canonical_result_v1',
      'calculation_id' => $calculationId,
      'version' => $version,
      'content_hash' => $contentHash,
      'structure' => $structure,
      'canonical_result' => $snapshotResult,
      'readiness' => $readiness,
    ];

    $transaction = $this->database->startTransaction();
    try {
      $existing = (bool) $this->database->select('brebo_calculation_snapshot', 's')
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->countQuery()
        ->execute()
        ->fetchField();
      if ($existing) {
        throw new \RuntimeException('Voor deze calculatieversie bestaat al een immutable snapshot.');
      }

      $this->database->insert('brebo_calculation_snapshot')->fields([
        'calculation_id' => $calculationId,
        'version' => $version,
        'content_hash' => $contentHash,
        'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
        'created' => $lockedAt,
        'created_by' => (int) $account->id(),
      ])->execute();

      $updated = $this->database->update('brebo_calculation_version')
        ->fields([
          'status' => 'established',
          'locked_at' => $lockedAt,
          'locked_by' => (int) $account->id(),
          'content_hash' => $contentHash,
        ])
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->condition('status', 'draft')
        ->isNull('locked_at')
        ->execute();

      if ($updated !== 1) {
        throw new \RuntimeException('Calculatieversie veranderde tijdens het vaststellen.');
      }
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }

    $snapshotResult['status'] = 'established';
    $snapshotResult['locked_at'] = $lockedAt;
    $snapshotResult['source'] = 'immutable_snapshot';
    return $snapshotResult;
  }

}
