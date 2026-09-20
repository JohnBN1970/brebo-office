<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/** Adds project administration assignment without duplicating project routes. */
final class ProjectAdministrationRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    if ($collection->get('brebo_office_core.project_administration') !== NULL) {
      return;
    }
    $route = new Route('/projecten/{node}/administratie');
    $route->setDefault('_form', '\\Drupal\\brebo_office_core\\Form\\ProjectAdministrationForm');
    $route->setDefault('_title', 'Projectadministratie');
    $route->setRequirement('_entity_access', 'node.update');
    $route->setOption('parameters', ['node' => ['type' => 'entity:node']]);
    $collection->add('brebo_office_core.project_administration', $route);
  }

}
