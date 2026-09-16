<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_project_cockpit\Unit;

use PHPUnit\Framework\TestCase;

/** Locks the user-facing Project dossier structure to one canonical flow. */
final class ProjectDossierNavigationContractTest extends TestCase {

  public function testCanonicalProjectDossierNavigation(): void {
    $root = dirname(__DIR__);
    $module = file_get_contents($root . '/brebo_project_cockpit.module');
    $controller = file_get_contents($root . '/src/Controller/ProjectCockpitController.php');
    $routing = file_get_contents($root . '/brebo_project_cockpit.routing.yml');

    self::assertIsString($module);
    self::assertIsString($controller);
    self::assertIsString($routing);

    $canonical = [
      'Overzicht' => 'brebo_project_cockpit.overview',
      'Planning' => 'brebo_office_core.project_planning',
      'Documenten' => 'brebo_document_data.project_dossier',
      'Begroting' => 'brebo_project_cockpit.budget',
      'Orders' => 'brebo_project_cockpit.orders',
      'Contracten' => 'brebo_project_cockpit.contracts',
      'Facturen' => 'brebo_project_cockpit.invoices',
      'Inzet' => 'brebo_inzet.project_dashboard',
      'Tekortkomingen' => 'brebo_project_cockpit.shortcomings',
      'Oplevering' => 'brebo_project_cockpit.completion',
    ];

    $offset = -1;
    foreach ($canonical as $label => $route) {
      $needle = "['{$label}', '{$route}'";
      $position = strpos($module, $needle);
      self::assertNotFalse($position, "Canonieke projecttab {$label} ontbreekt of gebruikt niet de echte route {$route}.");
      self::assertGreaterThan($offset, $position, "Projecttab {$label} staat niet in de canonieke dossier-volgorde.");
      $offset = $position;
      self::assertStringContainsString($route . ':', $routing, "Route {$route} ontbreekt uit routing.yml.");
    }

    self::assertStringNotContainsString("['Calculatie',", $module, 'Calculatie mag niet meer als zichtbare projecttab bestaan.');
    self::assertStringNotContainsString("['Inkoop',", $module, 'Inkoop mag niet meer als zichtbare projecttab bestaan.');
    self::assertStringNotContainsString("['Kwaliteit',", $module, 'Kwaliteit mag niet meer als zichtbare projecttab bestaan.');

    foreach ($canonical as $label => $route) {
      $needle = "['{$label}', '{$route}'";
      self::assertStringContainsString($needle, $controller, "Het projectoverzicht gebruikt voor {$label} nog niet dezelfde canonieke route.");
    }
  }

}
