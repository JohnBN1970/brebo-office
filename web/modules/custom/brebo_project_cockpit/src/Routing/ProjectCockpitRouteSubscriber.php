<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/** Makes the canonical cockpit the BREBO project detail page. */
final class ProjectCockpitRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    foreach (['brebo_office_core.project_dashboard', 'brebo_project_cockpit.overview'] as $route_name) {
      $route = $collection->get($route_name);
      if ($route === NULL) {
        continue;
      }

      $route->setDefault('_controller', '\\Drupal\\brebo_project_cockpit\\Controller\\CanonicalProjectCockpitController::overview');
      $route->setDefault('_title_callback', '\\Drupal\\brebo_project_cockpit\\Controller\\CanonicalProjectCockpitController::title');
    }
  }

}
