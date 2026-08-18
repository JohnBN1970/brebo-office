<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\Core\Database\Connection;

/** Computes operational urgency for glass positions from BREBO Office state. */
final class GlassOperationalRiskEvaluator {
  public function __construct(private readonly Connection $database) {}

  /** @param array<string,mixed> $position @return array{score:int,level:string,reasons:array<int,string>} */
  public function evaluate(array $position): array {
    $score = 0;
    $reasons = [];
    $state = (string) ($position['technical_status'] ?? 'concept');
    $id = (int) ($position['id'] ?? 0);

    if (($position['technical_check_state'] ?? '') === 'blocked') {
      $score += 100;
      $reasons[] = 'Technisch geblokkeerd';
    }
    elseif (($position['technical_check_state'] ?? '') === 'expert_review') {
      $score += 70;
      $reasons[] = 'Deskundige beoordeling nodig';
    }

    if ($state === 'measured') {
      $score += 45;
      $reasons[] = 'Wacht op technische vrijgave';
    }
    if ($state === 'approved' && !$this->hasProcurementRequest($id)) {
      $score += 40;
      $reasons[] = 'Vrijgegeven maar nog niet naar inkoop';
    }
    if ($state === 'ordered') {
      $delivery = $this->deliveryStatus($id);
      if ($delivery['late']) {
        $score += 90;
        $reasons[] = 'Levering te laat sinds ' . $delivery['date'];
      }
      elseif ($delivery['today']) {
        $score += 35;
        $reasons[] = 'Levering vandaag verwacht';
      }
      elseif ($delivery['date'] === NULL) {
        $score += 50;
        $reasons[] = 'Besteld zonder bevestigde leverdatum';
      }
    }
    if ($state === 'delivered') {
      $score += 25;
      $reasons[] = 'Geleverd en klaar voor montage';
    }

    $level = $score >= 80 ? 'kritiek' : ($score >= 40 ? 'aandacht' : ($score > 0 ? 'actie' : 'normaal'));
    return ['score' => $score, 'level' => $level, 'reasons' => $reasons];
  }

  private function hasProcurementRequest(int $positionId): bool {
    if (!$this->database->schema()->tableExists('brebo_procurement_request_line')) return FALSE;
    return (bool) $this->database->select('brebo_procurement_request_line', 'l')
      ->condition('source_domain', 'brebo_glass_position')
      ->condition('source_reference', (string) $positionId)
      ->countQuery()->execute()->fetchField();
  }

  /** @return array{date:?string,late:bool,today:bool} */
  private function deliveryStatus(int $positionId): array {
    $result = ['date' => NULL, 'late' => FALSE, 'today' => FALSE];
    if (!$this->database->schema()->tableExists('brebo_procurement_order')) return $result;
    $query = $this->database->select('brebo_procurement_request_line', 'l');
    $query->innerJoin('brebo_procurement_order', 'o', 'o.request_id = l.request_id');
    $query->addField('o', 'expected_delivery_date');
    $query->condition('l.source_domain', 'brebo_glass_position')->condition('l.source_reference', (string) $positionId)->condition('o.status', 'ordered');
    $query->orderBy('o.id', 'DESC')->range(0, 1);
    $date = $query->execute()->fetchField();
    if (!$date) return $result;
    $today = date('Y-m-d');
    return ['date' => (string) $date, 'late' => (string) $date < $today, 'today' => (string) $date === $today];
  }
}
