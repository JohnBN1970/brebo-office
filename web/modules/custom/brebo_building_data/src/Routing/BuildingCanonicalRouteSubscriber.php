<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/** Makes the normal building dossier URL the canonical truth workbench. */
final class BuildingCanonicalRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('brebo_office_core.building_dashboard');
    if ($route === NULL) {
      return;
    }

    $route->setDefault('_controller', '\\Drupal\\brebo_building_data\\Controller\\BuildingTruthWorkbenchController::workbench');
    $route->setDefault('_title_callback', '\\Drupal\\brebo_building_data\\Controller\\BuildingTruthWorkbenchController::title');
    $route->setOption('no_cache', TRUE);
  }

}
