<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\PayablesWorkQueueBuilder;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Daily payables work queues for BREBO Office Finance. */
final class PayablesWorkQueueController extends ControllerBase {

  public function __construct(private readonly PayablesWorkQueueBuilder $builder) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_finance.payables_work_queue_builder'));
  }

  public function page(): array {
    $data = $this->builder->build($this->currentUser());
    $labels = [
      'to_code' => ['Te coderen', 'Project, factuurregels of commitmentkoppeling ontbreekt.'],
      'blocked' => ['Geblokkeerd', 'Prestatie-, match- of controlafwijking vraagt eerst oplossing.'],
      'to_match' => ['Te matchen', 'Codering staat; voer de authoritative three-way match uit.'],
      'release_ready' => ['Vrijgave aanvragen', 'Volledig matched en klaar voor betaalvrijgave.'],
      'to_approve' => ['Goed te keuren', 'Betaalvrijgave wacht op onafhankelijke goedkeuring.'],
      'ready_to_pay' => ['Klaar om te betalen', 'Goedgekeurde vrijgave wacht op betalingsuitvoering.'],
    ];
    $paymentCenterUrl = Url::fromRoute('brebo_finance.payment_center')->toString();

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-finance-payables-queues']],
      'header' => [
        '#markup' => '<header class="bfcc-header"><div><span class="bfcc-kicker">BREBO OFFICE · FINANCE</span><h1>Te doen · Inkoop & betaling</h1><p>Dagelijkse werkvoorraad van ontvangen factuur tot gecontroleerde betaling.</p></div><a class="bfcc-live" href="' . $paymentCenterUrl . '">LIVE CONTROL</a></header>',
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
            Link::fromTextAndUrl($this->t('Betaalcentrum'), Url::fromRoute('brebo_finance.payment_center')),
          ],
          '#attributes' => ['class' => ['bfcc-finance-nav']],
        ],
      ],
      'summary' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['bfcc-grid']],
      ],
      '#attached' => ['library' => ['brebo_finance/command_center']],
      '#cache' => ['max-age' => 0],
    ];

    foreach ($labels as $key => [$title, $description]) {
      $count = (int) ($data['counts'][$key] ?? 0);
      $amount = $this->money($data['amounts_inc_vat'][$key] ?? 0);
      $build['summary'][$key] = [
        '#markup' => '<article class="bfcc-card"><span class="bfcc-kicker">' . $this->t('@count items', ['@count' => $count]) . '</span><h2>' . $title . '</h2><strong>' . $amount . '</strong><p>' . $description . '</p></article>',
      ];
    }

    foreach ($labels as $key => [$title, $description]) {
      $rows = [];
      foreach ((array) ($data['queues'][$key] ?? []) as $item) {
        $rows[] = [
          Link::fromTextAndUrl((string) $item['invoice_number'], Url::fromRoute('brebo_finance.purchase_invoice_view', ['invoice_id' => $item['invoice_id']])),
          (string) $item['supplier_name'],
          (string) $item['project_label'],
          (string) $item['due_date'],
          (string) $item['priority'],
          $this->money($item['amount_inc_vat']),
          (string) $item['match_status'],
          (string) $item['blocked_lines'],
        ];
      }
      $build['queue_' . $key] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['bfcc-section']],
        'title' => ['#markup' => '<h2>' . $title . '</h2><p>' . $description . '</p>'],
        'table' => [
          '#type' => 'table',
          '#header' => ['Factuur', 'Leverancier', 'Project', 'Vervaldatum', 'Prioriteit', 'Incl. btw', 'Match', 'Blokkades'],
          '#rows' => $rows,
          '#empty' => 'Geen items in deze werkvoorraad.',
        ],
      ];
    }

    return $build;
  }

  public function api(): JsonResponse {
    $response = new JsonResponse($this->builder->build($this->currentUser()));
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

  private function money(mixed $value): string {
    return '€ ' . number_format((float) $value, 2, ',', '.');
  }
}
