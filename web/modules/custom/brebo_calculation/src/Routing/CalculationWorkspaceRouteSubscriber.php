<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Keeps the legacy Office calculation URL as a compatibility redirect.
 */
final class CalculationWorkspaceRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('brebo_office_core.calculation_dashboard');
    if ($route === NULL) {
      return;
    }

    $defaults = $route->getDefaults();
    unset($defaults['_controller'], $defaults['_form'], $defaults['_title_callback']);
    $defaults['_controller'] = '\\Drupal\\brebo_calculation\\Controller\\CalculationWorkspaceRedirectController::redirect';
    $defaults['_title'] = 'Calculatiewerkbank';
    $route->setDefaults($defaults);

    $route->setRequirement('_permission', 'edit brebo calculation workbench');
    $route->setOption('parameters', []);
  }

}
