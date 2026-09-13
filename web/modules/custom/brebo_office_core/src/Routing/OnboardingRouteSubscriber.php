<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/** Adds onboarding routes without changing the large canonical routing file. */
final class OnboardingRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    if ($collection->get('brebo_office_core.welcome_onboarding') === NULL) {
      $collection->add('brebo_office_core.welcome_onboarding', new Route(
        '/welkom',
        ['_controller' => '\\Drupal\\brebo_office_core\\Controller\\WelcomeOnboardingController::page', '_title' => 'Welkom bij Office'],
        ['_user_is_logged_in' => 'TRUE'],
      ));
    }
    if ($collection->get('brebo_office_core.onboarding_tour_progress') === NULL) {
      $collection->add('brebo_office_core.onboarding_tour_progress', new Route(
        '/welkom/tour/progress',
        ['_controller' => '\\Drupal\\brebo_office_core\\Controller\\OnboardingTourController::progress'],
        ['_user_is_logged_in' => 'TRUE'],
        [],
        '',
        [],
        ['POST'],
      ));
    }
  }

}
