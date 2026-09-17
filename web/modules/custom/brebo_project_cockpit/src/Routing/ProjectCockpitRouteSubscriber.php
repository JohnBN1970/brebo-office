<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/** Keeps the experimental cockpit on its own route. */
final class ProjectCockpitRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('brebo_project_cockpit.overview');
    if ($route === NULL) {
      return;
    }

    $route->setDefault('_controller', '\\Drupal\\brebo_project_cockpit\\Controller\\CanonicalProjectCockpitController::overview');
    $route->setDefault('_title_callback', '\\Drupal\\brebo_project_cockpit\\Controller\\CanonicalProjectCockpitController::title');
  }

}
