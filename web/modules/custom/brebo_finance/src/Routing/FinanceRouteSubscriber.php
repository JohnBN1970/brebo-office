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
    if ($route !== NULL) $route->setDefault('_controller', '\\Drupal\\brebo_finance\\Controller\\FinanceShellController::page');

    $routes = [
      'brebo_finance.sales_workspace' => new Route('/brebo-office/finance/sales', ['_controller' => '\\Drupal\\brebo_finance\\Controller\\SalesWorkspaceController::page', '_title' => 'Verkoop'], ['_permission' => 'access brebo finance'], ['no_cache' => TRUE], '', [], ['GET']),
      'brebo_finance.sales_settings' => new Route('/brebo-office/finance/sales/settings', ['_form' => '\\Drupal\\brebo_finance\\Form\\SalesSettingsForm', '_title' => 'Verkoopinstellingen'], ['_permission' => 'approve brebo finance'], ['no_cache' => TRUE], '', [], ['GET', 'POST']),
      'brebo_finance.sales_standalone_start' => new Route('/brebo-office/finance/sales/new', ['_form' => '\\Drupal\\brebo_finance\\Form\\StandaloneSalesInvoiceForm', '_title' => 'Nieuwe losse factuur'], ['_permission' => 'access brebo finance'], ['no_cache' => TRUE], '', [], ['GET', 'POST']),
      'brebo_finance.sales_standalone_edit' => new Route('/brebo-office/finance/sales/drafts/{draft}/edit', ['_form' => '\\Drupal\\brebo_finance\\Form\\StandaloneSalesInvoiceForm', '_title' => 'Los factuurconcept bewerken'], ['_permission' => 'access brebo finance', 'draft' => '\\d+'], ['no_cache' => TRUE], '', [], ['GET', 'POST']),
      'brebo_finance.sales_standalone_review' => new Route('/brebo-office/finance/sales/drafts/{draft}/review', ['_form' => '\\Drupal\\brebo_finance\\Form\\StandaloneSalesInvoiceReviewForm', '_title' => 'Conceptfactuur ter beoordeling'], ['_permission' => 'access brebo finance', 'draft' => '\\d+'], ['no_cache' => TRUE], '', [], ['GET', 'POST']),
      'brebo_finance.sales_standalone_release' => new Route('/brebo-office/finance/sales/drafts/{draft}/release', ['_form' => '\\Drupal\\brebo_finance\\Form\\StandaloneSalesInvoiceReleaseForm', '_title' => 'Factuur definitief vrijgeven'], ['_permission' => 'access brebo finance', 'draft' => '\\d+'], ['no_cache' => TRUE], '', [], ['GET', 'POST']),
      'brebo_finance.sales_standalone_pdf_preview' => new Route('/brebo-office/finance/sales/drafts/{draft}/preview.pdf', ['_controller' => '\\Drupal\\brebo_finance\\Controller\\StandaloneSalesInvoicePdfController::preview'], ['_permission' => 'access brebo finance', 'draft' => '\\d+'], ['no_cache' => TRUE], '', [], ['GET']),
      'brebo_finance.receivables_action' => new Route('/brebo-office/finance/sales/invoices/{invoice}/receivables', ['_form' => '\\Drupal\\brebo_finance\\Form\\ReceivablesActionForm', '_title' => 'Debiteurenactie'], ['_permission' => 'manage brebo finance', 'invoice' => '\\d+'], ['no_cache' => TRUE], '', [], ['GET', 'POST']),
      'brebo_finance.receivables_bulk' => new Route('/brebo-office/finance/sales/receivables/bulk', ['_form' => '\\Drupal\\brebo_finance\\Form\\ReceivablesBulkActionForm', '_title' => 'Debiteuren bulkwerkbak'], ['_permission' => 'manage brebo finance'], ['no_cache' => TRUE], '', [], ['GET', 'POST']),
      'brebo_finance.collection_debtor_profile' => new Route('/brebo-office/finance/sales/invoices/{invoice}/collection-profile', ['_form' => '\\Drupal\\brebo_finance\\Form\\CollectionDebtorProfileForm', '_title' => 'Incassogegevens debiteur'], ['_permission' => 'manage brebo finance', 'invoice' => '\\d+'], ['no_cache' => TRUE], '', [], ['GET', 'POST']),
    ];
    foreach ($routes as $name => $candidate) if ($collection->get($name) === NULL) $collection->add($name, $candidate);
  }
}
