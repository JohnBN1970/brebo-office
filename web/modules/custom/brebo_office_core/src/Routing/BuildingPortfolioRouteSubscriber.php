<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Replaces the generic building list with the building portfolio UI.
 */
final class BuildingPortfolioRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('brebo_office_core.buildings')) {
      $route->setDefault('_controller', '\\Drupal\\brebo_office_core\\Controller\\BuildingPortfolioController::overview');
      $route->setDefault('_title', 'Gebouwenkaartotheek');
      $route->setDefault('bundle', NULL);
    }

    $moveRoute = new Route(
      '/gebouwen/kanban/verplaatsen',
      ['_controller' => '\\Drupal\\brebo_office_core\\Controller\\BuildingPortfolioController::move'],
      ['_permission' => 'access content', '_csrf_token' => 'TRUE']
    );
    $moveRoute->setMethods(['POST']);
    $collection->add('brebo_office_core.buildings_kanban_move', $moveRoute);

    $configRoute = new Route(
      '/gebouwen/kanban/indeling',
      ['_controller' => '\\Drupal\\brebo_office_core\\Controller\\BuildingPortfolioController::saveConfig'],
      ['_permission' => 'access content', '_csrf_token' => 'TRUE']
    );
    $configRoute->setMethods(['POST']);
    $collection->add('brebo_office_core.buildings_kanban_config', $configRoute);
  }

}
