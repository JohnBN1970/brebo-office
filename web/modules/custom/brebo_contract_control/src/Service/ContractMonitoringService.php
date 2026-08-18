<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\Core\Database\Connection;

/** Monitors supplier obligations from award through final close-out. */
final class ContractMonitoringService {

  public function __construct(private readonly Connection $database) {}

  /** @param array<int, array<string, mixed>> $obligations */
  public function initialize(int $awardId, array $obligations, ?int $now = NULL): array {
    $now ??= time();
    $award = $this->database->select('brebo_procurement_award', 'a')->fields('a')->condition('id', $awardId)->execute()->fetchAssoc();
    if (!$award) {
      throw new \InvalidArgumentException('Onbekende opdrachtverstrekking.');
    }
    $ids = [];
    foreach ($obligations as $item) {
      $type = trim((string) ($item['type'] ?? ''));
      $title = trim((string) ($item['title'] ?? ''));
      if ($type === '' || $title === '') {
        throw new \InvalidArgumentException('Iedere contractverplichting vereist type en title.');
      }
      $ids[] = (int) $this->database->insert('brebo_contract_obligation')->fields([
        'award_id' => $awardId,
        'type' => $type,
        'title' => $title,
        'due_at' => $item['due_at'] ?? NULL,
        'amount' => $item['amount'] ?? NULL,
        'required' => array_key_exists('required', $item) ? (int) (bool) $item['required'] : 1,
        'status' => 'open',
        'created_at' => $now,
      ])->execute();
    }
    return ['award_id' => $awardId, 'obligation_ids' => $ids, 'status' => 'monitoring'];
  }

  public function complete(int $obligationId, string $evidenceRef, ?int $now = NULL): void {
    if (trim($evidenceRef) === '') {
      throw new \InvalidArgumentException('Bewijsreferentie is verplicht om een verplichting af te sluiten.');
    }
    $this->database->update('brebo_contract_obligation')->fields([
      'status' => 'completed', 'evidence_ref' => $evidenceRef, 'completed_at' => $now ?? time(),
    ])->condition('id', $obligationId)->execute();
  }

  /** @return array<string, mixed> */
  public function status(int $awardId, ?int $now = NULL): array {
    $now ??= time();
    $rows = $this->database->select('brebo_contract_obligation', 'o')->fields('o')->condition('award_id', $awardId)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $open = $overdue = $completed = 0;
    $blocking = [];
    foreach ($rows as $row) {
      if ($row['status'] === 'completed') { $completed++; continue; }
      $open++;
      if (!empty($row['due_at']) && (int) $row['due_at'] < $now) {
        $overdue++;
        if ((int) $row['required'] === 1) { $blocking[] = $row; }
      }
    }
    $deviations = $this->database->select('brebo_contract_deviation', 'd')->fields('d')->condition('award_id', $awardId)->condition('status', 'open')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $critical = array_values(array_filter($deviations, static fn(array $d): bool => in_array($d['severity'], ['high', 'critical', 'hoog', 'kritiek'], TRUE)));

    return [
      'award_id' => $awardId,
      'obligations_total' => count($rows), 'open' => $open, 'overdue' => $overdue, 'completed' => $completed,
      'blocking_overdue' => $blocking, 'open_deviations' => $deviations, 'critical_deviations' => $critical,
      'can_close_contract' => $open === 0 && $critical === [],
      'status' => $critical !== [] ? 'critical_attention' : ($overdue > 0 ? 'overdue' : ($open > 0 ? 'monitoring' : 'ready_to_close')),
    ];
  }
}
