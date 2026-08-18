<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_office_core\Unit;

use Drupal\brebo_office_core\Service\ProjectScheduleCalculator;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\brebo_office_core\Service\ProjectScheduleCalculator
 */
final class ProjectScheduleCalculatorTest extends TestCase {

  private ProjectScheduleCalculator $calculator;

  protected function setUp(): void {
    parent::setUp();
    $this->calculator = new ProjectScheduleCalculator();
  }

  public function testFinishStartSkipsWeekend(): void {
    $result = $this->calculator->calculate([
      1 => $this->activity(1, 'A', '2026-08-17', '2026-08-21', 5),
      2 => $this->activity(2, 'B', '2026-08-21', '2026-08-24', 2, [1], 'FS'),
    ]);
    self::assertSame([], $result['errors']);
    self::assertSame('2026-08-24', $result['activities'][2]['proposed_start']);
    self::assertSame('2026-08-25', $result['activities'][2]['proposed_end']);
  }

  public function testLatestOfMultiplePredecessorsControlsStart(): void {
    $result = $this->calculator->calculate([
      1 => $this->activity(1, 'A', '2026-08-17', '2026-08-18', 2),
      2 => $this->activity(2, 'B', '2026-08-17', '2026-08-20', 4),
      3 => $this->activity(3, 'C', '2026-08-19', '2026-08-21', 3, [1, 2], 'FS'),
    ]);
    self::assertSame('2026-08-21', $result['activities'][3]['proposed_start']);
    self::assertSame('2026-08-25', $result['activities'][3]['proposed_end']);
  }

  public function testStartStartRelation(): void {
    $result = $this->calculator->calculate([
      1 => $this->activity(1, 'A', '2026-08-17', '2026-08-21', 5),
      2 => $this->activity(2, 'B', '2026-08-18', '2026-08-19', 2, [1], 'SS', 1),
    ]);
    self::assertSame('2026-08-18', $result['activities'][2]['proposed_start']);
    self::assertSame('2026-08-19', $result['activities'][2]['proposed_end']);
  }

  public function testCycleBlocksCalculation(): void {
    $result = $this->calculator->calculate([
      1 => $this->activity(1, 'A', '2026-08-17', '2026-08-18', 2, [2]),
      2 => $this->activity(2, 'B', '2026-08-19', '2026-08-20', 2, [1]),
    ]);
    self::assertNotEmpty($result['errors']);
    self::assertStringContainsString('Cyclische planningsrelatie', $result['errors'][0]);
  }

  private function activity(
    int $id,
    string $label,
    string $start,
    string $end,
    int $duration,
    array $predecessors = [],
    string $relation = 'FS',
    int $lag = 0,
  ): array {
    return compact('id', 'label', 'start', 'end', 'duration', 'predecessors', 'relation', 'lag');
  }

}
