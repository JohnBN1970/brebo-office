<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/** Makes the new cockpit the canonical BREBO project detail page. */
final class ProjectCockpitRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('brebo_office_core.project_dashboard');
    if ($route === NULL) {
      return;
    }

    $route->setDefault('_controller', '\Drupal\brebo_project_cockpit\Controller\ProjectCockpitController::overview');
    $route->setDefault('_title_callback', '\Drupal\brebo_project_cockpit\Controller\ProjectCockpitController::title');
  }

}
