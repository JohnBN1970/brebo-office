<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\brebo_glass\Contract\GlassPositionPersistenceInterface;

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
    private readonly GlassPositionPersistenceInterface $persistence,
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

    $now = $this->persistence->currentTime();
    $values += ['created' => $now, 'changed' => $now];

    return $this->persistence->insert($values);
  }

  /**
   * Returns a bounded, filterable glass schedule.
   *
   * @return array<int, array<string, mixed>>
   */
  public function findAll(string $search = '', string $status = '', string $sort = 'changed', string $direction = 'desc'): array {
    $column = self::SORT_COLUMNS[$sort] ?? self::SORT_COLUMNS['changed'];
    $order = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';
    return $this->persistence->findAll(trim($search), $status, $column, $order, 250);
  }

  /**
   * @return array<string, int>
   */
  public function countByStatus(): array {
    return $this->persistence->countByStatus();
  }

  /**
   * @return array<string, mixed>|null
   */
  public function find(int $id): ?array {
    return $this->persistence->find($id);
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
    $now = $this->persistence->currentTime();

    $affected = $this->persistence->approveMeasured($id, [
      'technical_status' => 'approved',
      'approved_by' => $userId,
      'approved_at' => $now,
      'approval_note' => trim($note),
      'approval_reference' => trim($reference),
      'approval_checksum' => $checksum,
      'changed' => $now,
    ]);

    if ($affected !== 1) {
      throw new \RuntimeException('Vrijgave is niet opgeslagen; de positie is gelijktijdig gewijzigd.');
    }
  }

  private function assertNodeBundle(int $nid, string $bundle, string $label): void {
    if (!$this->persistence->isNodeBundle($nid, $bundle)) {
      throw new \InvalidArgumentException(sprintf('Het gekozen %s is geen geldig BREBO-object.', $label));
    }
  }
}
