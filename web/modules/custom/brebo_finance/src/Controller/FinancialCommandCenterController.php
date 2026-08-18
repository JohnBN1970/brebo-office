<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\FinancialCommandCenter;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Exposes the BREBO portfolio financial command center. */
final class FinancialCommandCenterController extends ControllerBase {

  public function __construct(private readonly FinancialCommandCenter $commandCenter) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_finance.financial_command_center'));
  }

  public function page(): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'brebo-finance-command-center',
        'class' => ['brebo-finance-command-center'],
        'data-api-url' => '/brebo-office/api/finance/command-center',
        'data-decision-url' => '/brebo-office/finance/decision-inbox',
      ],
      'header' => [
        '#markup' => '<header class="bfcc-header"><div><span class="bfcc-kicker">BREBO OFFICE · FINANCE</span><h1>Financieel Command Center</h1><p>Portfolio-overzicht van geld, risico, verplichtingen en beslissingen.</p></div><div class="bfcc-live">LIVE CONTROL</div></header>',
      ],
      'content' => ['#markup' => '<div data-bfcc-content><div class="bfcc-loading">Financiële positie laden…</div></div>'],
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
