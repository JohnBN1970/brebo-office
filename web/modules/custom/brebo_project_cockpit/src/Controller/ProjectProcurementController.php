<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Controller;

use Drupal\brebo_finance\Service\FinancialCockpitBuilder;
use Drupal\brebo_finance\Service\FinancialProjectLedger;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Shows the project-filtered procurement chain. */
final class ProjectProcurementController extends ControllerBase {

  public function __construct(
    private readonly FinancialProjectLedger $ledger,
    private readonly FinancialCockpitBuilder $cockpitBuilder,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_finance.financial_project_ledger'),
      $container->get('brebo_finance.financial_cockpit_builder'),
    );
  }

  public function title(NodeInterface $node): string {
    $this->assertProject($node);
    return (string) $this->t('Inkoop — @project', ['@project' => $node->label()]);
  }

  public function overview(NodeInterface $node): array {
    $this->assertProject($node);
    $projectId = (int) $node->id();
    $ledger = $this->ledger->build($projectId);
    $finance = $this->cockpitBuilder->build($projectId);
    $pipeline = is_array($finance['procurement_pipeline'] ?? NULL) ? $finance['procurement_pipeline'] : [];

    $commitments = is_array($ledger['commitments'] ?? NULL) ? $ledger['commitments'] : [];
    $invoices = is_array($ledger['purchase_invoices'] ?? NULL) ? $ledger['purchase_invoices'] : [];
    $releases = is_array($ledger['payment_releases'] ?? NULL) ? $ledger['payment_releases'] : [];
    $blockers = is_array($ledger['invoice_performance_blockers'] ?? NULL) ? $ledger['invoice_performance_blockers'] : [];

    $commitmentRows = [];
    foreach ($commitments as $row) {
      $commitmentRows[] = [
        (string) ($row['commitment_number'] ?? '—'),
        (string) ($row['supplier_name'] ?? '—'),
        (string) ($row['status'] ?? '—'),
        $this->money($row['amount_ex_vat'] ?? NULL),
        $this->money($row['amount_inc_vat'] ?? NULL),
      ];
    }

    $invoiceRows = [];
    foreach ($invoices as $row) {
      $invoiceRows[] = [
        (string) ($row['supplier_name'] ?? '—'),
        (string) ($row['invoice_number'] ?? '—'),
        (string) ($row['invoice_date'] ?? '—'),
        (string) ($row['status'] ?? '—'),
        (string) ($row['match_status'] ?? '—'),
        $this->money($row['amount_ex_vat'] ?? NULL),
        $this->money($row['amount_inc_vat'] ?? NULL),
      ];
    }

    $paid = 0.0;
    foreach ($releases as $row) {
      if (in_array((string) ($row['status'] ?? ''), ['released', 'paid', 'completed'], TRUE)) {
        $paid += (float) ($row['payment_amount'] ?? 0) + (float) ($row['g_account_amount'] ?? 0);
      }
    }

    return [
      'principle' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-procurement-principle']],
        'title' => ['#markup' => '<h2>' . $this->t('Inkoopketen van het project') . '</h2>'],
        'text' => ['#markup' => '<p>' . $this->t('Werkbegroting → opdracht/verplichting → prestatie → inkoopfactuur → betaling. Inkoopfacturen zijn hier projectgefilterd zichtbaar; de financiële administratie blijft één bron en wordt niet gedupliceerd.') . '</p>'],
      ],
      'kpis' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-procurement-kpis']],
        'budget' => ['#markup' => $this->kpi('Begroot', $pipeline['budget_ex_vat'] ?? $pipeline['current_budget_ex_vat'] ?? NULL, 'excl. btw')],
        'committed' => ['#markup' => $this->kpi('Besteld / verplicht', $pipeline['committed_ex_vat'] ?? NULL, 'excl. btw')],
        'invoiced' => ['#markup' => $this->kpi('Gefactureerd', $pipeline['invoiced_ex_vat'] ?? NULL, 'excl. btw')],
        'paid' => ['#markup' => $this->kpi('Betaald', $pipeline['paid_inc_vat'] ?? $paid, 'incl. btw')],
        'open' => ['#markup' => '<div class="brebo-procurement-kpi"><strong>' . count($blockers) . '</strong><span>match-/prestatieblokkades</span></div>'],
      ],
      'commitments' => [
        '#type' => 'details',
        '#title' => $this->t('Opdrachten en verplichtingen (@count)', ['@count' => count($commitmentRows)]),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Opdracht'), $this->t('Leverancier'), $this->t('Status'), $this->t('Bedrag excl. btw'), $this->t('Bedrag incl. btw')],
          '#rows' => $commitmentRows,
          '#empty' => $this->t('Voor dit project zijn nog geen inkoopopdrachten of verplichtingen geregistreerd.'),
        ],
      ],
      'invoices' => [
        '#type' => 'details',
        '#title' => $this->t('Inkoopfacturen — projectgefilterd (@count)', ['@count' => count($invoiceRows)]),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Leverancier'), $this->t('Factuur'), $this->t('Factuurdatum'), $this->t('Status'), $this->t('3-way match'), $this->t('Excl. btw'), $this->t('Incl. btw')],
          '#rows' => $invoiceRows,
          '#empty' => $this->t('Voor dit project zijn nog geen inkoopfacturen geregistreerd.'),
        ],
      ],
      'control' => [
        '#type' => 'details',
        '#title' => $this->t('Inkoopcontrole'),
        '#open' => count($blockers) > 0,
        'summary' => ['#markup' => '<p>' . $this->t('@count factuurregel(s) vragen aandacht vanwege prestatie- of matchcontrole.', ['@count' => count($blockers)]) . '</p>'],
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['node:' . $projectId],
        'max-age' => 0,
      ],
    ];
  }

  private function kpi(string $label, mixed $value, string $basis): string {
    return '<div class="brebo-procurement-kpi"><strong>' . $this->money($value) . '</strong><span>' . $label . ' · ' . $basis . '</span></div>';
  }

  private function money(mixed $value): string {
    if (!is_numeric($value)) {
      return '—';
    }
    return '€ ' . number_format((float) $value, 2, ',', '.');
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException('BREBO project does not exist.');
    }
  }

}
