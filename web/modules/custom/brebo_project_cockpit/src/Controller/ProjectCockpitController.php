<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Controller;

use Drupal\brebo_finance\Service\FinancialCockpitBuilder;
use Drupal\brebo_project_cockpit\Service\ProjectStatusAggregator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Central steering cockpit for one BREBO project. */
final class ProjectCockpitController extends ControllerBase {

  public function __construct(
    private readonly FinancialCockpitBuilder $financialCockpitBuilder,
    private readonly ProjectStatusAggregator $projectStatusAggregator,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_finance.financial_cockpit_builder'),
      $container->get('brebo_project_cockpit.project_status_aggregator'),
    );
  }

  public function title(NodeInterface $node): string {
    $this->assertProject($node);
    return (string) $node->label();
  }

  public function overview(NodeInterface $node): array {
    $this->assertProject($node);
    $projectId = (int) $node->id();
    $finance = $this->financialCockpitBuilder->build($projectId);
    $operational = $this->projectStatusAggregator->build($projectId);
    $forecast = is_array($finance['forecast'] ?? NULL) ? $finance['forecast'] : [];
    $procurement = is_array($finance['procurement_pipeline'] ?? NULL) ? $finance['procurement_pipeline'] : [];
    $billing = is_array($finance['billing_position'] ?? NULL) ? $finance['billing_position'] : [];
    $cashCommitted = is_array($finance['cash_forecast']['committed'] ?? NULL) ? $finance['cash_forecast']['committed'] : [];
    $financeStatus = $this->financeStatus($finance, $forecast, $cashCommitted);
    $cashStatus = $this->cashStatus($cashCommitted);
    $projectStatus = $this->worstStatus([(string) ($operational['status'] ?? 'grijs'), $financeStatus, $cashStatus]);

    $attention = [];
    foreach (($operational['attention'] ?? []) as $item) {
      $attention[] = $this->t('@area: @message', ['@area' => (string) $item['label'], '@message' => (string) $item['message']]);
    }
    if (!empty($finance['forecast_is_stale'])) $attention[] = $this->t('Financieel: de prognose ontbreekt of is ouder dan 30 dagen.');
    if ($this->decimalNegative($forecast['forecast_result_ex_vat'] ?? NULL)) $attention[] = $this->t('Financieel: de actuele prognose toont een negatief projectresultaat.');
    if (!empty($cashCommitted['first_regular_shortfall_date'])) $attention[] = $this->t('Cashflow: verwacht tekort vanaf @date.', ['@date' => $cashCommitted['first_regular_shortfall_date']]);
    if ((int) ($billing['overdue_count'] ?? 0) > 0) $attention[] = $this->t('Debiteuren: @count verkoopfactuur/facturen zijn vervallen.', ['@count' => (int) $billing['overdue_count']]);
    if ($attention === []) $attention[] = $this->t('Geen directe stuurafwijkingen uit de aangesloten bronnen.');

    $committed = $this->number($procurement['committed_ex_vat'] ?? NULL);
    $purchaseInvoiced = $this->number($procurement['invoiced_ex_vat'] ?? NULL);
    $purchasePaid = $this->number($procurement['paid_inc_vat'] ?? NULL);
    $receivedInvoices = $purchaseInvoiced === NULL ? NULL : max(0.0, $purchaseInvoiced);
    $committedNotInvoiced = $committed !== NULL && $purchaseInvoiced !== NULL ? max(0.0, $committed - $purchaseInvoiced) : NULL;

    $domainRoutes = [
      'planning' => ['brebo_office_core.project_planning', ['node' => $projectId]],
      'inzet' => ['brebo_inzet.live_workforce', ['node' => $projectId]],
      'quality' => ['brebo_office_core.deviations', []],
      'risks' => ['brebo_office_core.risks', []],
      'actions' => ['brebo_office_core.actions', []],
      'procurement' => ['brebo_office_core.rfqs', []],
    ];
    $cards = [];
    foreach (($operational['domains'] ?? []) as $key => $domain) {
      [$route, $parameters] = $domainRoutes[$key] ?? ['brebo_project_cockpit.overview', ['node' => $projectId]];
      $cards[] = $this->card((string) $domain['label'], (int) $domain['total'], (string) $domain['message'], $route, $parameters, (string) $domain['status']);
    }
    $cards[] = $this->card('Financiën', NULL, 'Resultaat, verplichtingen, facturen en prognose', 'brebo_finance.project_finance_page', ['project_nid' => $projectId], $financeStatus);
    $cards[] = $this->card('Cashflow', NULL, 'Betaald, ontvangen en 13-weeks liquiditeitsbeeld', 'brebo_finance.project_finance_page', ['project_nid' => $projectId], $cashStatus);

    $hero = [
      'project' => $projectStatus,
      'planning' => $operational['domains']['planning']['status'] ?? 'grijs',
      'finance' => $financeStatus,
      'cash' => $cashStatus,
      'inzet' => $operational['domains']['inzet']['status'] ?? 'grijs',
      'quality' => $operational['domains']['quality']['status'] ?? 'grijs',
      'risks' => $operational['domains']['risks']['status'] ?? 'grijs',
    ];

    return [
      '#attached' => ['library' => ['brebo_project_cockpit/cockpit']],
      'hero' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-project-cockpit__hero']],
        'project' => ['#markup' => $this->statusMarkup('Project', $hero['project'])],
        'planning' => ['#markup' => $this->statusMarkup('Planning', $hero['planning'])],
        'finance' => ['#markup' => $this->statusMarkup('Geld', $hero['finance'])],
        'cash' => ['#markup' => $this->statusMarkup('Cash', $hero['cash'])],
        'inzet' => ['#markup' => $this->statusMarkup('Inzet', $hero['inzet'])],
        'quality' => ['#markup' => $this->statusMarkup('Kwaliteit', $hero['quality'])],
        'risks' => ['#markup' => $this->statusMarkup('Risico', $hero['risks'])],
      ],
      'attention' => ['#type' => 'details', '#title' => $this->t('Wat vraagt vandaag aandacht?'), '#open' => TRUE, 'items' => ['#theme' => 'item_list', '#items' => $attention]],
      'money' => [
        '#type' => 'table', '#caption' => $this->t('Geldstraat project'), '#header' => [$this->t('Positie'), $this->t('Bedrag'), $this->t('Basis')],
        '#rows' => [
          [$this->t('Werkbegroting'), $this->money($forecast['current_budget_ex_vat'] ?? NULL), $this->t('excl. btw')],
          [$this->t('Reeds verplicht'), $this->money($procurement['committed_ex_vat'] ?? NULL), $this->t('excl. btw')],
          [$this->t('Facturen ontvangen'), $this->money($receivedInvoices), $this->t('excl. btw; ontvangen/ingeboekt')],
          [$this->t('Verplicht, nog niet gefactureerd'), $this->money($committedNotInvoiced), $this->t('excl. btw')],
          [$this->t('Reeds betaald'), $this->money($purchasePaid), $this->t('incl. btw; cash')],
          [$this->t('Nog te verwachten kosten'), $this->money($forecast['forecast_remaining_cost_ex_vat'] ?? NULL), $this->t('excl. btw')],
          [$this->t('Prognose eindkosten'), $this->money($forecast['forecast_end_cost_ex_vat'] ?? NULL), $this->t('excl. btw')],
          [$this->t('Prognose resultaat'), $this->money($forecast['forecast_result_ex_vat'] ?? NULL), $this->t('excl. btw')],
          [$this->t('Prognose marge'), $this->percent($forecast['forecast_margin_pct'] ?? NULL), $this->t('op actuele omzet')],
        ],
      ],
      'revenue' => [
        '#type' => 'table', '#caption' => $this->t('Opbrengsten en ontvangst'), '#header' => [$this->t('Positie'), $this->t('Bedrag'), $this->t('Basis')],
        '#rows' => [
          [$this->t('Actuele omzet'), $this->money($forecast['current_revenue_ex_vat'] ?? NULL), $this->t('excl. btw')],
          [$this->t('Termijnen gepland'), $this->money($billing['planned_ex_vat'] ?? NULL), $this->t('excl. btw')],
          [$this->t('Gereed om te factureren'), $this->money($billing['billable_not_invoiced_ex_vat'] ?? NULL), $this->t('excl. btw')],
          [$this->t('Gefactureerd'), $this->money($billing['invoiced_inc_vat'] ?? NULL), $this->t('incl. btw')],
          [$this->t('Ontvangen'), $this->money($billing['paid_inc_vat'] ?? NULL), $this->t('incl. btw; cash')],
        ],
      ],
      'quick_actions' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-list-actions']],
        'planning' => $this->linkButton('Planning', 'brebo_office_core.project_planning', ['node' => $projectId]),
        'clock' => $this->linkButton('Klokken', 'brebo_inzet.mobile_clock', ['node' => $projectId]),
        'workforce' => $this->linkButton('Nu aan het werk', 'brebo_inzet.live_workforce', ['node' => $projectId]),
        'finance' => $this->linkButton('Financiën', 'brebo_finance.project_finance_page', ['project_nid' => $projectId]),
        'edit' => $this->linkButton('Project bewerken', 'entity.node.edit_form', ['node' => $projectId]),
      ],
      'steering' => [
        '#type' => 'table', '#caption' => $this->t('Stuurgebieden'), '#header' => [$this->t('Status'), $this->t('Onderdeel'), $this->t('Aantal'), $this->t('Betekenis'), $this->t('Openen')],
        '#rows' => array_map(fn(array $card): array => [$this->statusLabel($card['status']), ['data' => ['#markup' => '<strong>' . $card['title'] . '</strong>']], $card['value'] === NULL ? '—' : (string) $card['value'], $card['subtitle'], ['data' => $card['link']]], $cards),
      ],
      '#cache' => ['contexts' => ['user.permissions'], 'tags' => ['node:' . $projectId, 'node_list'], 'max-age' => 0],
    ];
  }

  private function financeStatus(array $finance, array $forecast, array $cash): string {
    if ($this->decimalNegative($forecast['forecast_result_ex_vat'] ?? NULL)) return 'rood';
    $billing = is_array($finance['billing_position'] ?? NULL) ? $finance['billing_position'] : [];
    $workflow = is_array($finance['workflow'] ?? NULL) ? $finance['workflow'] : [];
    if (!empty($finance['forecast_is_stale']) || (int) ($billing['overdue_count'] ?? 0) > 0 || array_sum(array_map('intval', $workflow)) > 0) return 'oranje';
    return $forecast === [] ? 'grijs' : 'groen';
  }

  private function cashStatus(array $cash): string { return $cash === [] ? 'grijs' : ((!empty($cash['first_regular_shortfall_date']) || !empty($cash['first_g_account_shortfall_date'])) ? 'rood' : 'groen'); }
  private function worstStatus(array $statuses): string { $rank=['grijs'=>0,'groen'=>1,'oranje'=>2,'rood'=>3]; $worst='grijs'; foreach($statuses as $s) if(($rank[$s]??0)>($rank[$worst]??0)) $worst=$s; return $worst; }
  private function statusMarkup(string $label,string $status): string { return '<div class="brebo-project-cockpit__status brebo-project-cockpit__status--'.$status.'"><span>'.$label.'</span><strong>'.mb_strtoupper($status).'</strong></div>'; }
  private function statusLabel(string $status): string { return match($status){'rood'=>'🔴 Rood','oranje'=>'🟠 Oranje','groen'=>'🟢 Groen',default=>'⚪ Grijs'}; }
  private function card(string $title,?int $value,string $subtitle,string $route,array $parameters,string $status): array { return ['title'=>$title,'value'=>$value,'subtitle'=>$subtitle,'status'=>$status,'link'=>Link::fromTextAndUrl($this->t('Openen'),Url::fromRoute($route,$parameters))->toRenderable()]; }
  private function linkButton(string $label,string $route,array $parameters=[]): array { return ['#type'=>'link','#title'=>$this->t($label),'#url'=>Url::fromRoute($route,$parameters),'#attributes'=>['class'=>['button']]]; }
  private function money(mixed $value): string { $n=$this->number($value); return $n===NULL?'—':'€ '.number_format($n,2,',','.'); }
  private function percent(mixed $value): string { $n=$this->number($value); return $n===NULL?'—':number_format($n,1,',','.').' %'; }
  private function number(mixed $value): ?float { return is_numeric($value)?(float)$value:NULL; }
  private function decimalNegative(mixed $value): bool { $n=$this->number($value); return $n!==NULL && $n<0; }
  private function assertProject(NodeInterface $node): void { if($node->bundle()!=='brebo_project') throw new NotFoundHttpException('BREBO project does not exist.'); }
}
