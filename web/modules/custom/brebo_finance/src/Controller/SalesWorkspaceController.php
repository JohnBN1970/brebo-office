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

    if ($this->database->schema()->tableExists('brebo_finance_sales_invoice_draft')) {
      $drafts = $this->database->select('brebo_finance_sales_invoice_draft', 'd')
        ->fields('d', ['id', 'draft_number', 'project_nid', 'invoice_date', 'due_date', 'status', 'amount_inc_vat'])
        ->orderBy('created', 'DESC')
        ->range(0, 50)
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);
      foreach ($drafts as $draft) {
        $projectId = (int) $draft['project_nid'];
        $projectLink = $projectId > 0
          ? ['data' => ['#type' => 'link', '#title' => (string) $projectId, '#url' => Url::fromRoute('brebo_project_cockpit.invoices', ['node' => $projectId])]]
          : $this->t('Los');
        $draftLabel = (string) $draft['draft_number'];
        $editableStandalone = $projectId === 0 && (string) $draft['status'] === 'draft';
        $draftCell = $editableStandalone
          ? ['data' => ['#type' => 'link', '#title' => $draftLabel, '#url' => Url::fromRoute('brebo_finance.sales_standalone_edit', ['draft' => (int) $draft['id']])]]
          : $draftLabel;
        $actionCell = '—';
        if ($editableStandalone) {
          $actionCell = ['data' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['brebo-finance-sales-actions']],
            'review' => [
              '#type' => 'link',
              '#title' => $this->t('Ter beoordeling'),
              '#url' => Url::fromRoute('brebo_finance.sales_standalone_review', ['draft' => (int) $draft['id']]),
            ],
            'separator' => ['#markup' => ' · '],
            'release' => [
              '#type' => 'link',
              '#title' => $this->t('Vrijgeven & verzenden'),
              '#url' => Url::fromRoute('brebo_finance.sales_standalone_release', ['draft' => (int) $draft['id']]),
            ],
          ]];
        }
        $rows[] = [
          $draftCell,
          $projectLink,
          $draft['invoice_date'],
          $draft['due_date'],
          $draft['status'] === 'draft' ? $this->t('Concept') : $draft['status'],
          '€ ' . number_format((float) $draft['amount_inc_vat'], 2, ',', '.'),
          '—',
          $actionCell,
        ];
      }
    }

    if ($this->database->schema()->tableExists('brebo_finance_sales_invoice')) {
      $query = $this->database->select('brebo_finance_sales_invoice', 'i')
        ->fields('i', ['invoice_number', 'project_nid', 'invoice_date', 'due_date', 'status', 'amount_inc_vat', 'paid_amount_inc_vat'])
        ->orderBy('due_date', 'ASC')
        ->range(0, 50);
      foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $invoice) {
        $projectId = (int) $invoice['project_nid'];
        $projectLink = $projectId > 0
          ? ['data' => ['#type' => 'link', '#title' => (string) $projectId, '#url' => Url::fromRoute('brebo_project_cockpit.invoices', ['node' => $projectId])]]
          : $this->t('Los');
        $rows[] = [
          $invoice['invoice_number'] !== '' ? $invoice['invoice_number'] : $this->t('Concept'),
          $projectLink,
          $invoice['invoice_date'],
          $invoice['due_date'],
          $invoice['status'],
          '€ ' . number_format((float) $invoice['amount_inc_vat'], 2, ',', '.'),
          '€ ' . number_format(max(0.0, (float) $invoice['amount_inc_vat'] - (float) $invoice['paid_amount_inc_vat']), 2, ',', '.'),
          '—',
        ];
      }
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-finance-sales-workspace']],
      'header' => ['#markup' => '<header class="bfcc-header"><div><span class="bfcc-kicker">BREBO OFFICE · FINANCE</span><h1>Verkoop</h1><p>Conceptfacturen, verzending, openstaande posten en ontvangsten.</p></div><div class="bfcc-live">LIVE CONTROL</div></header>'],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['bfcc-actions']],
        'standalone' => [
          '#type' => 'link',
          '#title' => $this->t('+ Nieuwe losse factuur'),
          '#url' => Url::fromRoute('brebo_finance.sales_standalone_start'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'decisions' => [
          '#type' => 'link',
          '#title' => $this->t('Besluiten / te doen'),
          '#url' => Url::fromRoute('brebo_finance.financial_decision_page'),
          '#attributes' => ['class' => ['button']],
          '#access' => $this->currentUser()->hasPermission('approve brebo finance'),
        ],
      ],
      'explanation' => [
        '#markup' => '<p><strong>Projectfacturen ontstaan vanuit het vastgestelde termijnschema.</strong> Finance toont dezelfde conceptfacturen centraal. Losse concepten kun je openen, bijwerken, ter beoordeling versturen of definitief vrijgeven.</p>',
      ],
      'invoices' => [
        '#type' => 'table',
        '#header' => [$this->t('Factuur'), $this->t('Project'), $this->t('Factuurdatum'), $this->t('Vervaldatum'), $this->t('Status'), $this->t('Bedrag'), $this->t('Openstaand'), $this->t('Actie')],
        '#rows' => $rows,
        '#empty' => $this->t('Nog geen verkoopfacturen beschikbaar.'),
      ],
      '#attached' => ['library' => ['brebo_finance/command_center']],
      '#cache' => ['contexts' => ['user.permissions'], 'max-age' => 0],
    ];
  }

}
