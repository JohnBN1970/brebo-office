<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/** Routes offer creation and calculation workbench helpers. */
final class OfferReadinessRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('brebo_office_core.create_offer_version');
    if ($route !== NULL) {
      // Keep the public route name stable so all existing links continue to work,
      // but send it through the readiness controller before the actual offer form.
      $defaults = $route->getDefaults();
      unset($defaults['_form']);
      $defaults['_controller'] = '\\Drupal\\brebo_calculation\\Controller\\CalculationOfferGateController::gate';
      $defaults['_title'] = 'Offerte gereedmaken';
      $route->setDefaults($defaults);
      $route->setRequirement('_brebo_offer_ready', 'TRUE');

      // The actual offer form is intentionally not linked directly. READY may
      // enter immediately; REVIEW only after a confirmation matching the exact
      // current version and warning fingerprint.
      $collection->add('brebo_calculation.offer_create_internal', new Route(
        '/calculaties/{node}/offerte-maken/formulier',
        [
          '_form' => '\\Drupal\\brebo_office_core\\Form\\OfferVersionForm',
          '_title' => 'Nieuwe offerteversie',
        ],
        [
          '_permission' => 'create brebo_offer_version content',
          '_brebo_offer_confirmed' => 'TRUE',
        ],
        [
          'parameters' => [
            'node' => ['type' => 'entity:node'],
          ],
        ],
      ));
    }

    $orderStatus = new Route(
      '/admin/brebo/calculations/{node}/workbench/block-order',
      ['_controller' => '\\Drupal\\brebo_calculation\\Controller\\CalculationBlockOrderController::status'],
      ['_permission' => 'edit brebo calculation workbench'],
      [
        '_admin_route' => TRUE,
        'parameters' => ['node' => ['type' => 'entity:node']],
      ],
    );
    $orderStatus->setMethods(['GET']);
    $collection->add('brebo_calculation.block_order_status', $orderStatus);

    $orderSave = new Route(
      '/admin/brebo/calculations/{node}/workbench/block-order/save',
      ['_controller' => '\\Drupal\\brebo_calculation\\Controller\\CalculationBlockOrderController::save'],
      [
        '_permission' => 'edit brebo calculation workbench',
        '_csrf_request_header_token' => 'TRUE',
      ],
      [
        '_admin_route' => TRUE,
        'parameters' => ['node' => ['type' => 'entity:node']],
      ],
    );
    $orderSave->setMethods(['POST']);
    $collection->add('brebo_calculation.block_order_save', $orderSave);
  }

}
