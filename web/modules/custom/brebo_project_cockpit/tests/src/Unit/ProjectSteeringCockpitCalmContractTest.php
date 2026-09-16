<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_project_cockpit\Unit;

use PHPUnit\Framework\TestCase;

/** Locks the canonical project overview to a calm steering-only surface. */
final class ProjectSteeringCockpitCalmContractTest extends TestCase {

  public function testOverviewKeepsSteeringAndMovesDetailOut(): void {
    $root = dirname(__DIR__, 3);
    $controller = file_get_contents($root . '/src/Controller/CanonicalProjectCockpitController.php');

    self::assertIsString($controller);
    self::assertStringContainsString("unset($build['money'], $build['revenue'], $build['steering']);", $controller);
    self::assertStringContainsString("$build['attention']['#title'] = $this->t('Aandacht');", $controller);
    self::assertStringContainsString("$build['progress']['#caption'] = $this->t('Kernvoortgang');", $controller);
    self::assertStringContainsString("['planning', 'finance', 'edit']", $controller);
    self::assertStringNotContainsString("['clock', 'workforce']", $controller);
  }

}
