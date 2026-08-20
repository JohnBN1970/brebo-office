<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\Core\Database\Connection;

/** Project-wide availability based only on actual glass stock events. */
final class GlassAvailabilityService {
  private const EVENTS = ['delivered','installed','damaged'];

  public function __construct(
    private readonly Connection $database,
    private readonly GlassPositionRepository $positions,
  ) {}

  /** @param array<string,mixed> $position */
  public function groupKey(array $position): string {
    $data = [
      'width_mm' => (int) ($position['width_mm'] ?? 0),
      'height_mm' => (int) ($position['height_mm'] ?? 0),
      'glass_type' => (string) ($position['glass_type'] ?? ''),
      'composition' => (string) ($position['composition'] ?? ''),
      'safety_class' => (string) ($position['safety_class'] ?? ''),
      'fire_class' => (string) ($position['fire_class'] ?? ''),
      'recommended_glass_ref' => (string) ($position['recommended_glass_ref'] ?? ''),
    ];
    return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
  }

  public function recordForPosition(int $positionId, string $eventType, float $quantity, ?string $reference, ?string $note, int $userId): int {
    if (!in_array($eventType, self::EVENTS, TRUE)) throw new \InvalidArgumentException('Onbekend glasvoorraadevent.');
    if ($quantity <= 0) throw new \InvalidArgumentException('Aantal moet groter dan nul zijn.');
    $position = $this->positions->find($positionId);
    if (!$position) throw new \InvalidArgumentException('Glaspositie bestaat niet.');
    $projectId = (int) ($position['project_nid'] ?? 0);
    if ($projectId <= 0) throw new \RuntimeException('Projectkoppeling is verplicht voor projectbrede glasbeschikbaarheid.');
    if (!$this->database->schema()->tableExists('brebo_glass_stock_event')) throw new \RuntimeException('Glasvoorraadregistratie is nog niet geïnstalleerd.');

    return (int) $this->database->insert('brebo_glass_stock_event')->fields([
      'project_nid' => $projectId,
      'glass_group_key' => $this->groupKey($position),
      'event_type' => $eventType,
      'quantity' => $quantity,
      'source_reference' => trim((string) $reference) ?: NULL,
      'note' => trim((string) $note) ?: NULL,
      'happened_at' => time(),
      'created_by' => $userId,
      'created' => time(),
    ])->execute();
  }

  /** @return array<string,mixed> */
  public function stockForPosition(int $positionId, float $reservedQuantity = 0.0): array {
    $position = $this->positions->find($positionId);
    if (!$position) throw new \InvalidArgumentException('Glaspositie bestaat niet.');
    $projectId = (int) ($position['project_nid'] ?? 0);
    if ($projectId <= 0) throw new \RuntimeException('Projectkoppeling ontbreekt.');
    $key = $this->groupKey($position);
    $totals = ['delivered'=>0.0,'installed'=>0.0,'damaged'=>0.0];
    if ($this->database->schema()->tableExists('brebo_glass_stock_event')) {
      $q = $this->database->select('brebo_glass_stock_event','e');
      $q->addField('e','event_type');
      $q->addExpression('SUM(e.quantity)','total');
      $q->condition('project_nid',$projectId)->condition('glass_group_key',$key)->groupBy('event_type');
      foreach ($q->execute() as $row) if (isset($totals[$row->event_type])) $totals[$row->event_type] = (float) $row->total;
    }
    $reserved = max(0.0, $reservedQuantity);
    $free = max(0.0, $totals['delivered'] - $totals['installed'] - $totals['damaged'] - $reserved);
    return [
      'project_nid'=>$projectId,
      'glass_group_key'=>$key,
      'technical_match'=>TRUE,
      'actually_delivered'=>$totals['delivered'] > 0,
      'delivered_quantity'=>$totals['delivered'],
      'used_quantity'=>$totals['installed'],
      'damaged_quantity'=>$totals['damaged'],
      'reserved_quantity'=>$reserved,
      'free_quantity'=>$free,
      'reference'=>'glass-group:'.$key,
    ];
  }
}
