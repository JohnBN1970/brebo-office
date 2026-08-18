<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/** Stores and compares supplier offers for procurement requests. */
final class ProcurementOfferManager {
  public function __construct(private readonly Connection $database) {}

  /** @param array<string,mixed> $values */
  public function create(int $requestId, array $values, AccountInterface $account): int {
    $request = $this->database->select('brebo_procurement_request', 'r')->fields('r')->condition('id', $requestId)->execute()->fetchAssoc();
    if (!$request) throw new \InvalidArgumentException('Leveranciersaanvraag bestaat niet.');
    $supplierName = trim((string) ($values['supplier_name'] ?? ''));
    if ($supplierName === '') throw new \InvalidArgumentException('Leverancier is verplicht.');
    $total = (float) ($values['quoted_total'] ?? 0);
    if ($total < 0) throw new \InvalidArgumentException('Offertebedrag mag niet negatief zijn.');
    $now = time();
    return (int) $this->database->insert('brebo_procurement_offer')->fields([
      'request_id' => $requestId,
      'supplier_ref' => trim((string) ($values['supplier_ref'] ?? '')) ?: NULL,
      'supplier_name' => $supplierName,
      'offer_number' => trim((string) ($values['offer_number'] ?? '')) ?: NULL,
      'offer_date' => trim((string) ($values['offer_date'] ?? '')) ?: NULL,
      'valid_until' => trim((string) ($values['valid_until'] ?? '')) ?: NULL,
      'quoted_total' => $total,
      'currency' => strtoupper(trim((string) ($values['currency'] ?? 'EUR')) ?: 'EUR'),
      'delivery_date' => trim((string) ($values['delivery_date'] ?? '')) ?: NULL,
      'lead_time_days' => ($values['lead_time_days'] ?? '') !== '' ? (int) $values['lead_time_days'] : NULL,
      'technical_deviation' => trim((string) ($values['technical_deviation'] ?? '')) ?: NULL,
      'conditions_summary' => trim((string) ($values['conditions_summary'] ?? '')) ?: NULL,
      'status' => 'received',
      'created' => $now,
      'created_by' => (int) $account->id(),
      'changed' => $now,
    ])->execute();
  }

  /** @return array<int,array<string,mixed>> */
  public function compare(int $requestId): array {
    $rows = $this->database->select('brebo_procurement_offer', 'o')->fields('o')->condition('request_id', $requestId)->orderBy('quoted_total', 'ASC')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $requested = $this->database->select('brebo_procurement_request', 'r')->fields('r', ['requested_delivery_date'])->condition('id', $requestId)->execute()->fetchField();
    foreach ($rows as &$row) {
      $row['technical_ok'] = trim((string) ($row['technical_deviation'] ?? '')) === '';
      $row['delivery_ok'] = !$requested || !$row['delivery_date'] || (string) $row['delivery_date'] <= (string) $requested;
      $row['score'] = 0;
      if ($row['technical_ok']) $row['score'] += 60;
      if ($row['delivery_ok']) $row['score'] += 20;
      $row['score'] += 20;
    }
    unset($row);
    usort($rows, static fn(array $a, array $b): int => [$b['score'], $a['quoted_total']] <=> [$a['score'], $b['quoted_total']]);
    return $rows;
  }

  public function selectWinner(int $requestId, int $offerId, AccountInterface $account): void {
    $offer = $this->database->select('brebo_procurement_offer', 'o')->fields('o')->condition('id', $offerId)->condition('request_id', $requestId)->execute()->fetchAssoc();
    if (!$offer) throw new \InvalidArgumentException('Offerte hoort niet bij deze aanvraag.');
    if (trim((string) ($offer['technical_deviation'] ?? '')) !== '') throw new \RuntimeException('Een offerte met technische afwijking kan niet zonder inhoudelijke vrijgave als winnaar worden gekozen.');
    $transaction = $this->database->startTransaction();
    try {
      $this->database->update('brebo_procurement_offer')->fields(['status' => 'rejected', 'changed' => time()])->condition('request_id', $requestId)->execute();
      $this->database->update('brebo_procurement_offer')->fields(['status' => 'selected', 'changed' => time(), 'selected_by' => (int) $account->id(), 'selected_at' => time()])->condition('id', $offerId)->execute();
      $this->database->update('brebo_procurement_request')->fields(['status' => 'offer_selected', 'supplier_ref' => $offer['supplier_ref'], 'supplier_name' => $offer['supplier_name'], 'changed' => time()])->condition('id', $requestId)->execute();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }
}
