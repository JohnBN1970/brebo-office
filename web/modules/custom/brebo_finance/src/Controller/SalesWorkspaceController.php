<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Organisation-wide sales and receivables workspace. */
final class SalesWorkspaceController extends ControllerBase {

  public function __construct(private readonly Connection $database) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function page(): array {
    $rows = [];
    if ($this->database->schema()->tableExists('brebo_finance_sales_invoice')) {
      $query = $this->database->select('brebo_finance_sales_invoice', 'i')
        ->fields('i', ['invoice_number', 'project_nid', 'invoice_date', 'due_date', 'status', 'amount_inc_vat', 'paid_amount_inc_vat'])
        ->orderBy('due_date', 'ASC')
        ->range(0, 50);
      foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $invoice) {
        $projectId = (int) $invoice['project_nid'];
        $projectLink = $projectId > 0
          ? ['data' => ['#type' => 'link', '#title' => (string) $projectId, '#url' => Url::fromRoute('brebo_project_cockpit.invoices', ['node' => $projectId])]]
          : '';
        $rows[] = [
          $invoice['invoice_number'], $projectLink, $invoice['invoice_date'], $invoice['due_date'], $invoice['status'],
          '€ ' . number_format((float) $invoice['amount_inc_vat'], 2, ',', '.'),
          '€ ' . number_format(max(0.0, (float) $invoice['amount_inc_vat'] - (float) $invoice['paid_amount_inc_vat']), 2, ',', '.'),
        ];
      }
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-finance-sales-workspace']],
      'header' => ['#markup' => '<header class="bfcc-header"><div><span class="bfcc-kicker">BREBO OFFICE · FINANCE</span><h1>Verkoop</h1><p>Van termijn en conceptfactuur tot ontvangst en debiteurenbewaking.</p></div><div class="bfcc-live">LIVE CONTROL</div></header>'],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['bfcc-actions']],
        'help' => ['#markup' => '<strong>Nieuwe verkoop start vanuit het project.</strong> Open hieronder het project om termijnen, stelposten, conceptfacturen en vrijgave te beheren.'],
        'decisions' => [
          '#type' => 'link',
          '#title' => $this->t('Besluiten / te doen'),
          '#url' => Url::fromRoute('brebo_finance.financial_decision_page'),
          '#attributes' => ['class' => ['button', 'button--primary']],
          '#access' => $this->currentUser()->hasPermission('approve brebo finance'),
        ],
      ],
      'invoices' => [
        '#type' => 'table',
        '#header' => [$this->t('Factuur'), $this->t('Project'), $this->t('Factuurdatum'), $this->t('Vervaldatum'), $this->t('Status'), $this->t('Bedrag'), $this->t('Openstaand')],
        '#rows' => $rows,
        '#empty' => $this->t('Nog geen verkoopfacturen beschikbaar. Maak de eerste verkoopfactuur vanuit een project via Facturen.'),
      ],
      '#attached' => ['library' => ['brebo_finance/command_center']],
      '#cache' => ['contexts' => ['user.permissions'], 'max-age' => 60],
    ];
  }

}
