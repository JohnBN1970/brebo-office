<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adds the authenticated BREBO Office appearance preference endpoint.
 */
final class AppearanceRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $route = new Route(
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

    $collection->add('brebo_office_core.appearance_preference', $route);
  }

}
