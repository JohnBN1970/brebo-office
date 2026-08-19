<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Controller\ControllerBase;

/** Renders the signed-in user's personal financial action queue. */
final class MyFinanceActionsPageController extends ControllerBase {
  public function page(): array {
    return [
      '#type'=>'container',
      '#attributes'=>[
        'id'=>'brebo-my-finance-actions',
        'class'=>['brebo-my-finance-actions'],
        'data-api-url'=>'/brebo-office/api/finance/my-actions',
      ],
      'header'=>['#markup'=>'<header class="bmfa-header"><div><span class="bmfa-kicker">BREBO OFFICE · MIJN WERKBAK</span><h1>Mijn financiële acties</h1><p>Wat moet ik vandaag oplossen om projecten en betalingen door te laten lopen?</p></div><a href="/brebo-office/finance">Financieel Command Center</a></header>'],
      'content'=>['#markup'=>'<div data-bmfa-content><div class="bmfa-loading">Werkbak laden…</div></div>'],
      '#attached'=>['library'=>['brebo_finance/my_finance_actions']],
      '#cache'=>['max-age'=>0],
    ];
  }
}
