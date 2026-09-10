<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/** Keeps the Finance entry route independently renderable. */
final class FinanceRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('brebo_finance.command_center_page');
    if ($route !== NULL) {
      $route->setDefault('_controller', '\\Drupal\\brebo_finance\\Controller\\FinanceShellController::page');
    }

    if ($collection->get('brebo_finance.sales_workspace') === NULL) {
      $collection->add('brebo_finance.sales_workspace', new Route(
        '/brebo-office/finance/sales',
        [
          '_controller' => '\\Drupal\\brebo_finance\\Controller\\SalesWorkspaceController::page',
          '_title' => 'Verkoop',
        ],
        ['_permission' => 'access brebo finance'],
        ['no_cache' => TRUE],
        '',
        [],
        ['GET'],
      ));
    }

    if ($collection->get('brebo_finance.sales_standalone_start') === NULL) {
      $collection->add('brebo_finance.sales_standalone_start', new Route(
        '/brebo-office/finance/sales/new',
        [
          '_form' => '\\Drupal\\brebo_finance\\Form\\StandaloneSalesInvoiceForm',
          '_title' => 'Nieuwe losse factuur',
        ],
        ['_permission' => 'access brebo finance'],
        ['no_cache' => TRUE],
        '',
        [],
        ['GET', 'POST'],
      ));
    }

    if ($collection->get('brebo_finance.sales_standalone_edit') === NULL) {
      $collection->add('brebo_finance.sales_standalone_edit', new Route(
        '/brebo-office/finance/sales/drafts/{draft}/edit',
        [
          '_form' => '\\Drupal\\brebo_finance\\Form\\StandaloneSalesInvoiceForm',
          '_title' => 'Los factuurconcept bewerken',
        ],
        [
          '_permission' => 'access brebo finance',
          'draft' => '\\d+',
        ],
        ['no_cache' => TRUE],
        '',
        [],
        ['GET', 'POST'],
      ));
    }
  }

}
