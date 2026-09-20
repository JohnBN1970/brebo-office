<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_project_cockpit\Unit;

use PHPUnit\Framework\TestCase;

/** Locks the user-facing Project dossier structure to one canonical flow. */
final class ProjectDossierNavigationContractTest extends TestCase {

  public function testCanonicalProjectDossierNavigation(): void {
    $root = dirname(__DIR__, 3);
    $module = file_get_contents($root . '/brebo_project_cockpit.module');
    $controller = file_get_contents($root . '/src/Controller/CanonicalProjectCockpitController.php');
    $subscriber = file_get_contents($root . '/src/Routing/ProjectCockpitRouteSubscriber.php');

    self::assertIsString($module);
    self::assertIsString($controller);
    self::assertIsString($subscriber);

    $routingByOwner = [
      'brebo_project_cockpit' => file_get_contents($root . '/brebo_project_cockpit.routing.yml'),
      'brebo_office_core' => file_get_contents($root . '/../brebo_office_core/brebo_office_core.routing.yml'),
      'brebo_document_data' => file_get_contents($root . '/../brebo_document_data/brebo_document_data.routing.yml'),
      'brebo_inzet' => file_get_contents($root . '/../brebo_inzet/brebo_inzet.routing.yml'),
    ];
    foreach ($routingByOwner as $routing) {
      self::assertIsString($routing);
    }

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

      $owner = strstr($route, '.', TRUE);
      self::assertIsString($owner);
      self::assertArrayHasKey($owner, $routingByOwner, "Geen routingbron vastgelegd voor {$route}.");
      self::assertStringContainsString($route . ':', $routingByOwner[$owner], "Route {$route} ontbreekt uit routing van {$owner}.");
    }

    self::assertStringNotContainsString("['Calculatie',", $module);
    self::assertStringNotContainsString("['Inkoop',", $module);
    self::assertStringNotContainsString("['Kwaliteit',", $module);
    self::assertStringContainsString('_brebo_project_cockpit_tabs(', $controller);
    self::assertStringContainsString('CanonicalProjectCockpitController::overview', $subscriber);
    self::assertStringContainsString('CanonicalProjectCockpitController::title', $subscriber);
  }

}
