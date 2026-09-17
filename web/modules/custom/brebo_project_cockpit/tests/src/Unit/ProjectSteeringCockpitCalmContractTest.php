<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_project_cockpit\Unit;

use PHPUnit\Framework\TestCase;

/** Locks the canonical project overview to a calm, plain-language surface. */
final class ProjectSteeringCockpitCalmContractTest extends TestCase {

  public function testOverviewKeepsSteeringAndMovesDetailOut(): void {
    $root = dirname(__DIR__, 3);
    $controller = file_get_contents($root . '/src/Controller/CanonicalProjectCockpitController.php');
    $richCss = file_get_contents($root . '/css/project-cockpit-rich.css');

    self::assertIsString($controller);
    self::assertIsString($richCss);
    self::assertStringContainsString("unset(\$build['money'], \$build['revenue'], \$build['steering'], \$build['quick_actions']);", $controller);
    self::assertStringContainsString("\$build['attention']['#title'] = \$this->t('Dit vraagt aandacht');", $controller);
    self::assertStringContainsString("\$this->t('Voortgang')", $controller);
    self::assertStringContainsString("\$this->t('Tijdpad')", $controller);
    self::assertStringContainsString("\$this->t('Uitgevoerde kosten')", $controller);
    self::assertStringContainsString("\$this->t('Verwacht resultaat')", $controller);
    self::assertStringContainsString("\$this->t('Planning & voortgang')", $controller);
    self::assertStringContainsString("\$this->t('Projectorganisatie')", $controller);
    self::assertStringContainsString("field_brebo_project_manager", $controller);
    self::assertStringContainsString("Nog niet toegewezen", $controller);
    self::assertStringContainsString("getRoutesByNames(['brebo_document_data.project_dossier'])", $controller);
    self::assertStringContainsString("->count() > 0", $controller);
    self::assertStringContainsString('brebo-project-dashboard__steering', $richCss);
    self::assertStringContainsString('var(--brebo-text-secondary, var(--brebo-muted', $richCss);
    self::assertStringNotContainsString("['clock', 'workforce']", $controller);
  }

}
