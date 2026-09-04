<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\State\StateInterface;

/** Persists operational sync health and per-project reconciliation provenance. */
final class ReceivablesReconciliationMonitor {

  private const STATE_KEY = 'brebo_finance.receivables_reconciliation';

  public function __construct(
    private readonly StateInterface $state,
    private readonly Connection $database,
  ) {}

  /** @param array<string, int> $summary */
  public function succeeded(array $summary, int $startedAt): void {
    $completedAt = time();
    $this->state->set(self::STATE_KEY, [
      'status' => 'ok',
      'started_at' => $startedAt,
      'completed_at' => $completedAt,
      'summary' => $summary,
      'error' => NULL,
      'last_success_started_at' => $startedAt,
      'last_success_completed_at' => $completedAt,
      'last_success_summary' => $summary,
    ]);
  }

  public function failed(\Throwable $error, int $startedAt): void {
    $previous = $this->status();
    $previousWasSuccessful = ($previous['status'] ?? NULL) === 'ok';
    $lastSuccessStartedAt = $previous['last_success_started_at'] ?? ($previousWasSuccessful ? ($previous['started_at'] ?? NULL) : NULL);
    $lastSuccessCompletedAt = $previous['last_success_completed_at'] ?? ($previousWasSuccessful ? ($previous['completed_at'] ?? NULL) : NULL);
    $lastSuccessSummary = $previous['last_success_summary'] ?? ($previousWasSuccessful ? ($previous['summary'] ?? NULL) : NULL);

    $this->state->set(self::STATE_KEY, [
      'status' => 'failed',
      'started_at' => $startedAt,
      'completed_at' => time(),
      'summary' => NULL,
      'error' => mb_substr($error->getMessage(), 0, 1000),
      'last_success_started_at' => $lastSuccessStartedAt,
      'last_success_completed_at' => $lastSuccessCompletedAt,
      'last_success_summary' => $lastSuccessSummary,
    ]);
  }

  /** @return array<string, mixed> */
  public function status(): array {
    $value = $this->state->get(self::STATE_KEY, []);
    return is_array($value) ? $value : [];
  }

  /** Records one project-scoped immutable provenance event in the existing audit trail. */
  public function invoiceUpdated(int $projectNid, int $salesInvoiceId, string $beforeHash, string $afterHash, string $moneybirdId): void {
    if (!$this->database->schema()->tableExists('brebo_finance_audit')) {
      return;
    }
    $this->database->insert('brebo_finance_audit')->fields([
      'project_nid' => $projectNid,
      'entity_type' => 'sales_invoice',
      'entity_id' => $salesInvoiceId,
      'action' => 'moneybird_reconcile',
      'before_hash' => $beforeHash !== '' ? $beforeHash : NULL,
      'after_hash' => $afterHash,
      'payload' => json_encode(['moneybird_id' => $moneybirdId], JSON_THROW_ON_ERROR),
      'reason' => 'Recurring Moneybird receivables reconciliation.',
      'created' => time(),
      'created_by' => NULL,
    ])->execute();
  }

}
