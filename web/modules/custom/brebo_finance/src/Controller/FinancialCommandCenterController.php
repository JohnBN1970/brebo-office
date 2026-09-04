<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\BusinessHealthBuilder;
use Drupal\brebo_finance\Service\BusinessHealthIntegrationClient;
use Drupal\brebo_finance\Service\FinancialCommandCenter;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Exposes the BREBO organisation-wide financial command center. */
final class FinancialCommandCenterController extends ControllerBase {

  public function __construct(
    private readonly FinancialCommandCenter $commandCenter,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly BusinessHealthBuilder $businessHealth,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_finance.financial_command_center'),
      $container->get('date.formatter'),
      new BusinessHealthBuilder(
        new BusinessHealthIntegrationClient($container->get('http_client')),
        $container->get('config.factory'),
        $container->get('cache.default'),
      ),
    );
  }

  public function page(): array {
    $sync = $this->commandCenter->receivablesSyncHealth();
    $syncAttention = !empty($sync['requires_attention']);
    $lastSuccess = isset($sync['last_success_completed_at']) ? (int) $sync['last_success_completed_at'] : NULL;
    $syncLabel = $lastSuccess !== NULL
      ? $this->t('Laatste succesvolle Moneybird debiteurensync: @date', ['@date' => $this->dateFormatter->format($lastSuccess, 'custom', 'd-m-Y H:i')])
      : $this->t('Er is nog geen succesvolle Moneybird debiteurensync geregistreerd.');
    $syncError = $syncAttention && !empty($sync['operator_message'])
      ? '<br><strong>' . $this->t('Actie vereist:') . '</strong> ' . $this->t((string) $sync['operator_message'])
      : '';
    $decisionUrl = Url::fromRoute('brebo_finance.financial_decision_page');
    $canOpenDecisionInbox = $decisionUrl->access($this->currentUser());

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'brebo-finance-command-center',
        'class' => ['brebo-finance-command-center'],
        'data-api-url' => Url::fromRoute('brebo_finance.command_center_api')->toString(),
        'data-decision-url' => $canOpenDecisionInbox ? $decisionUrl->toString() : '',
        'data-payables-url' => Url::fromRoute('brebo_finance.payables_work_queues')->toString(),
        'data-purchase-invoices-url' => Url::fromRoute('brebo_finance.purchase_invoice_list')->toString(),
      ],
      'header' => [
        '#markup' => '<header class="bfcc-header"><div><span class="bfcc-kicker">BREBO OFFICE · FINANCE</span><h1>Finance</h1><p>Financieel commandocentrum voor resultaat, liquiditeit, inkoop, verkoop, vaste kosten, risico en besluiten.</p></div><div class="bfcc-live">LIVE CONTROL</div></header>',
      ],
      'navigation' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['bfcc-section']],
        'links' => [
          '#theme' => 'item_list',
          '#items' => [
            Link::fromTextAndUrl($this->t('Dashboard'), Url::fromRoute('brebo_finance.command_center_page')),
            Link::fromTextAndUrl($this->t('Te doen · Inkoop & betaling'), Url::fromRoute('brebo_finance.payables_work_queues')),
            Link::fromTextAndUrl($this->t('Inkoopfacturen'), Url::fromRoute('brebo_finance.purchase_invoice_list')),
            Link::fromTextAndUrl($this->t('Betaalcentrum'), Url::fromRoute('brebo_finance.payment_center')),
            ['#markup' => '<a href="#bfcc-sales">' . $this->t('Verkoop & debiteuren') . '</a>'],
            ['#markup' => '<a href="#bfcc-business-health">' . $this->t('Bedrijfsgezondheid') . '</a>'],
          ],
          '#attributes' => ['class' => ['bfcc-finance-nav']],
        ],
      ],
      'sync_health' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['bfcc-section', $syncAttention ? 'bfcc-sync-warning' : 'bfcc-sync-ok']],
        'content' => [
          '#markup' => '<span class="bfcc-kicker">MONEYBIRD DEBITEUREN</span><p><strong>' . ($syncAttention ? $this->t('Synchronisatie vraagt aandacht') : $this->t('Synchronisatie actief')) . '</strong><br>' . $syncLabel . $syncError . '</p>',
        ],
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

  public function api(): JsonResponse {
    $data = $this->commandCenter->dashboard($this->currentUser());
    $data['business_health'] = $this->businessHealth->build();
    $response = new JsonResponse($data);
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    return $response;
  }
}
