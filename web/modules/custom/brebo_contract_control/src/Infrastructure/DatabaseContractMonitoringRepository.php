<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\ContractMonitoringRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for contract monitoring persistence. */
final class DatabaseContractMonitoringRepository implements ContractMonitoringRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  /** @return array<string, mixed>|null */
  public function findAward(int $awardId): ?array {
    $record = $this->database
      ->select('brebo_procurement_award', 'a')
      ->fields('a')
      ->condition('id', $awardId)
      ->execute()
      ->fetchAssoc();

    return $record ?: NULL;
  }

  /** @param array<string, mixed> $record */
  public function insertObligation(array $record): int {
    return (int) $this->database
      ->insert('brebo_contract_obligation')
      ->fields($record)
      ->execute();
  }

  /** @param array<string, mixed> $fields */
  public function completeObligation(int $obligationId, array $fields): void {
    $this->database
      ->update('brebo_contract_obligation')
      ->fields($fields)
      ->condition('id', $obligationId)
      ->execute();
  }

  /** @return array<int, array<string, mixed>> */
  public function findObligationsByAward(int $awardId): array {
    return $this->database
      ->select('brebo_contract_obligation', 'o')
      ->fields('o')
      ->condition('award_id', $awardId)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @return array<int, array<string, mixed>> */
  public function findOpenDeviationsByAward(int $awardId): array {
    return $this->database
      ->select('brebo_contract_deviation', 'd')
      ->fields('d')
      ->condition('award_id', $awardId)
      ->condition('status', 'open')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

}
