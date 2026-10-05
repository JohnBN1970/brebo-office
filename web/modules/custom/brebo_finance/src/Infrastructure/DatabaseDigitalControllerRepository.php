<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\DigitalControllerRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for the Finance Digital Controller. */
final class DatabaseDigitalControllerRepository implements DigitalControllerRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function latestForecast(int $projectNid): ?array {
    $record = $this->database->select('brebo_finance_forecast_snapshot', 'f')
      ->fields('f')
      ->condition('project_nid', $projectNid)
      ->orderBy('snapshot_date', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $record === FALSE ? NULL : $record;
  }

  public function openFindings(int $projectNid): array {
    return $this->database->select('brebo_finance_control_finding', 'f')
      ->fields('f', [
        'id', 'control_code', 'origin', 'severity', 'source_type', 'source_id',
        'title', 'cause', 'consequence', 'control_measure', 'owner_uid', 'due_date',
        'status', 'detected', 'last_seen',
      ])
      ->condition('project_nid', $projectNid)
      ->condition('status', ['open', 'pending_verification'], 'IN')
      ->orderBy('severity')
      ->orderBy('detected')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function pendingAiCount(int $projectNid): int {
    return (int) $this->database->select('brebo_finance_ai_assessment', 'a')
      ->condition('project_nid', $projectNid)
      ->condition('status', 'pending_review')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  public function paymentExceptions(int $projectNid): array {
    $query = $this->database->select('brebo_finance_purchase_invoice', 'i');
    $query->fields('i', [
      'id', 'supplier_name', 'invoice_number', 'due_date', 'match_status',
      'amount_inc_vat', 'g_account_amount', 'regular_account_amount',
    ]);
    $query->condition('project_nid', $projectNid);
    $or = $query->orConditionGroup()
      ->condition('match_status', 'matched', '<>')
      ->condition('status', 'received');
    $query->condition($or);
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function budgetState(int $projectNid): array {
    $budget = $this->database->select('brebo_finance_budget', 'b')
      ->fields('b', [
        'id', 'version', 'status', 'source_calculation_id',
        'source_calculation_version', 'source_content_hash', 'content_hash', 'approved',
      ])
      ->condition('project_nid', $projectNid)
      ->condition('budget_type', 'working')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $budget === FALSE ? ['status' => 'missing'] : $budget;
  }

  public function saveScheduledRun(int $projectNid, string $runDate, array $fields): int {
    $this->database->merge('brebo_finance_controller_run')
      ->keys([
        'project_nid' => $projectNid,
        'run_date' => $runDate,
        'run_type' => 'scheduled',
      ])
      ->fields($fields)
      ->execute();

    return (int) $this->database->select('brebo_finance_controller_run', 'r')
      ->fields('r', ['id'])
      ->condition('project_nid', $projectNid)
      ->condition('run_date', $runDate)
      ->condition('run_type', 'scheduled')
      ->execute()
      ->fetchField();
  }

}
