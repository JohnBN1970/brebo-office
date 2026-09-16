<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_project_cockpit\Unit;

use PHPUnit\Framework\TestCase;

/** Locks the canonical project overview to a calm, plain-language surface. */
final class ProjectSteeringCockpitCalmContractTest extends TestCase {

  public function testOverviewKeepsSteeringAndMovesDetailOut(): void {
    $root = dirname(__DIR__, 3);
    $controller = file_get_contents($root . '/src/Controller/CanonicalProjectCockpitController.php');

    self::assertIsString($controller);
    self::assertStringContainsString("unset(\$build['money'], \$build['revenue'], \$build['steering']);", $controller);
    self::assertStringContainsString("\$build['attention']['#title'] = \$this->t('Dit vraagt aandacht');", $controller);
    self::assertStringContainsString("\$build['progress']['#caption'] = \$this->t('Hoe staat het project ervoor?');", $controller);
    self::assertStringContainsString("'Werk gereed'", $controller);
    self::assertStringContainsString("'Voor of achter op planning'", $controller);
    self::assertStringContainsString("'Verwachte marge bij oplevering'", $controller);
    self::assertStringContainsString("['planning', 'finance', 'edit']", $controller);
    self::assertStringNotContainsString("['clock', 'workforce']", $controller);
  }

}
