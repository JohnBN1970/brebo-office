<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/** Keeps central Office settings restricted while exposing registered sections. */
final class SettingsAccessRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $access = '\\Drupal\\brebo_office_core\\Access\\SettingsAccess::access';

    $route = $collection->get('brebo_office_core.settings');
    if ($route !== NULL) {
      $requirements = $route->getRequirements();
      unset($requirements['_permission']);
      $requirements['_custom_access'] = $access;
      $route->setRequirements($requirements);
    }

    if ($collection->get('brebo_office_core.administration_settings') === NULL) {
      $collection->add('brebo_office_core.administration_settings', new Route(
        '/instellingen/administraties',
        [
          '_form' => '\\Drupal\\brebo_office_core\\Form\\AdministrationSettingsForm',
          '_title' => 'Organisatie, administraties & nummering',
        ],
        ['_custom_access' => $access],
        ['no_cache' => TRUE],
        '',
        [],
        ['GET', 'POST'],
      ));
    }
  }

}
