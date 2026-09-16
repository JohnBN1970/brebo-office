<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_project_cockpit\Unit;

use PHPUnit\Framework\TestCase;

/** Locks the canonical project overview to a calm, plain-language surface. */
final class ProjectSteeringCockpitCalmContractTest extends TestCase {

  public function testOverviewKeepsSteeringAndMovesDetailOut(): void {
    $root = dirname(__DIR__, 3);
    $controller = file_get_contents($root . '/src/Controller/CanonicalProjectCockpitController.php');
    $officeCalm = file_get_contents(dirname($root, 3) . '/themes/custom/brebo_office/css/office-calm.css');

    self::assertIsString($controller);
    self::assertIsString($officeCalm);
    self::assertStringContainsString("unset(\$build['money'], \$build['revenue'], \$build['steering']);", $controller);
    self::assertStringContainsString("\$build['attention']['#title'] = \$this->t('Dit vraagt aandacht');", $controller);
    self::assertStringContainsString("\$build['progress']['#caption'] = \$this->t('Hoe staat het project ervoor?');", $controller);
    self::assertStringContainsString("'Werk gereed'", $controller);
    self::assertStringContainsString("'Voor of achter op planning'", $controller);
    self::assertStringContainsString("'Verwachte marge bij oplevering'", $controller);
    self::assertStringContainsString("['planning', 'finance', 'edit']", $controller);
    self::assertStringContainsString("\$class !== 'brebo-list-actions'", $controller);
    self::assertStringContainsString("'brebo-project-cockpit__primary-actions'", $controller);
    self::assertStringContainsString("\$build['quick_actions']['planning']['#attributes']['class'][] = 'button--primary';", $controller);
    self::assertStringContainsString('.brebo-project-cockpit__primary-actions', $officeCalm);
    self::assertStringNotContainsString("['clock', 'workforce']", $controller);
  }

}
