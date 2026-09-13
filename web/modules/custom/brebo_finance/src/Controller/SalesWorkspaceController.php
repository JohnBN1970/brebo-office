<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\ReceivablesDunningManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Organisation-wide sales and receivables workspace. */
final class SalesWorkspaceController extends ControllerBase {

  private readonly ReceivablesDunningManager $dunningManager;

  public function __construct(
    private readonly Connection $database,
    KeyValueFactoryInterface $keyValueFactory,
    ConfigFactoryInterface $configFactory,
  ) {
    $this->dunningManager = new ReceivablesDunningManager($database, $keyValueFactory, $configFactory);
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('keyvalue'),
      $container->get('config.factory'),
    );
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
        ->fields('i', ['id', 'invoice_number', 'project_nid', 'invoice_date', 'due_date', 'status', 'amount_inc_vat', 'paid_amount_inc_vat'])
        ->orderBy('due_date', 'ASC')
        ->range(0, 50);
      foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $invoice) {
        $projectId = (int) $invoice['project_nid'];
        $projectLink = $projectId > 0
          ? ['data' => ['#type' => 'link', '#title' => (string) $projectId, '#url' => Url::fromRoute('brebo_project_cockpit.invoices', ['node' => $projectId])]]
          : $this->t('Los');

        $receivables = $this->dunningManager->state((int) $invoice['id']);
        $nextStep = $this->dunningManager->nextStep((int) $invoice['id']);
        $action = $this->receivablesActionLabel($receivables, $nextStep);

        $rows[] = [
          $invoice['invoice_number'] !== '' ? $invoice['invoice_number'] : $this->t('Concept'),
          $projectLink,
          $invoice['invoice_date'],
          $invoice['due_date'],
          $this->receivablesStatusLabel((string) $receivables['status']),
          '€ ' . number_format((float) $invoice['amount_inc_vat'], 2, ',', '.'),
          '€ ' . number_format((float) $receivables['outstanding_amount_inc_vat'], 2, ',', '.'),
          $action,
        ];
      }
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-finance-sales-workspace']],
      'header' => ['#markup' => '<header class="bfcc-header"><div><span class="bfcc-kicker">BREBO OFFICE · FINANCE</span><h1>Verkoop</h1><p>Conceptfacturen, verzending, openstaande posten en debiteurenbewaking.</p></div><div class="bfcc-live">LIVE CONTROL</div></header>'],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['bfcc-actions']],
        'standalone' => [
          '#type' => 'link',
          '#title' => $this->t('+ Nieuwe losse factuur'),
          '#url' => Url::fromRoute('brebo_finance.sales_standalone_start'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'settings' => [
          '#type' => 'link',
          '#title' => $this->t('Verkoopinstellingen'),
          '#url' => Url::fromRoute('brebo_finance.sales_settings'),
          '#attributes' => ['class' => ['button']],
          '#access' => $this->currentUser()->hasPermission('approve brebo finance'),
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
        '#markup' => '<p><strong>Office bewaakt nu ook de debiteurenstatus.</strong> Betwiste facturen, betalingsregelingen en holds worden niet automatisch geëscaleerd. Betaalde, gecrediteerde en geannuleerde facturen stoppen de keten direct.</p>',
      ],
      'invoices' => [
        '#type' => 'table',
        '#header' => [$this->t('Factuur'), $this->t('Project'), $this->t('Factuurdatum'), $this->t('Vervaldatum'), $this->t('Debiteurenstatus'), $this->t('Bedrag'), $this->t('Openstaand'), $this->t('Volgende actie')],
        '#rows' => $rows,
        '#empty' => $this->t('Nog geen verkoopfacturen beschikbaar.'),
      ],
      '#attached' => ['library' => ['brebo_finance/command_center']],
      '#cache' => ['contexts' => ['user.permissions'], 'max-age' => 0],
    ];
  }

  private function receivablesStatusLabel(string $status): string {
    return match ($status) {
      'open' => (string) $this->t('Open'),
      'vervallen' => (string) $this->t('Vervallen'),
      'herinnerd' => (string) $this->t('Herinnerd'),
      'aangemaand' => (string) $this->t('Aangemaand'),
      'laatste_sommatie' => (string) $this->t('Laatste sommatie'),
      'gereed_voor_incasso' => (string) $this->t('Gereed voor incasso'),
      'regeling' => (string) $this->t('Betalingsregeling'),
      'betwist' => (string) $this->t('Betwist'),
      'hold' => (string) $this->t('Hold'),
      'betaald' => (string) $this->t('Betaald'),
      'gecrediteerd' => (string) $this->t('Gecrediteerd'),
      'gesloten' => (string) $this->t('Gesloten'),
      default => $status,
    };
  }

  /** @param array<string,mixed> $state */
  private function receivablesActionLabel(array $state, ?string $nextStep): string {
    if ($state['blocked_reason'] !== NULL) {
      return match ($state['blocked_reason']) {
        'disputed' => (string) $this->t('Geblokkeerd: betwist'),
        'payment_arrangement' => (string) $this->t('Geblokkeerd: regeling'),
        'manual_hold' => (string) $this->t('Geblokkeerd: hold'),
        default => (string) $this->t('Geblokkeerd'),
      };
    }
    if ($nextStep === NULL) {
      return '—';
    }
    return match ($nextStep) {
      'reminder' => (string) $this->t('Herinnering klaarzetten'),
      'demand' => (string) $this->t('Aanmaning klaarzetten'),
      'final_notice' => (string) $this->t('Laatste sommatie klaarzetten'),
      'collection_ready' => (string) $this->t('Dossier gereed voor incasso'),
      default => $nextStep,
    };
  }
}
