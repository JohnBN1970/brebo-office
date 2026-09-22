<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Routing;

use Symfony\Component\Routing\RouteCollection;
use Drupal\Core\Routing\RouteSubscriberBase;

/**
 * Keeps the public Office calculation detail URL as the canonical workbench.
 *
 * The old Office controller still contains legacy calculation dashboard code,
 * but users should never land on that parallel calculation truth.
 */
final class CalculationWorkspaceRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('brebo_office_core.calculation_dashboard');
    if ($route === NULL) {
      return;
    }

    $defaults = $route->getDefaults();
    unset($defaults['_controller'], $defaults['_title_callback']);
    $defaults['_form'] = '\\Drupal\\brebo_calculation\\Form\\CalculationWorkbenchForm';
    $defaults['_title'] = 'Calculatiewerkbank';
    $route->setDefaults($defaults);

    // Preserve normal node access for the Office URL. The workbench itself
    // determines whether the current user may edit or only view the version.
    $route->setRequirement('_entity_access', 'node.view');
    $route->setOption('parameters', [
      'node' => ['type' => 'entity:node'],
    ]);
  }

}
