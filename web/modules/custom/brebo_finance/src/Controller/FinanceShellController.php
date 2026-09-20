<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Renders the Finance shell independently from optional runtime data builders.
 */
final class FinanceShellController extends ControllerBase {

  /**
   * Keeps the Finance entry page renderable even when a live data component fails.
   */
  public function page(): array {
    $decisionUrl = '';
    try {
      $url = Url::fromRoute('brebo_finance.financial_decision_page');
      if ($url->access($this->currentUser())) {
        $decisionUrl = $url->toString();
      }
    }
    catch (\Throwable $e) {
      $this->getLogger('brebo_finance')->warning('Finance decision link unavailable: @message', ['@message' => $e->getMessage()]);
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'brebo-finance-command-center',
        'class' => ['brebo-finance-command-center'],
        'data-api-url' => Url::fromRoute('brebo_finance.command_center_api')->toString(),
        'data-decision-url' => $decisionUrl,
        'data-payables-url' => Url::fromRoute('brebo_finance.payables_work_queues')->toString(),
        'data-purchase-invoices-url' => Url::fromRoute('brebo_finance.purchase_invoice_list')->toString(),
      ],
      'header' => [
        '#markup' => '<header class="bfcc-header"><div><span class="bfcc-kicker">BREBO OFFICE · FINANCE</span><h1>Finance</h1><p>Financieel commandocentrum voor resultaat, liquiditeit, inkoop, verkoop, vaste kosten, risico en besluiten.</p></div><div class="bfcc-live">LIVE CONTROL</div></header>',
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => ['data-bfcc-content' => 'true'],
        'loading' => [
          '#markup' => '<div class="bfcc-loading">' . $this->t('Financieel dashboard wordt geladen…') . '</div>',
        ],
      ],
      '#attached' => ['library' => ['brebo_finance/command_center']],
      '#cache' => ['max-age' => 0],
    ];
  }

}
