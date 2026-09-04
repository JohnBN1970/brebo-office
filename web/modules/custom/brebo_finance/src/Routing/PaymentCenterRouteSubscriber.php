<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/** Adds payment-center operating routes without duplicating Finance routing YAML. */
final class PaymentCenterRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    if (!$collection->get('brebo_finance.payment_center')) {
      $collection->add('brebo_finance.payment_center', new Route(
        '/brebo-office/finance/payment-center',
        ['_controller' => '\\Drupal\\brebo_finance\\Controller\\PaymentCenterController::page', '_title' => 'Betaalcentrum'],
        ['_permission' => 'access brebo finance'],
        ['no_cache' => TRUE],
        '',
        [],
        ['GET'],
      ));
    }
    if (!$collection->get('brebo_finance.payment_center_prepare')) {
      $collection->add('brebo_finance.payment_center_prepare', new Route(
        '/brebo-office/finance/payment-center/prepare',
        ['_controller' => '\\Drupal\\brebo_finance\\Controller\\PaymentCenterController::prepare'],
        ['_permission' => 'manage brebo finance'],
        ['no_cache' => TRUE],
        '',
        [],
        ['POST'],
      ));
    }
    if (!$collection->get('brebo_finance.payment_center_review')) {
      $collection->add('brebo_finance.payment_center_review', new Route(
        '/brebo-office/finance/payment-center/{batch_id}/review',
        ['_controller' => '\\Drupal\\brebo_finance\\Controller\\PaymentCenterController::review'],
        ['_permission' => 'manage brebo finance', 'batch_id' => '\\d+'],
        ['no_cache' => TRUE],
        '',
        [],
        ['POST'],
      ));
    }
    if (!$collection->get('brebo_finance.payment_center_release')) {
      $collection->add('brebo_finance.payment_center_release', new Route(
        '/brebo-office/finance/payment-center/{batch_id}/release',
        ['_controller' => '\\Drupal\\brebo_finance\\Controller\\PaymentCenterController::release'],
        ['_permission' => 'approve brebo finance', 'batch_id' => '\\d+'],
        ['no_cache' => TRUE],
        '',
        [],
        ['POST'],
      ));
    }
  }

}
