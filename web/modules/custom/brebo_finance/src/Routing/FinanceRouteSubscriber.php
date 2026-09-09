<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/** Keeps the Finance entry route independently renderable. */
final class FinanceRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('brebo_finance.command_center_page');
    if ($route !== NULL) {
      $route->setDefault('_controller', '\\Drupal\\brebo_finance\\Controller\\FinanceShellController::page');
    }
  }

}
