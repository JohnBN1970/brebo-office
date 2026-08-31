<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adds authenticated BREBO Office user-preference endpoints.
 */
final class AppearanceRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $appearance = new Route(
      '/brebo-office/appearance',
      ['_controller' => '\\Drupal\\brebo_office_core\\Controller\\AppearancePreferenceController::save'],
      [
        '_user_is_logged_in' => 'TRUE',
        '_csrf_request_header_token' => 'TRUE',
      ],
      [],
      '',
      [],
      ['POST'],
    );
    $collection->add('brebo_office_core.appearance_preference', $appearance);

    $layout_load = new Route(
      '/brebo-office/dashboard-layout',
      ['_controller' => '\\Drupal\\brebo_office_core\\Controller\\DashboardLayoutPreferenceController::load'],
      ['_user_is_logged_in' => 'TRUE'],
      [],
      '',
      [],
      ['GET'],
    );
    $collection->add('brebo_office_core.dashboard_layout_load', $layout_load);

    $layout_save = new Route(
      '/brebo-office/dashboard-layout',
      ['_controller' => '\\Drupal\\brebo_office_core\\Controller\\DashboardLayoutPreferenceController::save'],
      [
        '_user_is_logged_in' => 'TRUE',
        '_csrf_request_header_token' => 'TRUE',
      ],
      [],
      '',
      [],
      ['POST'],
    );
    $collection->add('brebo_office_core.dashboard_layout_save', $layout_save);
  }

}
