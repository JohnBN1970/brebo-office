<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/** Keeps Office settings restricted while allowing BREBO executive access. */
final class SettingsAccessRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('brebo_office_core.settings');
    if ($route === NULL) {
      return;
    }

    $route->setRequirement(
      '_custom_access',
      '\\Drupal\\brebo_office_core\\Access\\SettingsAccess::access',
    );
    $route->setRequirement('_permission', NULL);
  }

}
