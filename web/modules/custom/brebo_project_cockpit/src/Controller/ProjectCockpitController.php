<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Controller;

use Drupal\brebo_finance\Service\FinancialCockpitBuilder;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Central steering cockpit for one BREBO project.
 */
final class ProjectCockpitController extends ControllerBase {

  public function __construct(
    private readonly FinancialCockpitBuilder $financialCockpitBuilder,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_finance.financial_cockpit_builder'));
  }

  public function title(NodeInterface $node): string {
    $this->assertProject($node);
    return (string) $node->label();
  }

  public function overview(NodeInterface $node): array {
    $this->assertProject($node);
    $projectId = (int) $node->id();
    $finance = $this->financialCockpitBuilder->build($projectId);
    $forecast = is_array($finance['forecast'] ?? NULL) ? $finance['forecast'] : [];
    $procurement = is_array($finance['procurement_pipeline'] ?? NULL) ? $finance['procurement_pipeline'] : [];
    $billing = is_array($finance['billing_position'] ?? NULL) ? $finance['billing_position'] : [];
    $cashCommitted = is_array($finance['cash_forecast']['committed'] ?? NULL) ? $finance['cash_forecast']['committed'] : [];

    $redClock = $this->countClockSeverity($projectId, 'rood');
    $orangeClock = $this->countClockSeverity($projectId, 'oranje');
    $activePeople = $this->countProjectNodes('brebo_clock_registration', $projectId, ['field_brebo_clock_status' => 'Open']);
    $financeStatus = $this->financeStatus($finance, $forecast, $cashCommitted);
    $inzetStatus = $redClock > 0 ? 'rood' : ($orangeClock > 0 ? 'oranje' : 'groen');
    $projectStatus = $this->worstStatus([$financeStatus, $inzetStatus]);

    $attention = [];
    if ($redClock > 0) {
      $attention[] = $this->t('@count rode klokafwijking(en) vragen directe controle.', ['@count' => $redClock]);
    }
    if ($orangeClock > 0) {
      $attention[] = $this->t('@count oranje klokafwijking(en) vragen beoordeling.', ['@count' => $orangeClock]);
    }
    if (!empty($finance['forecast_is_stale'])) {
      $attention[] = $this->t('De financiële prognose ontbreekt of is ouder dan 30 dagen.');
    }
    if ($this->decimalNegative($forecast['forecast_result_ex_vat'] ?? NULL)) {
      $attention[] = $this->t('De actuele prognose toont een negatief projectresultaat.');
    }
    if (!empty($cashCommitted['first_regular_shortfall_date'])) {
      $attention[] = $this->t('De committed cashforecast voorspelt een tekort op de reguliere rekening vanaf @date.', ['@date' => $cashCommitted['first_regular_shortfall_date']]);
    }
    if ((int) ($billing['overdue_count'] ?? 0) > 0) {
      $attention[] = $this->t('@count verkoopfactuur/facturen zijn vervallen.', ['@count' => (int) $billing['overdue_count']]);
    }
    if ($attention === []) {
      $attention[] = $this->t('Geen directe stuurafwijkingen uit de momenteel aangesloten bronnen.');
    }

    $committed = $this->number($procurement['committed_ex_vat'] ?? NULL);
    $purchaseInvoiced = $this->number($procurement['invoiced_ex_vat'] ?? NULL);
    $purchasePaid = $this->number($procurement['paid_inc_vat'] ?? NULL);
    $openReceivedInvoicesExVat = $committed !== NULL && $purchaseInvoiced !== NULL
      ? max(0.0, $purchaseInvoiced)
      : NULL;
    $committedNotInvoicedExVat = $committed !== NULL && $purchaseInvoiced !== NULL
      ? max(0.0, $committed - $purchaseInvoiced)
      : NULL;

    $cards = [
      $this->card('Planning', $this->countProjectNodes('brebo_planning_activity', $projectId), 'activiteiten', 'brebo_office_core.project_planning', ['node' => $projectId], 'groen'),
      $this->card('Inzet', $activePeople, 'nu actief', 'brebo_inzet.live_workforce', ['node' => $projectId], $inzetStatus),
      $this->card('Klokafwijkingen', $redClock + $orangeClock, 'aandachtspunten', 'brebo_inzet.project_clock_deviations', ['node' => $projectId], $inzetStatus),
      $this->card('Werkpakketten', $this->countProjectNodes('brebo_work_package', $projectId), 'werkpakketten', 'brebo_office_core.work_packages', [], 'groen'),
      $this->card('Inkoop', $this->countProjectNodes('brebo_rfq', $projectId), 'prijsaanvragen', 'brebo_office_core.rfqs', [], 'groen'),
      $this->card('Risico’s', $this->countProjectNodes('brebo_risk', $projectId), 'geregistreerd', 'brebo_office_core.risks', [], 'groen'),
      $this->card('Acties', $this->countProjectNodes('brebo_action', $projectId), 'projectacties', 'brebo_office_core.actions', [], 'groen'),
      $this->card('Financiën', NULL, 'projectfinanciën openen', 'brebo_finance.project_finance_page', ['project_nid' => $projectId], $financeStatus),
    ];

    return [
      '#attached' => ['library' => ['brebo_project_cockpit/cockpit']],
      'hero' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-project-cockpit__hero']],
        'status' => ['#markup' => $this->statusMarkup('Projectstatus', $projectStatus)],
        'finance' => ['#markup' => $this->statusMarkup('Financieel', $financeStatus)],
        'cash' => ['#markup' => $this->statusMarkup('Cashflow', $this->cashStatus($cashCommitted))],
      ],
      'attention' => [
        '#type' => 'details',
        '#title' => $this->t('Wat vraagt vandaag aandacht?'),
        '#open' => TRUE,
        'items' => ['#theme' => 'item_list', '#items' => $attention],
      ],
      'money' => [
        '#type' => 'table',
        '#caption' => $this->t('Geldstraat project'),
        '#header' => [$this->t('Positie'), $this->t('Bedrag'), $this->t('Basis')],
        '#rows' => [
          [$this->t('Werkbegroting'), $this->money($forecast['current_budget_ex_vat'] ?? NULL), $this->t('excl. btw')],
          [$this->t('Reeds verplicht'), $this->money($procurement['committed_ex_vat'] ?? NULL), $this->t('excl. btw')],
          [$this->t('Facturen ontvangen'), $this->money($openReceivedInvoicesExVat), $this->t('excl. btw; ontvangen/ingeboekt')],
          [$this->t('Verplicht, nog niet gefactureerd'), $this->money($committedNotInvoicedExVat), $this->t('excl. btw')],
          [$this->t('Reeds betaald'), $this->money($purchasePaid), $this->t('incl. btw; cash')],
          [$this->t('Nog te verwachten kosten'), $this->money($forecast['forecast_remaining_cost_ex_vat'] ?? NULL), $this->t('excl. btw')],
          [$this->t('Prognose eindkosten'), $this->money($forecast['forecast_end_cost_ex_vat'] ?? NULL), $this->t('excl. btw')],
          [$this->t('Prognose resultaat'), $this->money($forecast['forecast_result_ex_vat'] ?? NULL), $this->t('excl. btw')],
          [$this->t('Prognose marge'), $this->percent($forecast['forecast_margin_pct'] ?? NULL), $this->t('op actuele omzet')],
        ],
      ],
      'revenue' => [
        '#type' => 'table',
        '#caption' => $this->t('Opbrengsten en ontvangst'),
        '#header' => [$this->t('Positie'), $this->t('Bedrag'), $this->t('Basis')],
        '#rows' => [
          [$this->t('Actuele omzet'), $this->money($forecast['current_revenue_ex_vat'] ?? NULL), $this->t('excl. btw')],
          [$this->t('Termijnen gepland'), $this->money($billing['planned_ex_vat'] ?? NULL), $this->t('excl. btw')],
          [$this->t('Gereed om te factureren'), $this->money($billing['billable_not_invoiced_ex_vat'] ?? NULL), $this->t('excl. btw')],
          [$this->t('Gefactureerd'), $this->money($billing['invoiced_inc_vat'] ?? NULL), $this->t('incl. btw')],
          [$this->t('Ontvangen'), $this->money($billing['paid_inc_vat'] ?? NULL), $this->t('incl. btw; cash')],
        ],
      ],
      'quick_actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'planning' => $this->linkButton('Planning', 'brebo_office_core.project_planning', ['node' => $projectId]),
        'clock' => $this->linkButton('Klokken', 'brebo_inzet.mobile_clock', ['node' => $projectId]),
        'workforce' => $this->linkButton('Nu aan het werk', 'brebo_inzet.live_workforce', ['node' => $projectId]),
        'finance' => $this->linkButton('Financiën', 'brebo_finance.project_finance_page', ['project_nid' => $projectId]),
        'edit' => $this->linkButton('Project bewerken', 'entity.node.edit_form', ['node' => $projectId]),
      ],
      'steering' => [
        '#type' => 'table',
        '#caption' => $this->t('Stuurgebieden'),
        '#header' => [$this->t('Status'), $this->t('Onderdeel'), $this->t('Aantal'), $this->t('Betekenis'), $this->t('Openen')],
        '#rows' => array_map(fn (array $card): array => [
          $this->statusLabel($card['status']),
          ['data' => ['#markup' => '<strong>' . $card['title'] . '</strong>']],
          $card['value'] === NULL ? '—' : (string) $card['value'],
          $card['subtitle'],
          ['data' => $card['link']],
        ], $cards),
      ],
      '#cache' => ['contexts' => ['user.permissions'], 'tags' => ['node:' . $projectId, 'node_list'], 'max-age' => 0],
    ];
  }

  private function financeStatus(array $finance, array $forecast, array $cashCommitted): string {
    if ($this->decimalNegative($forecast['forecast_result_ex_vat'] ?? NULL) || !empty($cashCommitted['first_regular_shortfall_date'])) {
      return 'rood';
    }
    $billing = is_array($finance['billing_position'] ?? NULL) ? $finance['billing_position'] : [];
    $workflow = is_array($finance['workflow'] ?? NULL) ? $finance['workflow'] : [];
    if (!empty($finance['forecast_is_stale']) || (int) ($billing['overdue_count'] ?? 0) > 0 || array_sum(array_map('intval', $workflow)) > 0) {
      return 'oranje';
    }
    return 'groen';
  }

  private function cashStatus(array $cash): string {
    if ($cash === []) {
      return 'grijs';
    }
    return !empty($cash['first_regular_shortfall_date']) || !empty($cash['first_g_account_shortfall_date']) ? 'rood' : 'groen';
  }

  private function worstStatus(array $statuses): string {
    $rank = ['grijs' => 0, 'groen' => 1, 'oranje' => 2, 'rood' => 3];
    $worst = 'grijs';
    foreach ($statuses as $status) {
      if (($rank[$status] ?? 0) > ($rank[$worst] ?? 0)) {
        $worst = $status;
      }
    }
    return $worst;
  }

  private function statusMarkup(string $label, string $status): string {
    return '<div class="brebo-project-cockpit__status brebo-project-cockpit__status--' . $status . '"><span>' . $label . '</span><strong>' . mb_strtoupper($status) . '</strong></div>';
  }

  private function statusLabel(string $status): string {
    return match ($status) {
      'rood' => '🔴 Rood',
      'oranje' => '🟠 Oranje',
      'groen' => '🟢 Groen',
      default => '⚪ Grijs',
    };
  }

  /** @return array{title:string,value:?int,subtitle:string,link:array,status:string} */
  private function card(string $title, ?int $value, string $subtitle, string $route, array $parameters, string $status): array {
    return ['title' => $title, 'value' => $value, 'subtitle' => $subtitle, 'status' => $status, 'link' => Link::fromTextAndUrl($this->t('Openen'), Url::fromRoute($route, $parameters))->toRenderable()];
  }

  private function linkButton(string $label, string $route, array $parameters = []): array {
    return ['#type' => 'link', '#title' => $this->t($label), '#url' => Url::fromRoute($route, $parameters), '#attributes' => ['class' => ['button']]];
  }

  private function countProjectNodes(string $bundle, int $projectId, array $conditions = []): int {
    $nodeType = $this->entityTypeManager()->getStorage('node_type')->load($bundle);
    if ($nodeType === NULL) {
      return 0;
    }
    $fields = $this->entityFieldManager()->getFieldDefinitions('node', $bundle);
    if (!isset($fields['field_brebo_project_ref'])) {
      return 0;
    }
    $query = $this->entityTypeManager()->getStorage('node')->getQuery()->accessCheck(TRUE)->condition('type', $bundle)->condition('field_brebo_project_ref', $projectId)->count();
    foreach ($conditions as $field => $value) {
      if (isset($fields[$field])) {
        $query->condition($field, $value);
      }
    }
    return (int) $query->execute();
  }

  private function countClockSeverity(int $projectId, string $severity): int {
    return $this->countProjectNodes('brebo_clock_registration', $projectId, ['field_brebo_clock_severity' => $severity]);
  }

  private function money(mixed $value): string {
    $number = $this->number($value);
    return $number === NULL ? '—' : '€ ' . number_format($number, 2, ',', '.');
  }

  private function percent(mixed $value): string {
    $number = $this->number($value);
    return $number === NULL ? '—' : number_format($number, 1, ',', '.') . '%';
  }

  private function number(mixed $value): ?float {
    return is_numeric($value) ? (float) $value : NULL;
  }

  private function decimalNegative(mixed $value): bool {
    return is_numeric($value) && (float) $value < 0;
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
  }

}
