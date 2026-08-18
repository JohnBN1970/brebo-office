<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use UnexpectedValueException;

/** Resolves monetary exposure for gate exceptions from blocking findings. */
final class FinancialGateExposureResolver {

  public function __construct(private readonly Connection $database) {}

  /**
   * @param list<int> $findingIds
   * @return array{exposure_amount:string, finding_exposures:list<array<string,mixed>>, unresolved:list<int>}
   */
  public function resolve(int $projectNid, array $findingIds): array {
    $findingIds = array_values(array_unique(array_map('intval', $findingIds)));
    if ($findingIds === []) {
      return ['exposure_amount' => '0.00', 'finding_exposures' => [], 'unresolved' => []];
    }

    $rows = $this->database->select('brebo_finance_control_finding', 'f')
      ->fields('f', ['id', 'project_nid', 'control_code', 'source_type', 'source_id', 'payload'])
      ->condition('project_nid', $projectNid)
      ->condition('id', $findingIds, 'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if (count($rows) !== count($findingIds)) {
      throw new UnexpectedValueException('One or more financial findings do not belong to this project.');
    }

    $totalCents = 0;
    $resolved = [];
    $unresolved = [];
    foreach ($rows as $row) {
      $amount = $this->extractAmount((string) ($row['payload'] ?? ''));
      if ($amount === NULL) {
        $unresolved[] = (int) $row['id'];
        $resolved[] = [
          'finding_id' => (int) $row['id'],
          'control_code' => (string) $row['control_code'],
          'amount' => NULL,
          'source' => 'unresolved',
        ];
        continue;
      }
      $cents = $this->toCents($amount);
      $totalCents += abs($cents);
      $resolved[] = [
        'finding_id' => (int) $row['id'],
        'control_code' => (string) $row['control_code'],
        'amount' => $this->fromCents(abs($cents)),
        'source' => 'finding_payload',
      ];
    }

    return [
      'exposure_amount' => $this->fromCents($totalCents),
      'finding_exposures' => $resolved,
      'unresolved' => $unresolved,
    ];
  }

  private function extractAmount(string $payloadJson): ?string {
    if ($payloadJson === '') return NULL;
    try {
      $payload = json_decode($payloadJson, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      return NULL;
    }
    if (!is_array($payload)) return NULL;

    foreach ([
      'financial_exposure_ex_vat',
      'forecast_impact_ex_vat',
      'amount_ex_vat',
      'amount_inc_vat',
      'delayed_receipts_inc_vat',
      'lowest_balance',
    ] as $key) {
      if (isset($payload[$key]) && is_scalar($payload[$key])) {
        $value = str_replace(',', '.', trim((string) $payload[$key]));
        if (preg_match('/^-?(0|[1-9][0-9]*)(\.[0-9]{1,4})?$/', $value) === 1) return $value;
      }
    }
    return NULL;
  }

  private function toCents(string $amount): int {
    $negative = str_starts_with($amount, '-');
    $amount = ltrim($amount, '-');
    [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
    $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);
    $cents = ((int) $whole * 100) + (int) $fraction;
    return $negative ? -$cents : $cents;
  }

  private function fromCents(int $cents): string {
    return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
  }
}
