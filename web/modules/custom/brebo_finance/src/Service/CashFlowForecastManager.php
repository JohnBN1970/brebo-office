<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use DateInterval;
use DateTimeImmutable;
use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/**
 * Builds traceable thirteen-week cash forecasts without assumed cash events.
 */
final class CashFlowForecastManager {

  private const array DIRECTIONS = ['incoming', 'outgoing'];
  private const array ACCOUNT_BUCKETS = ['regular', 'g_account'];
  private const array STATUSES = ['expected', 'confirmed', 'settled', 'cancelled'];

  public function __construct(
    private readonly Connection $database,
    private readonly VatCalculator $decimal,
  ) {}

  /**
   * Synchronizes one source-backed cash event idempotently.
   *
   * @param array<string, mixed> $sourcePayload
   */
  public function synchronizeEvent(
    int $projectNid,
    string $sourceSystem,
    string $sourceType,
    string $sourceId,
    string $direction,
    string $accountBucket,
    string $description,
    string $amountIncVat,
    string $dueDate,
    string $status,
    ?string $confidence,
    int $recordedAt,
    array $sourcePayload,
    int $systemUserId = 0,
  ): int {
    if (!in_array($direction, self::DIRECTIONS, TRUE)
      || !in_array($accountBucket, self::ACCOUNT_BUCKETS, TRUE)
      || !in_array($status, self::STATUSES, TRUE)
    ) {
      throw new InvalidArgumentException('Unknown cash-event direction, account bucket or status.');
    }
    if ($this->decimal->compare($amountIncVat, '0') <= 0) {
      throw new InvalidArgumentException('Cash-event amount must be greater than zero.');
    }
    if (!$this->validDate($dueDate)) {
      throw new InvalidArgumentException('Cash-event due date must use YYYY-MM-DD.');
    }
    foreach ([$sourceSystem, $sourceType, $sourceId, $description] as $required) {
      if (trim($required) === '') {
        throw new InvalidArgumentException('Cash-event source identity and description are required.');
      }
    }
    if ($recordedAt <= 0 || $sourcePayload === []) {
      throw new InvalidArgumentException('Cash event requires a source timestamp and evidence payload.');
    }
    if ($status === 'expected') {
      if ($confidence === NULL
        || !preg_match('/^(?:0(?:\.\d{1,6})?|1(?:\.0{1,6})?)$/', trim($confidence))
      ) {
        throw new InvalidArgumentException('Expected cash events require confidence between zero and one.');
      }
    }
    elseif ($confidence !== NULL) {
      throw new InvalidArgumentException('Confidence is only recorded for expected cash events.');
    }

    $sourceJson = json_encode($sourcePayload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    $sourceHash = hash('sha256', $sourceJson);
    $existing = $this->database->select('brebo_finance_cash_event', 'e')
      ->fields('e')
      ->condition('source_system', trim($sourceSystem))
      ->condition('source_type', trim($sourceType))
      ->condition('source_id', trim($sourceId))
      ->condition('account_bucket', $accountBucket)
      ->execute()
      ->fetchAssoc();
    if ($existing !== FALSE && (int) $existing['recorded_at'] > $recordedAt) {
      throw new UnexpectedValueException('Older cash evidence cannot overwrite a newer source event.');
    }
    if ($existing !== FALSE && hash_equals((string) $existing['source_hash'], $sourceHash)) {
      return (int) $existing['id'];
    }

    $now = time();
    $fields = [
      'project_nid' => $projectNid,
      'direction' => $direction,
      'description' => trim($description),
      'amount_inc_vat' => $amountIncVat,
      'due_date' => $dueDate,
      'status' => $status,
      'confidence' => $confidence,
      'source_hash' => $sourceHash,
      'recorded_at' => $recordedAt,
      'settled_at' => $status === 'settled' ? $recordedAt : NULL,
      'changed' => $now,
      'changed_by' => $systemUserId,
    ];

    if ($existing === FALSE) {
      return (int) $this->database->insert('brebo_finance_cash_event')
        ->fields($fields + [
          'source_system' => trim($sourceSystem),
          'source_type' => trim($sourceType),
          'source_id' => trim($sourceId),
          'account_bucket' => $accountBucket,
          'created' => $now,
          'created_by' => $systemUserId,
        ])
        ->execute();
    }

    $eventId = (int) $existing['id'];
    $this->database->update('brebo_finance_cash_event')
      ->fields($fields)
      ->condition('id', $eventId)
      ->execute();
    return $eventId;
  }

  /**
   * Creates one immutable 13-week project cash forecast.
   */
  public function snapshot(
    int $projectNid,
    string $openingRegularBalance,
    string $openingGAccountBalance,
    string $scenario,
    int $userId,
    ?string $snapshotDate = NULL,
  ): int {
    if (!in_array($scenario, ['committed', 'expected'], TRUE)) {
      throw new InvalidArgumentException('Cash forecast scenario must be committed or expected.');
    }
    $date = $snapshotDate ?? date('Y-m-d');
    if (!$this->validDate($date)) {
      throw new InvalidArgumentException('Cash forecast date must use YYYY-MM-DD.');
    }
    if ($userId <= 0) {
      throw new InvalidArgumentException('Cash forecast requires a responsible human user.');
    }

    $exists = (int) $this->database->select('brebo_finance_cash_forecast_snapshot', 's')
      ->condition('project_nid', $projectNid)
      ->condition('snapshot_date', $date)
      ->condition('scenario', $scenario)
      ->countQuery()
      ->execute()
      ->fetchField();
    if ($exists > 0) {
      throw new RuntimeException('This immutable daily cash-forecast scenario already exists.');
    }

    $start = new DateTimeImmutable($date);
    $end = $start->add(new DateInterval('P90D'));
    $statuses = $scenario === 'committed' ? ['confirmed'] : ['confirmed', 'expected'];
    $events = $this->events($projectNid, $end->format('Y-m-d'), $statuses);

    $regular = $openingRegularBalance;
    $gAccount = $openingGAccountBalance;
    $lowestRegular = $regular;
    $lowestGAccount = $gAccount;
    $firstRegularShortfall = $this->decimal->compare($regular, '0') < 0 ? $date : NULL;
    $firstGShortfall = $this->decimal->compare($gAccount, '0') < 0 ? $date : NULL;
    $weeks = [];

    for ($week = 0; $week < 13; $week++) {
      $weekStart = $start->add(new DateInterval('P' . ($week * 7) . 'D'));
      $weekEnd = $weekStart->add(new DateInterval('P6D'));
      $row = [
        'week' => $week + 1,
        'start_date' => $weekStart->format('Y-m-d'),
        'end_date' => $weekEnd->format('Y-m-d'),
        'regular_incoming' => '0.0000',
        'regular_outgoing' => '0.0000',
        'g_account_incoming' => '0.0000',
        'g_account_outgoing' => '0.0000',
        'events' => [],
      ];

      foreach ($events as $event) {
        $eventDate = (string) $event['due_date'];
        $belongs = $week === 0
          ? $eventDate <= $weekEnd->format('Y-m-d')
          : $eventDate >= $weekStart->format('Y-m-d') && $eventDate <= $weekEnd->format('Y-m-d');
        if (!$belongs) {
          continue;
        }

        $key = $event['account_bucket'] . '_' . $event['direction'];
        $row[$key] = $this->decimal->add((string) $row[$key], (string) $event['amount_inc_vat']);
        $row['events'][] = [
          'id' => (int) $event['id'],
          'source_type' => $event['source_type'],
          'source_id' => $event['source_id'],
          'description' => $event['description'],
          'due_date' => $eventDate,
          'direction' => $event['direction'],
          'account_bucket' => $event['account_bucket'],
          'amount_inc_vat' => $event['amount_inc_vat'],
          'status' => $event['status'],
          'confidence' => $event['confidence'],
          'source_hash' => $event['source_hash'],
        ];
      }

      $regular = $this->decimal->add(
        $regular,
        $this->decimal->subtract((string) $row['regular_incoming'], (string) $row['regular_outgoing']),
      );
      $gAccount = $this->decimal->add(
        $gAccount,
        $this->decimal->subtract((string) $row['g_account_incoming'], (string) $row['g_account_outgoing']),
      );
      $row['closing_regular_balance'] = $regular;
      $row['closing_g_account_balance'] = $gAccount;
      $weeks[] = $row;

      if ($this->decimal->compare($regular, $lowestRegular) < 0) {
        $lowestRegular = $regular;
      }
      if ($this->decimal->compare($gAccount, $lowestGAccount) < 0) {
        $lowestGAccount = $gAccount;
      }
      if ($firstRegularShortfall === NULL && $this->decimal->compare($regular, '0') < 0) {
        $firstRegularShortfall = $weekEnd->format('Y-m-d');
      }
      if ($firstGShortfall === NULL && $this->decimal->compare($gAccount, '0') < 0) {
        $firstGShortfall = $weekEnd->format('Y-m-d');
      }
    }

    $payload = [
      'project_nid' => $projectNid,
      'snapshot_date' => $date,
      'scenario' => $scenario,
      'opening_regular_balance' => $openingRegularBalance,
      'opening_g_account_balance' => $openingGAccountBalance,
      'lowest_regular_balance' => $lowestRegular,
      'lowest_g_account_balance' => $lowestGAccount,
      'first_regular_shortfall_date' => $firstRegularShortfall,
      'first_g_account_shortfall_date' => $firstGShortfall,
      'weeks' => $weeks,
    ];
    $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    $hash = hash('sha256', $payloadJson);
    $now = time();

    $snapshotId = (int) $this->database->insert('brebo_finance_cash_forecast_snapshot')
      ->fields([
        'project_nid' => $projectNid,
        'snapshot_date' => $date,
        'scenario' => $scenario,
        'opening_regular_balance' => $openingRegularBalance,
        'opening_g_account_balance' => $openingGAccountBalance,
        'lowest_regular_balance' => $lowestRegular,
        'lowest_g_account_balance' => $lowestGAccount,
        'first_regular_shortfall_date' => $firstRegularShortfall,
        'first_g_account_shortfall_date' => $firstGShortfall,
        'payload' => $payloadJson,
        'content_hash' => $hash,
        'created' => $now,
        'created_by' => $userId,
      ])
      ->execute();

    $this->database->insert('brebo_finance_audit')
      ->fields([
        'project_nid' => $projectNid,
        'entity_type' => 'cash_forecast_snapshot',
        'entity_id' => $snapshotId,
        'action' => 'snapshot_created',
        'after_hash' => $hash,
        'payload' => json_encode([
          'scenario' => $scenario,
          'event_count' => count($events),
          'first_regular_shortfall_date' => $firstRegularShortfall,
          'first_g_account_shortfall_date' => $firstGShortfall,
        ], JSON_THROW_ON_ERROR),
        'reason' => 'Immutable thirteen-week cash forecast from traceable cash events.',
        'created' => $now,
        'created_by' => $userId,
      ])
      ->execute();

    return $snapshotId;
  }

  /**
   * @param list<string> $statuses
   *
   * @return list<array<string, mixed>>
   */
  private function events(int $projectNid, string $endDate, array $statuses): array {
    return $this->database->select('brebo_finance_cash_event', 'e')
      ->fields('e')
      ->condition('project_nid', $projectNid)
      ->condition('status', $statuses, 'IN')
      ->condition('due_date', $endDate, '<=')
      ->orderBy('due_date')
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  private function validDate(string $date): bool {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== FALSE && $parsed->format('Y-m-d') === $date;
  }

}
