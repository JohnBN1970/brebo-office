<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/** Keeps Finance entry points independently renderable. */
final class FinanceRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('brebo_finance.command_center_page');
    if ($route !== NULL) {
      $route->setDefault('_controller', '\\Drupal\\brebo_finance\\Controller\\FinanceShellController::page');
    }

    if ($collection->get('brebo_finance.sales_workspace') === NULL) {
      $collection->add('brebo_finance.sales_workspace', new Route(
        '/brebo-office/finance/sales',
        ['_controller' => '\\Drupal\\brebo_finance\\Controller\\SalesWorkspaceController::page', '_title' => 'Verkoop'],
        ['_permission' => 'access brebo finance'],
        ['no_cache' => TRUE],
      ));
    }
  }

}
