<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\FinancialCommandCenter;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Exposes the BREBO organisation-wide financial command center. */
final class FinancialCommandCenterController extends ControllerBase {

  public function __construct(private readonly FinancialCommandCenter $commandCenter) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_finance.financial_command_center'));
  }

  public function page(): array {
    $commandCenter = $this->commandCenter->build($this->currentUser());
    $sync = $commandCenter['receivables_sync'] ?? [];
    $syncAttention = !empty($sync['requires_attention']);
    $lastSuccess = isset($sync['last_success_completed_at']) ? (int) $sync['last_success_completed_at'] : NULL;
    $syncLabel = $lastSuccess !== NULL
      ? $this->t('Laatste succesvolle Moneybird debiteurensync: @date', ['@date' => date('d-m-Y H:i', $lastSuccess)])
      : $this->t('Er is nog geen succesvolle Moneybird debiteurensync geregistreerd.');
    $syncError = $syncAttention && !empty($sync['error'])
      ? '<br><strong>' . $this->t('Actie vereist:') . '</strong> ' . htmlspecialchars((string) $sync['error'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
      : '';

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'brebo-finance-command-center',
        'class' => ['brebo-finance-command-center'],
      ],
      'header' => [
        '#markup' => '<header class="bfcc-header"><div><span class="bfcc-kicker">BREBO OFFICE · FINANCE</span><h1>Finance</h1><p>Organisatiebreed financieel overzicht. Projectgebonden processen blijven onder Projecten.</p></div><div class="bfcc-live">LIVE CONTROL</div></header>',
      ],
      'navigation' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['bfcc-section']],
        'links' => [
          '#theme' => 'item_list',
          '#items' => [
            Link::fromTextAndUrl($this->t('Dashboard'), Url::fromRoute('brebo_finance.command_center_page')),
            Link::fromTextAndUrl($this->t('Te doen'), Url::fromRoute('brebo_finance.payables_work_queues')),
            Link::fromTextAndUrl($this->t('Inkoopfacturen'), Url::fromRoute('brebo_finance.purchase_invoice_list')),
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
        '#attributes' => ['class' => ['bfcc-section']],
        'title' => ['#markup' => '<span class="bfcc-kicker">ORGANISATIE</span><h2>Financieel dashboard</h2>'],
        'intro' => ['#markup' => '<p>De organisatiebrede KPI-laag wordt hierna aangesloten op de maatgevende Moneybird-rubrieken. Tot die tijd worden geen projectwaarden als ondernemings-KPI gepresenteerd.</p>'],
        'payables' => [
          '#markup' => '<p><strong>Dagelijkse werkvoorraad:</strong> ' . Link::fromTextAndUrl($this->t('open Te doen · Inkoop & betaling'), Url::fromRoute('brebo_finance.payables_work_queues'))->toString() . '</p>',
        ],
        'purchase_invoices' => [
          '#markup' => '<p><strong>Alle inkoopfacturen:</strong> ' . Link::fromTextAndUrl($this->t('open Inkoopfacturen'), Url::fromRoute('brebo_finance.purchase_invoice_list'))->toString() . '</p>',
        ],
      ],
      '#attached' => ['library' => ['brebo_finance/command_center']],
      '#cache' => ['max-age' => 0],
    ];
  }

  public function api(): JsonResponse {
    $response = new JsonResponse($this->commandCenter->build($this->currentUser()));
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    return $response;
  }
}
