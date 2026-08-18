<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_office_core\Unit;

use Drupal\brebo_office_core\Service\PersonnelBudgetControl;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\brebo_office_core\Service\PersonnelBudgetControl
 */
final class PersonnelBudgetControlTest extends TestCase {

  private PersonnelBudgetControl $control;

  protected function setUp(): void {
    parent::setUp();
    $this->control = new PersonnelBudgetControl();
  }

  public function testBalancedAllocationIsAccepted(): void {
    $result = $this->control->analyze(
      [10 => ['budget_hours' => 80, 'budget_actual_hours' => 0]],
      [
        1 => ['budget_line_id' => 10, 'planned_hours' => 40, 'actual_hours' => 0],
        2 => ['budget_line_id' => 10, 'planned_hours' => 40, 'actual_hours' => 0],
      ],
    );

    self::assertSame('Akkoord', $result['lines'][10]['control_status']);
    self::assertSame(80.0, $result['lines'][10]['allocated_hours']);
    self::assertSame(0.0, $result['lines'][10]['remaining_to_allocate']);
  }

  public function testOverplanningIsBlocked(): void {
    $result = $this->control->analyze(
      [10 => ['budget_hours' => 80, 'budget_actual_hours' => 0]],
      [1 => ['budget_line_id' => 10, 'planned_hours' => 88, 'actual_hours' => 0]],
    );

    self::assertSame('Blokkade', $result['lines'][10]['control_status']);
    self::assertSame(-8.0, $result['lines'][10]['remaining_to_allocate']);
  }

  public function testUnderplanningWarns(): void {
    $result = $this->control->analyze(
      [10 => ['budget_hours' => 80, 'budget_actual_hours' => 0]],
      [1 => ['budget_line_id' => 10, 'planned_hours' => 64, 'actual_hours' => 0]],
    );

    self::assertSame('Waarschuwing', $result['lines'][10]['control_status']);
    self::assertSame(16.0, $result['lines'][10]['remaining_to_allocate']);
  }

  public function testActualOverrunIsBlocked(): void {
    $result = $this->control->analyze(
      [10 => ['budget_hours' => 80, 'budget_actual_hours' => 84]],
      [1 => ['budget_line_id' => 10, 'planned_hours' => 80, 'actual_hours' => 82]],
    );

    self::assertSame('Blokkade', $result['lines'][10]['control_status']);
    self::assertSame(-4.0, $result['lines'][10]['remaining_after_actual']);
  }

  public function testUnlinkedAssignmentIsBlocked(): void {
    $result = $this->control->analyze(
      [],
      [1 => ['budget_line_id' => NULL, 'planned_hours' => 8, 'actual_hours' => 0]],
    );

    self::assertSame('Blokkade', $result['unlinked'][1]['control_status']);
  }

}
