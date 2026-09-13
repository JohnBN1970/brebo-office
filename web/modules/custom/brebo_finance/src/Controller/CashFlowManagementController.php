<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\CashFlowManagementReportBuilder;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Organisation-wide cashflow and management reporting page. */
final class CashFlowManagementController extends ControllerBase {

  public function __construct(private readonly CashFlowManagementReportBuilder $builder) {}

  public static function create(ContainerInterface $container): static {
    return new static(new CashFlowManagementReportBuilder($container->get('database')));
  }

  public function page(): array {
    $report = $this->builder->build();
    $sales = $report['sales'];
    $forecast = $report['forecast'];

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
    foreach ($report['signals'] as $signal) $signalItems .= '<li>' . htmlspecialchars((string) $signal, ENT_QUOTES, 'UTF-8') . '</li>';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-finance-cashflow-management']],
      'header' => ['#markup' => '<header class="bfcc-header"><div><span class="bfcc-kicker">BREBO OFFICE · MANAGEMENT</span><h1>Cashflow & managementrapportage</h1><p>Bedrijfsbrede liquiditeits- en debiteurensturing op basis van aantoonbare brondata.</p></div><div class="bfcc-live">13 WEKEN</div></header>'],
      'kpis' => ['#markup' => '<div class="bfcc-grid">'
        . $this->card('Gefactureerd', (float) $sales['invoiced'])
        . $this->card('Ontvangen', (float) $sales['received'])
        . $this->card('Openstaand', (float) $sales['outstanding'])
        . $this->card('Vervallen', (float) $sales['overdue'])
        . $this->card('13w inkomend', (float) $forecast['incoming_13w'])
        . $this->card('13w uitgaand', (float) $forecast['outgoing_13w'])
        . $this->card('13w netto beweging', (float) $forecast['net_13w'])
        . '</div>'],
      'signals' => ['#markup' => '<section><h2>Managementsignalen</h2><ul>' . $signalItems . '</ul></section>'],
      'weeks' => [
        '#type' => 'table',
        '#caption' => $this->t('13-weeks cashflowprognose'),
        '#header' => [$this->t('Week'), $this->t('Periode'), $this->t('Inkomend'), $this->t('Uitgaand'), $this->t('Netto'), $this->t('G-rekening in'), $this->t('G-rekening uit')],
        '#rows' => $weekRows,
      ],
      'debtor' => ['#markup' => '<section><h2>Debiteurenpositie</h2><p><strong>' . (int) $sales['open_count'] . '</strong> openstaande facturen · ' . $this->money((float) $sales['overdue']) . ' vervallen · ' . $this->money((float) $sales['disputed']) . ' in geschil.</p></section>'],
      'source_note' => ['#markup' => '<p><em>' . htmlspecialchars((string) $report['note'], ENT_QUOTES, 'UTF-8') . '</em></p>'],
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
