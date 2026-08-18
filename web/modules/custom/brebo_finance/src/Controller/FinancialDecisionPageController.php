<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Controller\ControllerBase;

/** BREBO Office page shell for human financial decisions. */
final class FinancialDecisionPageController extends ControllerBase {

  public function page(): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'brebo-finance-decision-app',
        'class' => ['brebo-finance-decisions'],
        'data-inbox-url' => '/brebo-office/api/finance/decision-inbox?mine=1',
        'data-notifications-url' => '/brebo-office/api/finance/notifications',
        'data-decision-base-url' => '/brebo-office/api/finance/phase-gate-exceptions',
      ],
      'header' => [
        '#markup' => '<div class="bfd-header"><div><div class="bfd-eyebrow">BREBO Office · Financiële beheersing</div><h1>Beslisinbox</h1><p>Besluiten die jouw bevoegdheid nodig hebben. Exposure, urgentie en onderbouwing in één scherm.</p></div><button class="bfd-bell" type="button" data-bfd-bell aria-label="Financiële meldingen">🔔 <span data-bfd-badge hidden>0</span></button></div>',
      ],
      'status' => [
        '#markup' => '<div class="bfd-status" data-bfd-status>Besluiten laden…</div>',
      ],
      'content' => [
        '#markup' => '<div class="bfd-layout"><main><div class="bfd-list" data-bfd-list></div></main><aside class="bfd-notifications" data-bfd-notifications hidden><div class="bfd-panel-head"><strong>Meldingen</strong><button type="button" data-bfd-close aria-label="Sluiten">×</button></div><div data-bfd-notification-list></div></aside></div>',
      ],
      '#attached' => ['library' => ['brebo_finance/decision_inbox']],
      '#cache' => ['max-age' => 0],
    ];
  }
}
