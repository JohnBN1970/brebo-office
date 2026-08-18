<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adds the project financial cockpit without touching the shared routing file.
 */
final class ProjectFinancialRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    if ($collection->get('brebo_office_core.project_financial')) {
      return;
    }

    $collection->add('brebo_office_core.project_financial', new Route(
      '/projecten/{node}/financieel',
      [
        '_controller' => '\\Drupal\\brebo_office_core\\Controller\\ProjectFinancialController::overview',
        '_title_callback' => '\\Drupal\\brebo_office_core\\Controller\\ProjectFinancialController::title',
      ],
      [
        '_entity_access' => 'node.view',
      ],
      [
        'parameters' => [
          'node' => ['type' => 'entity:node'],
        ],
      ],
    ));
  }

}
