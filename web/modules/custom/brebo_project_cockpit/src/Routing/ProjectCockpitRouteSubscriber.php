<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/** Makes the proven canonical cockpit the main project overview. */
final class ProjectCockpitRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    foreach (['brebo_project_cockpit.overview', 'brebo_office_core.project_dashboard'] as $routeName) {
      $route = $collection->get($routeName);
      if ($route === NULL) {
        continue;
      }

      $route->setDefault('_controller', '\\Drupal\\brebo_project_cockpit\\Controller\\CanonicalProjectCockpitController::overview');
      $route->setDefault('_title_callback', '\\Drupal\\brebo_project_cockpit\\Controller\\CanonicalProjectCockpitController::title');
    }
  }

}
