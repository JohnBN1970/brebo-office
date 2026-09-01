<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Routes eligible generic Office lists through the shared status portfolio UI.
 */
final class StatusPortfolioRouteSubscriber extends RouteSubscriberBase {

  private const ELIGIBLE_BUNDLES = [
    'brebo_building_zone',
    'brebo_cluster',
    'brebo_dwelling',
    'brebo_product_position',
    'brebo_verification',
    'brebo_deviation',
    'brebo_work_package',
    'brebo_release_gate',
    'brebo_work_budget',
    'brebo_rfq',
    'brebo_supplier_quote',
    'brebo_budget_change',
    'brebo_route_item',
    'brebo_project_scope',
    'brebo_organization',
    'brebo_contact',
  ];

  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($collection->all() as $route) {
      $controller = (string) $route->getDefault('_controller');
      $bundle = (string) ($route->getDefault('bundle') ?? '');
      if ($controller !== '\\Drupal\\brebo_office_core\\Controller\\OfficeController::objectList') {
        continue;
      }
      if (!in_array($bundle, self::ELIGIBLE_BUNDLES, TRUE)) {
        continue;
      }
      $route->setDefault('_controller', '\\Drupal\\brebo_office_core\\Controller\\StatusPortfolioController::overview');
    }
  }

}
