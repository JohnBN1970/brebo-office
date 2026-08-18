<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\brebo_office_core\Service\ProjectFinancialControl;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Live financial cockpit for one BREBO project.
 */
final class ProjectFinancialController extends ControllerBase {

  public function __construct(private readonly ProjectFinancialControl $financialControl) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_office_core.project_financial_control'));
  }

  public function title(NodeInterface $node): string {
    $this->assertProject($node);
    return (string) $this->t('Financiële cockpit · @project', ['@project' => $node->label()]);
  }

  public function overview(NodeInterface $node): array {
    $this->assertProject($node);
    $result = $this->financialControl->analyze($node);
    $money = static fn (float $value): string => '€ ' . number_format($value, 2, ',', '.');

    $laborRows = [];
    foreach ($result['rows'] as $row) {
      $laborRows[] = [$row['label'], number_format((float) $row['budget_hours'], 2, ',', '.'), number_format((float) $row['actual_hours'], 2, ',', '.'), number_format((float) $row['forecast_hours'], 2, ',', '.'), $money((float) $row['labor_rate']), $money((float) $row['actual_labor_cost']), $money((float) $row['forecast_labor_cost']), $row['status']];
    }

    $commitments = [];
    foreach ($result['commitment_rows'] as $row) {
      $commitments[] = [$row['supplier'], $row['quote_number'] ?: '—', $row['rfq'], $money((float) $row['amount']), $row['status'] ?: 'Geselecteerd'];
    }

    $transactions = [];
    foreach ($result['transaction_rows'] as $row) {
      $transactions[] = [$row['supplier'], $row['invoice_number'], $money((float) $row['gross_amount']), $row['match_status'], $row['approval_status'], $money((float) $row['paid_amount']), $money((float) $row['paid_g_account']), $row['payment_status']];
    }

    return [
      'status' => ['#type' => 'container', '#attributes' => ['class' => ['messages', $result['status'] === 'Akkoord' ? 'messages--status' : 'messages--warning']], 'text' => ['#markup' => '<strong>' . $this->t('Projectstatus: @status', ['@status' => $result['status']]) . '</strong>']],
      'summary' => [
        '#type' => 'table',
        '#header' => [$this->t('Budget kostprijs'), $this->t('Werkelijk totaal'), $this->t('Inkoop verplicht'), $this->t('Prognose kostprijs'), $this->t('Afwijking')],
        '#rows' => [[$money((float) $result['budget_cost']), $money((float) $result['actual_total_cost']), $money((float) $result['commitment_cost']), $money((float) $result['forecast_cost']), $money((float) $result['variance'])]],
      ],
      'cashflow' => [
        '#type' => 'table',
        '#header' => [$this->t('Besteld'), $this->t('Gefactureerd'), $this->t('Goedgekeurd'), $this->t('Betaald'), $this->t('waarvan G-rekening'), $this->t('Openstaand')],
        '#rows' => [[$money((float) $result['ordered_cost']), $money((float) $result['invoiced_cost']), $money((float) $result['approved_invoice_cost']), $money((float) $result['paid_cost']), $money((float) $result['paid_g_account']), $money((float) $result['open_payables'])]],
      ],
      'coverage' => [
        '#type' => 'table',
        '#header' => [$this->t('Werkbegrotingen'), $this->t('Regels'), $this->t('Niet-arbeidsbudget'), $this->t('Inkoopdekking'), $this->t('Geblokkeerde facturen'), $this->t('Vervallen facturen')],
        '#rows' => [[$result['work_budgets'], $result['lines'], $money((float) $result['non_labor_budget']), number_format((float) $result['commitment_coverage_pct'], 1, ',', '.') . '%', $result['blocked_invoices'], $result['overdue_invoices']]],
      ],
      'hours' => ['#type' => 'table', '#header' => [$this->t('Budgeturen'), $this->t('Goedgekeurd werkelijk'), $this->t('Prognose einduren')], '#rows' => [[number_format((float) $result['budget_hours'], 2, ',', '.'), number_format((float) $result['actual_hours'], 2, ',', '.'), number_format((float) $result['forecast_hours'], 2, ',', '.')]]],
      'scope' => ['#type' => 'container', '#attributes' => ['class' => ['messages', 'messages--status']], 'text' => ['#markup' => $this->t('<strong>Datadekking:</strong> @scope', ['@scope' => $result['actual_scope']])]],
      'signals_heading' => ['#markup' => '<h2>' . $this->t('Digitale controller · signalen') . '</h2>'],
      'signals' => ['#theme' => 'item_list', '#items' => $result['signals'] ?: [$this->t('Geen financiële afwijkingen uit de beschikbare projectdata.')]],
      'transactions_heading' => ['#markup' => '<h2>' . $this->t('Leveranciersfacturen & betalingen') . '</h2>'],
      'transactions' => [
        '#type' => 'table',
        '#header' => [$this->t('Leverancier'), $this->t('Factuur'), $this->t('Bruto'), $this->t('3-way match'), $this->t('Goedkeuring'), $this->t('Betaald'), $this->t('G-rekening'), $this->t('Betaalstatus')],
        '#rows' => $transactions,
        '#empty' => $this->t('Nog geen financiële transacties voor dit project.'),
      ],
      'commitments_heading' => ['#markup' => '<h2>' . $this->t('Inkoopvoortraject') . '</h2>'],
      'commitments' => ['#type' => 'table', '#header' => [$this->t('Leverancier'), $this->t('Offertenummer'), $this->t('Prijsaanvraag'), $this->t('Geselecteerd bedrag'), $this->t('Status')], '#rows' => $commitments, '#empty' => $this->t('Nog geen geselecteerde leveranciersoffertes.')],
      'labor_heading' => ['#markup' => '<h2>' . $this->t('Arbeidsprognose per werkbegrotingsregel') . '</h2>'],
      'labor' => ['#type' => 'table', '#header' => [$this->t('Regel'), $this->t('Budget u'), $this->t('Werkelijk u'), $this->t('Prognose u'), $this->t('Kostprijs/u'), $this->t('Werkelijk €'), $this->t('Prognose €'), $this->t('Signaal')], '#rows' => $laborRows, '#empty' => $this->t('Nog geen werkbegrotingsregels beschikbaar.')],
      '#cache' => ['max-age' => 0],
    ];
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
  }

}
