<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Controller\ControllerBase;

/** Renders the signed-in user's BREBO Office work queue. */
final class MyFinanceActionsPageController extends ControllerBase {
  public function page(): array {
    return [
      '#type'=>'container',
      '#attributes'=>[
        'id'=>'brebo-my-finance-actions',
        'class'=>['brebo-my-finance-actions'],
        'data-api-url'=>'/brebo-office/api/my-work',
      ],
      'header'=>['#markup'=>'<header class="bmfa-header"><div><span class="bmfa-kicker">BREBO OFFICE · PERSOONLIJKE COCKPIT</span><h1>Mijn Werkbak</h1><p>Alles wat vandaag jouw aandacht nodig heeft, over alle BREBO Office-domeinen heen.</p></div><a href="/brebo-office/finance">Financieel Command Center</a></header>'],
      'content'=>['#markup'=>'<div data-bmfa-content><div class="bmfa-loading">Mijn Werkbak laden…</div></div>'],
      '#attached'=>['library'=>['brebo_finance/my_finance_actions']],
      '#cache'=>['max-age'=>0],
    ];
  }
}
