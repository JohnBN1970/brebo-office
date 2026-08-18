<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Persists and retrieves glass positions in the canonical object structure.
 */
final class GlassPositionRepository {

  private const SORT_COLUMNS = [
    'position' => 'position_code',
    'location' => 'location',
    'glass_type' => 'glass_type',
    'area' => 'area_m2',
    'weight' => 'estimated_weight_kg',
    'status' => 'technical_status',
    'changed' => 'changed',
  ];

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly GlassApprovalPolicy $approvalPolicy,
  ) {}

  /**
   * @param array<string, mixed> $values
   */
  public function insert(array $values): int {
    $this->assertNodeBundle((int) $values['building_nid'], 'brebo_building', 'gebouw');
    if (!empty($values['project_nid'])) {
      $this->assertNodeBundle((int) $values['project_nid'], 'brebo_project', 'project');
    }

    $now = $this->time->getRequestTime();
    $values += ['created' => $now, 'changed' => $now];

    return (int) $this->database->insert('brebo_glass_position')
      ->fields($values)
      ->execute();
  }

  /**
   * Returns a bounded, filterable glass schedule.
   *
   * @return array<int, array<string, mixed>>
   */
  public function findAll(string $search = '', string $status = '', string $sort = 'changed', string $direction = 'desc'): array {
    $query = $this->database->select('brebo_glass_position', 'g')
      ->fields('g');

    if ($status !== '') {
      $query->condition('g.technical_status', $status);
    }

    $search = trim($search);
    if ($search !== '') {
      $group = $query->orConditionGroup()
        ->condition('g.position_code', '%' . $this->database->escapeLike($search) . '%', 'LIKE')
        ->condition('g.location', '%' . $this->database->escapeLike($search) . '%', 'LIKE')
        ->condition('g.composition', '%' . $this->database->escapeLike($search) . '%', 'LIKE');
      $query->condition($group);
    }

    $column = self::SORT_COLUMNS[$sort] ?? self::SORT_COLUMNS['changed'];
    $order = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

    return $query
      ->orderBy('g.' . $column, $order)
      ->range(0, 250)
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

  /**
   * @return array<string, int>
   */
  public function countByStatus(): array {
    $query = $this->database->select('brebo_glass_position', 'g');
    $query->addField('g', 'technical_status', 'status');
    $query->addExpression('COUNT(*)', 'total');
    $query->groupBy('g.technical_status');

    $counts = ['all' => 0];
    foreach ($query->execute() as $row) {
      $counts[(string) $row->status] = (int) $row->total;
      $counts['all'] += (int) $row->total;
    }
    return $counts;
  }

  /**
   * @return array<string, mixed>|null
   */
  public function find(int $id): ?array {
    $record = $this->database->select('brebo_glass_position', 'g')
      ->fields('g')
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();
    return $record ?: NULL;
  }

  public function approve(int $id, int $userId, string $reference, string $note): void {
    $position = $this->find($id);
    if (!$position) {
      throw new \InvalidArgumentException('Glaspositie bestaat niet.');
    }
    if ((string) $position['technical_status'] === 'approved') {
      throw new \InvalidArgumentException('Glaspositie is al technisch vrijgegeven.');
    }
    $policy = $this->approvalPolicy->evaluate($position);
    if (!$policy['allowed']) {
      throw new \InvalidArgumentException(implode(' ', $policy['issues']));
    }
    if (trim($reference) === '' || trim($note) === '') {
      throw new \InvalidArgumentException('Vrijgavereferentie en motivatie zijn verplicht.');
    }

    $checksumData = [
      'position_code' => $position['position_code'],
      'application_type' => $position['application_type'],
      'composition' => $position['composition'],
      'width_mm' => $position['width_mm'],
      'height_mm' => $position['height_mm'],
      'quantity' => $position['quantity'],
      'design_wind_pressure_kpa' => $position['design_wind_pressure_kpa'],
      'glass_wind_resistance_kpa' => $position['glass_wind_resistance_kpa'],
      'wind_utilization' => $position['wind_utilization'],
      'wind_standard_ref' => $position['wind_standard_ref'],
      'wind_calculation_ref' => $position['wind_calculation_ref'],
      'recommended_glass_ref' => $position['recommended_glass_ref'],
    ];
    $checksum = hash('sha256', json_encode($checksumData, JSON_THROW_ON_ERROR));
    $now = $this->time->getRequestTime();

    $affected = $this->database->update('brebo_glass_position')
      ->fields([
        'technical_status' => 'approved',
        'approved_by' => $userId,
        'approved_at' => $now,
        'approval_note' => trim($note),
        'approval_reference' => trim($reference),
        'approval_checksum' => $checksum,
        'changed' => $now,
      ])
      ->condition('id', $id)
      ->condition('technical_status', 'measured')
      ->condition('approved_at', NULL, 'IS NULL')
      ->execute();

    if ($affected !== 1) {
      throw new \RuntimeException('Vrijgave is niet opgeslagen; de positie is gelijktijdig gewijzigd.');
    }
  }

  private function assertNodeBundle(int $nid, string $bundle, string $label): void {
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node || $node->bundle() !== $bundle) {
      throw new \InvalidArgumentException(sprintf('Het gekozen %s is geen geldig BREBO-object.', $label));
    }
  }

}
