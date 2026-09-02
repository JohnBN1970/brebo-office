<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/** Adds authenticated Office dashboard summary endpoints. */
final class CommercialDashboardRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    $commercial = new Route(
      '/brebo-office/dashboard/commercial-summary',
      ['_controller' => '\\Drupal\\brebo_office_core\\Controller\\CommercialDashboardSummaryController::summary'],
      ['_user_is_logged_in' => 'TRUE', '_permission' => 'access content'],
      [], '', [], ['GET'],
    );
    $collection->add('brebo_office_core.dashboard_commercial_summary', $commercial);

    $quality = new Route(
      '/brebo-office/dashboard/quality-summary',
      ['_controller' => '\\Drupal\\brebo_office_core\\Controller\\QualityDashboardSummaryController::summary'],
      ['_user_is_logged_in' => 'TRUE', '_permission' => 'access content'],
      [], '', [], ['GET'],
    );
    $collection->add('brebo_office_core.dashboard_quality_summary', $quality);

    // Keep the canonical CRM URL, but make Dashboard the third presentation mode.
    // List and Kanban are delegated to the existing CrmController unchanged.
    if ($funnel = $collection->get('brebo_office_core.funnel')) {
      $funnel->setDefault('_controller', '\\Drupal\\brebo_office_core\\Controller\\CrmFunnelWorkspaceController::overview');
    }
  }

}
