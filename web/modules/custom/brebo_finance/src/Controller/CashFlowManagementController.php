<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\BusinessHealthBuilder;
use Drupal\brebo_finance\Service\BusinessHealthIntegrationClient;
use Drupal\brebo_finance\Service\CashFlowManagementReportBuilder;
use Drupal\brebo_finance\Service\PortfolioLiquidityProjection;
use Drupal\brebo_finance\Service\VatCalculator;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Organisation-wide cashflow and management reporting page. */
final class CashFlowManagementController extends ControllerBase {

  public function __construct(
    private readonly CashFlowManagementReportBuilder $builder,
    private readonly BusinessHealthBuilder $businessHealth,
    private readonly PortfolioLiquidityProjection $liquidity,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      new CashFlowManagementReportBuilder($container->get('database')),
      new BusinessHealthBuilder(
        new BusinessHealthIntegrationClient($container->get('http_client')),
        $container->get('config.factory'),
        $container->get('cache.default'),
      ),
      new PortfolioLiquidityProjection(
        $container->get('database'),
        $container->get('entity_type.manager'),
        $container->get('config.factory'),
        new VatCalculator(),
      ),
    );
  }

  public function page(): array {
    $report = $this->builder->build();
    $sales = $report['sales'];
    $forecast = $report['forecast'];
    $health = $this->businessHealth->build();
    $liquidity = $this->liquidity->build($this->currentUser(), $health);

    $weekRows = [];
    foreach ($forecast['weeks'] as $week) {
      $weekRows[] = [
        'Week ' . $week['week'],
        $week['start_date'] . ' t/m ' . $week['end_date'],
        $this->money((float) $week['incoming']),
        $this->money((float) $week['outgoing']),
        $this->money((float) $week['net']),
        $this->money((float) $week['g_account_incoming']),
        $this->money((float) $week['g_account_outgoing']),
      ];
    }

    $signalItems = '';
    foreach ($report['signals'] as $signal) {
      $signalItems .= '<li>' . htmlspecialchars((string) $signal, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    foreach (($liquidity['reasons'] ?? []) as $reason) {
      $signalItems .= '<li>' . htmlspecialchars((string) $reason, ENT_QUOTES, 'UTF-8') . '</li>';
    }

    $openingRegular = (float) ($liquidity['opening']['regular'] ?? 0);
    $openingG = (float) ($liquidity['opening']['g_account'] ?? 0);
    $horizon90 = $liquidity['horizons']['90']['expected'] ?? NULL;
    $projectedRegular = is_array($horizon90) ? (float) ($horizon90['regular']['projected_balance'] ?? 0) : NULL;
    $projectedG = is_array($horizon90) ? (float) ($horizon90['g_account']['projected_balance'] ?? 0) : NULL;

    $healthMarkup = '<section><h2>Directiebeeld</h2>';
    if (($health['status'] ?? 'unavailable') === 'ok') {
      $pl = $health['profit_loss'] ?? [];
      $healthMarkup .= '<p>Omzet <strong>' . $this->money((float) ($pl['revenue'] ?? 0)) . '</strong> · brutowinst <strong>' . $this->money((float) ($pl['gross_profit'] ?? 0)) . '</strong> · resultaat <strong>' . $this->money((float) ($pl['net_profit'] ?? 0)) . '</strong>.</p>';
      if (($health['break_even']['monthly_revenue_required'] ?? NULL) !== NULL) {
        $healthMarkup .= '<p>Benodigde maandelijkse break-evenomzet: <strong>' . $this->money((float) $health['break_even']['monthly_revenue_required']) . '</strong>.</p>';
      }
    }
    else {
      $healthMarkup .= '<p>Moneybird bedrijfsgezondheid is tijdelijk niet beschikbaar; de operationele Office-data hieronder blijft bruikbaar.</p>';
    }
    $healthMarkup .= '</section>';

    $liquidityMarkup = '<section><h2>Liquiditeit</h2><p>Actueel aantoonbaar saldo regulier <strong>' . $this->money($openingRegular) . '</strong> · G-rekening <strong>' . $this->money($openingG) . '</strong>.</p>';
    if ($projectedRegular !== NULL && $projectedG !== NULL) {
      $liquidityMarkup .= '<p>Verwacht na 90 dagen: regulier <strong>' . $this->money($projectedRegular) . '</strong> · G-rekening <strong>' . $this->money($projectedG) . '</strong>.</p>';
    }
    $liquidityMarkup .= '</section>';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-finance-cashflow-management']],
      'header' => ['#markup' => '<header class="bfcc-header"><div><span class="bfcc-kicker">BREBO OFFICE · MANAGEMENT</span><h1>Cashflow & managementrapportage</h1><p>Directiesturing en controllercontrole vanuit dezelfde aantoonbare financiële bronnen.</p></div><div class="bfcc-live">13 WEKEN</div></header>'],
      'kpis' => ['#markup' => '<div class="bfcc-grid">'
        . $this->card('Banksaldo regulier', $openingRegular)
        . $this->card('G-rekening', $openingG)
        . $this->card('Gefactureerd', (float) $sales['invoiced'])
        . $this->card('Ontvangen', (float) $sales['received'])
        . $this->card('Openstaand', (float) $sales['outstanding'])
        . $this->card('Vervallen', (float) $sales['overdue'])
        . $this->card('13w inkomend', (float) $forecast['incoming_13w'])
        . $this->card('13w uitgaand', (float) $forecast['outgoing_13w'])
        . $this->card('13w netto beweging', (float) $forecast['net_13w'])
        . '</div>'],
      'director' => ['#markup' => $healthMarkup],
      'liquidity' => ['#markup' => $liquidityMarkup],
      'signals' => ['#markup' => '<section><h2>Controller · aandacht en acties</h2><ul>' . $signalItems . '</ul></section>'],
      'weeks' => [
        '#type' => 'table',
        '#caption' => $this->t('Controller · 13-weeks cashflowprognose'),
        '#header' => [$this->t('Week'), $this->t('Periode'), $this->t('Inkomend'), $this->t('Uitgaand'), $this->t('Netto'), $this->t('G-rekening in'), $this->t('G-rekening uit')],
        '#rows' => $weekRows,
      ],
      'debtor' => ['#markup' => '<section><h2>Controller · debiteurenpositie</h2><p><strong>' . (int) $sales['open_count'] . '</strong> openstaande facturen · ' . $this->money((float) $sales['overdue']) . ' vervallen · ' . $this->money((float) $sales['disputed']) . ' in geschil.</p></section>'],
      'source_note' => ['#markup' => '<p><em>Bronnen: Moneybird bedrijfsgezondheid en bankrekeningen via de BREBO Integration API, definitieve verkoopfacturen en brongebonden BREBO cash-events. Geen ontbrekende bedragen worden ingevuld of geschat.</em></p>'],
      '#attached' => ['library' => ['brebo_finance/command_center']],
      '#cache' => ['max-age' => 0],
    ];
  }

  private function card(string $label, float $amount): string {
    return '<article class="bfcc-card"><span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span><strong>' . $this->money($amount) . '</strong></article>';
  }

  private function money(float $amount): string {
    return '€ ' . number_format($amount, 2, ',', '.');
  }
}
