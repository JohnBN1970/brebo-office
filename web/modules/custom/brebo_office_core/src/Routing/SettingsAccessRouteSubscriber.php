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

    // Drupal's permission access check treats comma-separated permissions as OR.
    // The settings centre remains restricted to technical site administrators or
    // BREBO users with explicit executive financial procuration.
    $route->setRequirement(
      '_permission',
      'administer site configuration,approve brebo finance executive',
    );
  }

}
