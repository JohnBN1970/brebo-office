<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/** Routes eligible Office lists through the shared status portfolio UI. */
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
    'brebo_action',
    'brebo_risk',
    'brebo_signal',
  ];

  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($collection->all() as $route) {
      $controller = (string) $route->getDefault('_controller');
      $bundle = (string) ($route->getDefault('bundle') ?? '');
      if ($controller !== '\\Drupal\\brebo_office_core\\Controller\\OfficeController::objectList') continue;
      if (!in_array($bundle, self::ELIGIBLE_BUNDLES, TRUE)) continue;
      $route->setDefault('_controller', '\\Drupal\\brebo_office_core\\Controller\\StatusPortfolioController::overview');
    }

    $this->addPortfolioRoute($collection, 'brebo_office_core.actions', '/acties', 'Acties', 'brebo_action');
    $this->addPortfolioRoute($collection, 'brebo_office_core.risks', '/risicos', 'Risico’s', 'brebo_risk');
    $this->addPortfolioRoute($collection, 'brebo_office_core.signals', '/signalen', 'Signalen', 'brebo_signal');
  }

  private function addPortfolioRoute(RouteCollection $collection, string $name, string $path, string $title, string $bundle): void {
    if ($collection->get($name) !== NULL) return;
    $collection->add($name, new Route(
      $path,
      [
        '_controller' => '\\Drupal\\brebo_office_core\\Controller\\StatusPortfolioController::overview',
        '_title' => $title,
        'bundle' => $bundle,
      ],
      ['_permission' => 'access content'],
    ));
  }

}
