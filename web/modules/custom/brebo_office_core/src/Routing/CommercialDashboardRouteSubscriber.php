<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adds the authenticated commercial dashboard summary endpoint.
 */
final class CommercialDashboardRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $route = new Route(
      '/brebo-office/dashboard/commercial-summary',
      ['_controller' => '\\Drupal\\brebo_office_core\\Controller\\CommercialDashboardSummaryController::summary'],
      [
        '_user_is_logged_in' => 'TRUE',
        '_permission' => 'access content',
      ],
      [],
      '',
      [],
      ['GET'],
    );

    $collection->add('brebo_office_core.dashboard_commercial_summary', $route);
  }

}
