<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/** Adds the calculation readiness requirement to the existing offer route. */
final class OfferReadinessRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('brebo_office_core.create_offer_version');
    if ($route !== NULL) {
      $route->setRequirement('_brebo_offer_ready', 'TRUE');
    }
  }

}
