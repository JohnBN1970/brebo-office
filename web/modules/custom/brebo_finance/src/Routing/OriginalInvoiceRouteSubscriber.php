<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/** Adds the private original-invoice evidence route. */
final class OriginalInvoiceRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    if ($collection->get('brebo_finance.purchase_invoice_original') !== NULL) {
      return;
    }
    $collection->add('brebo_finance.purchase_invoice_original', new Route(
      '/brebo-office/finance/purchase-invoices/{invoice_id}/original',
      [
        '_controller' => '\\Drupal\\brebo_finance\\Controller\\OriginalInvoiceDocumentController::view',
        '_title' => 'Originele factuur',
      ],
      [
        '_permission' => 'access brebo finance',
        'invoice_id' => '\\d+',
      ],
      ['no_cache' => TRUE],
      '',
      [],
      ['GET'],
    ));
  }

}
