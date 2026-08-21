<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Controller;

use Drupal\brebo_finance\Service\FinancialCockpitBuilder;
use Drupal\brebo_project_cockpit\Service\ProjectMilestoneBuilder;
use Drupal\brebo_project_cockpit\Service\ProjectProgressBuilder;
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
    private readonly ProjectProgressBuilder $projectProgressBuilder,
    private readonly ProjectMilestoneBuilder $projectMilestoneBuilder,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_finance.financial_cockpit_builder'),
      $container->get('brebo_project_cockpit.project_status_aggregator'),
      $container->get('brebo_project_cockpit.project_progress_builder'),
      $container->get('brebo_project_cockpit.project_milestone_builder'),
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
    $progress = $this->projectProgressBuilder->build($projectId);
    $milestones = $this->projectMilestoneBuilder->build($projectId);

    $forecast = is_array($finance['forecast'] ?? NULL) ? $finance['forecast'] : [];
    $procurement = is_array($finance['procurement_pipeline'] ?? NULL) ? $finance['procurement_pipeline'] : [];
    $billing = is_array($finance['billing_position'] ?? NULL) ? $finance['billing_position'] : [];
    $cashCommitted = is_array($finance['cash_forecast']['committed'] ?? NULL) ? $finance['cash_forecast']['committed'] : [];
    $domains = is_array($operational['domains'] ?? NULL) ? $operational['domains'] : [];
    $nextMilestone = is_array($milestones['next_milestone'] ?? NULL) ? $milestones['next_milestone'] : [];

    $financeStatus = $this->financeStatus($finance, $forecast, $cashCommitted);
    $cashStatus = $this->cashStatus($cashCommitted);
    $planningStatus = (string) ($progress['status'] ?? ($domains['planning']['status'] ?? 'grijs'));
    $projectStatus = $this->worstStatus([(string) ($operational['status'] ?? 'grijs'), $planningStatus, $financeStatus, $cashStatus]);

    $attention = [];
    foreach (($operational['attention'] ?? []) as $item) {
      $attention[] = $this->t('@area: @message', ['@area' => (string) $item['label'], '@message' => (string) $item['message']]);
    }
    if ((int) ($progress['blocked_count'] ?? 0) > 0) {
      $attention[] = $this->t('Planning: @count activiteit(en) zijn geblokkeerd.', ['@count' => (int) $progress['blocked_count']]);
    }
    if ((int) ($progress['late_count'] ?? 0) > 0) {
      $attention[] = $this->t('Planning: @count activiteit(en) zijn voorbij de geplande einddatum.', ['@count' => (int) $progress['late_count']]);
    }
    if (is_numeric($progress['progress_vs_time_pct'] ?? NULL) && (float) $progress['progress_vs_time_pct'] < -3.0) {
      $attention[] = $this->t('Planning: fysieke voortgang loopt @pct procentpunt achter op het verstreken projecttijdpad.', ['@pct' => number_format(abs((float) $progress['progress_vs_time_pct']), 1, ',', '.')]);
    }
    if (!empty($finance['forecast_is_stale'])) {
      $attention[] = $this->t('Financieel: de prognose ontbreekt of is ouder dan 30 dagen.');
    }
    if ($this->decimalNegative($forecast['forecast_result_ex_vat'] ?? NULL)) {
      $attention[] = $this->t('Financieel: de actuele prognose toont een negatief projectresultaat.');
    }
    if (!empty($cashCommitted['first_regular_shortfall_date'])) {
      $attention[] = $this->t('Cashflow: verwacht tekort vanaf @date.', ['@date' => $cashCommitted['first_regular_shortfall_date']]);
    }
    if ((int) ($billing['overdue_count'] ?? 0) > 0) {
      $attention[] = $this->t('Debiteuren: @count verkoopfactuur/facturen zijn vervallen.', ['@count' => (int) $billing['overdue_count']]);
    }
    if ($attention === []) {
      $attention[] = $this->t('Geen directe stuurafwijkingen uit de aangesloten bronnen.');
    }

    $committed = $this->number($procurement['committed_ex_vat'] ?? NULL);
    $purchaseInvoiced = $this->number($procurement['invoiced_ex_vat'] ?? NULL);
    $purchasePaid = $this->number($procurement['paid_inc_vat'] ?? NULL);
    $receivedInvoices = $purchaseInvoiced === NULL ? NULL : max(0.0, $purchaseInvoiced);
    $committedNotInvoiced = $committed !== NULL && $purchaseInvoiced !== NULL ? max(0.0, $committed - $purchaseInvoiced) : NULL;

    $domainRoutes = [
      'planning' => ['brebo_office_core.project_planning', ['node' => $projectId]],
      'inzet' => ['brebo_inzet.live_workforce', ['node' => $projectId]],
      'quality' => ['brebo_office_core.deviations', []],
      'risks' => ['brebo_project_cockpit.overview', ['node' => $projectId]],
      'actions' => ['brebo_project_cockpit.overview', ['node' => $projectId]],
      'procurement' => ['brebo_project_cockpit.overview', ['node' => $projectId]],
    ];
    $cards = [];
    foreach (($domains ?? []) as $key => $domain) {
      [$route, $parameters] = $domainRoutes[$key] ?? ['brebo_project_cockpit.overview', ['node' => $projectId]];
      $status = $key === 'planning' ? $planningStatus : (string) $domain['status'];
      $subtitle = $key === 'planning' && is_numeric($progress['actual_progress_pct'] ?? NULL)
        ? sprintf('%s%% gereed; %s%% tijd verstreken', number_format((float) $progress['actual_progress_pct'], 1, ',', '.'), $this->percentNumber($progress['time_elapsed_pct'] ?? NULL))
        : (string) $domain['message'];
      $cards[] = $this->card((string) $domain['label'], (int) $domain['total'], $subtitle, $route, $parameters, $status);
    }
    $cards[] = $this->card('Financiën', NULL, 'Resultaat, verplichtingen, facturen en prognose', 'brebo_finance.project_finance_page', ['project_nid' => $projectId], $financeStatus);
    $cards[] = $this->card('Cashflow', NULL, 'Betaald, ontvangen en 13-weeks liquiditeitsbeeld', 'brebo_finance.project_finance_page', ['project_nid' => $projectId], $cashStatus);

    $client = $this->entityOrValue($node, 'field_brebo_client_org_ref');
    if ($client === '—') {
      $client = $this->fieldValue($node, 'field_brebo_client');
    }
    $buildings = [];
    if ($node->hasField('field_brebo_building_refs')) {
      foreach ($node->get('field_brebo_building_refs')->referencedEntities() as $building) {
        $buildings[] = (string) $building->label();
      }
    }
    $projectLeader = '—';
    foreach (['field_brebo_project_manager', 'field_brebo_project_leader', 'field_brebo_projectleider'] as $field) {
      $candidate = $this->entityOrValue($node, $field);
      if ($candidate !== '—') {
        $projectLeader = $candidate;
        break;
      }
    }
    $phase = (string) ($milestones['current_phase'] ?? '');
    if ($phase === '') {
      $phase = $this->fieldValue($node, 'field_brebo_status');
    }
    $nextLabel = $nextMilestone !== [] ? (string) ($nextMilestone['label'] ?? '—') : '—';
    if ($nextMilestone !== [] && !empty($nextMilestone['due'])) {
      $nextLabel .= ' · ' . (string) $nextMilestone['due'];
    }

    $hero = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-cockpit__hero']],
      'project' => $this->richCard('Project', $projectStatus, $this->fieldValue($node, 'field_brebo_project_code'), [
        'Opdrachtgever: ' . $client,
        'Locatie: ' . ($buildings !== [] ? implode(' · ', $buildings) : '—'),
        'Projectleider: ' . $projectLeader,
        'Fase: ' . $phase,
        'Volgende mijlpaal: ' . $nextLabel,
      ]),
      'planning' => $this->richCard('Planning', $planningStatus, $this->percent($progress['actual_progress_pct'] ?? NULL) . ' gereed', [
        $this->percent($progress['time_elapsed_pct'] ?? NULL) . ' tijd verstreken',
        $this->signedPercentPoint($progress['progress_vs_time_pct'] ?? NULL) . ' t.o.v. tijdpad',
        (int) ($progress['late_count'] ?? 0) . ' te laat · ' . (int) ($progress['blocked_count'] ?? 0) . ' geblokkeerd',
        'Volgende mijlpaal: ' . ($nextMilestone !== [] ? (string) ($nextMilestone['label'] ?? '—') : '—'),
      ]),
      'finance' => $this->richCard('Geld', $financeStatus, $this->money($forecast['forecast_result_ex_vat'] ?? NULL) . ' resultaat', [
        'Werkbegroting: ' . $this->money($forecast['current_budget_ex_vat'] ?? NULL),
        'Verplicht: ' . $this->money($procurement['committed_ex_vat'] ?? NULL),
        'Prognose eindkosten: ' . $this->money($forecast['forecast_end_cost_ex_vat'] ?? NULL),
        'Marge: ' . $this->percent($forecast['forecast_margin_pct'] ?? NULL),
      ]),
      'cash' => $this->richCard('Cash', $cashStatus, $this->money($billing['paid_inc_vat'] ?? NULL) . ' ontvangen', [
        'Betaald inkoop: ' . $this->money($procurement['paid_inc_vat'] ?? NULL),
        'Gefactureerd verkoop: ' . $this->money($billing['invoiced_inc_vat'] ?? NULL),
        'Vervallen: ' . (int) ($billing['overdue_count'] ?? 0) . ' factuur/facturen',
        'Eerste cashtekort: ' . (!empty($cashCommitted['first_regular_shortfall_date']) ? (string) $cashCommitted['first_regular_shortfall_date'] : 'geen voorzien'),
      ]),
      'inzet' => $this->domainRichCard('Inzet', $domains['inzet'] ?? [], 'personen/signalen'),
      'quality' => $this->domainRichCard('Kwaliteit', $domains['quality'] ?? [], 'controles/afwijkingen'),
      'risks' => $this->domainRichCard('Risico', $domains['risks'] ?? [], "actieve risico's"),
    ];

    $canvas = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-cockpit__canvas']],
      'hero' => $hero,
      'attention' => ['#type' => 'details', '#title' => $this->t('Wat vraagt vandaag aandacht?'), '#open' => TRUE, 'items' => ['#theme' => 'item_list', '#items' => $attention]],
      'progress' => [
        '#type' => 'table',
        '#caption' => $this->t('Projectvoortgang versus tijd en geld'),
        '#header' => [$this->t('Indicator'), $this->t('Waarde'), $this->t('Betekenis')],
        '#rows' => [
          [$this->t('Uitgevoerde voortgang'), $this->percent($progress['actual_progress_pct'] ?? NULL), $this->t('Duurgewogen voortgang van uitvoeringsactiviteiten')],
          [$this->t('Projecttijd verstreken'), $this->percent($progress['time_elapsed_pct'] ?? NULL), $this->t('Vroegste geplande start tot laatste baseline-einddatum')],
          [$this->t('Voortgang t.o.v. tijd'), $this->signedPercentPoint($progress['progress_vs_time_pct'] ?? NULL), $this->t('Positief = voor op tijdpad; negatief = achter')],
          [$this->t('Geplande periode'), $this->dateRange($progress['planned_start'] ?? NULL, $progress['planned_end'] ?? NULL), $this->t('@count uitvoeringsactiviteiten', ['@count' => (int) ($progress['activity_count'] ?? 0)])],
          [$this->t('Kosten gerealiseerd'), $this->money($procurement['verified_performance_ex_vat'] ?? NULL), $this->t('excl. btw; geverifieerde prestatie')],
          [$this->t('Kosten gefactureerd'), $this->money($procurement['invoiced_ex_vat'] ?? NULL), $this->t('excl. btw; ontvangen inkoopfacturen')],
          [$this->t('Prognose eindmarge'), $this->percent($forecast['forecast_margin_pct'] ?? NULL), $this->t('op actuele omzet')],
        ],
        '#description' => (string) ($progress['basis'] ?? ''),
      ],
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
      'steering' => [
        '#type' => 'table', '#caption' => $this->t('Stuurgebieden'), '#header' => [$this->t('Status'), $this->t('Onderdeel'), $this->t('Aantal'), $this->t('Betekenis'), $this->t('Openen')],
        '#rows' => array_map(fn(array $card): array => [$this->statusLabel($card['status']), ['data' => ['#markup' => '<strong>' . $card['title'] . '</strong>']], $card['value'] === NULL ? '—' : (string) $card['value'], $card['subtitle'], ['data' => $card['link']]], $cards),
      ],
    ];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-cockpit__layout']],
      '#attached' => ['library' => ['brebo_project_cockpit/cockpit']],
      'menu' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions'], 'aria-label' => $this->t('Projectnavigatie')],
        'planning' => $this->linkButton('Planning', 'brebo_office_core.project_planning', ['node' => $projectId]),
        'clock' => $this->linkButton('Klokken', 'brebo_inzet.mobile_clock', ['node' => $projectId]),
        'workforce' => $this->linkButton('Nu aan het werk', 'brebo_inzet.live_workforce', ['node' => $projectId]),
        'finance' => $this->linkButton('Financiën', 'brebo_finance.project_finance_page', ['project_nid' => $projectId]),
        'edit' => $this->linkButton('Project bewerken', 'entity.node.edit_form', ['node' => $projectId]),
      ],
      'canvas' => $canvas,
      '#cache' => ['contexts' => ['user.permissions'], 'tags' => ['node:' . $projectId, 'node_list'], 'max-age' => 0],
    ];
  }

  private function richCard(string $label, string $status, string $headline, array $lines): array {
    $details = ['#type' => 'container', '#attributes' => ['class' => ['brebo-project-cockpit__card-details']]];
    foreach ($lines as $index => $line) {
      $details['line_' . $index] = ['#type' => 'html_tag', '#tag' => 'div', '#value' => (string) $line, '#attributes' => ['class' => ['brebo-project-cockpit__card-line']]];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-cockpit__status', 'brebo-project-cockpit__status--' . $status, 'brebo-project-cockpit__status--rich']],
      'label' => ['#type' => 'html_tag', '#tag' => 'span', '#value' => $label, '#attributes' => ['class' => ['brebo-project-cockpit__card-label']]],
      'status' => ['#type' => 'html_tag', '#tag' => 'strong', '#value' => mb_strtoupper($status), '#attributes' => ['class' => ['brebo-project-cockpit__card-status']]],
      'headline' => ['#type' => 'html_tag', '#tag' => 'div', '#value' => $headline, '#attributes' => ['class' => ['brebo-project-cockpit__card-headline']]],
      'details' => $details,
    ];
  }

  private function domainRichCard(string $label, mixed $domain, string $unit): array {
    $domain = is_array($domain) ? $domain : [];
    $total = (int) ($domain['total'] ?? 0);
    $message = trim((string) ($domain['message'] ?? ''));
    return $this->richCard($label, (string) ($domain['status'] ?? 'grijs'), $total . ' ' . $unit, [$message !== '' ? $message : 'Nog geen aangesloten stuurinformatie.']);
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
  private function statusLabel(string $status): string { return match($status){'rood'=>'🔴 Rood','oranje'=>'🟠 Oranje','groen'=>'🟢 Groen',default=>'⚪ Grijs'}; }
  private function card(string $title,?int $value,string $subtitle,string $route,array $parameters,string $status): array { return ['title'=>$title,'value'=>$value,'subtitle'=>$subtitle,'status'=>$status,'link'=>Link::fromTextAndUrl($this->t('Openen'),Url::fromRoute($route,$parameters))->toRenderable()]; }
  private function linkButton(string $label,string $route,array $parameters=[]): array { return ['#type'=>'link','#title'=>$this->t($label),'#url'=>Url::fromRoute($route,$parameters),'#attributes'=>['class'=>['button']]]; }
  private function money(mixed $value): string { $n=$this->number($value); return $n===NULL?'—':'€ '.number_format($n,2,',','.'); }
  private function percent(mixed $value): string { $n=$this->number($value); return $n===NULL?'—':number_format($n,1,',','.').' %'; }
  private function percentNumber(mixed $value): string { $n=$this->number($value); return $n===NULL?'—':number_format($n,1,',','.'); }
  private function signedPercentPoint(mixed $value): string { $n=$this->number($value); return $n===NULL?'—':($n>0?'+':'').number_format($n,1,',','.').' pp'; }
  private function dateRange(mixed $start,mixed $end): string { return is_string($start)&&$start!==''&&is_string($end)&&$end!==''?$start.' → '.$end:'—'; }
  private function number(mixed $value): ?float { return is_numeric($value)?(float)$value:NULL; }
  private function decimalNegative(mixed $value): bool { $n=$this->number($value); return $n!==NULL && $n<0; }
  private function fieldValue(NodeInterface $node, string $field): string { if(!$node->hasField($field)||$node->get($field)->isEmpty()) return '—'; $value=trim((string)($node->get($field)->value??'')); return $value!==''?$value:'—'; }
  private function entityOrValue(NodeInterface $node, string $field): string { if(!$node->hasField($field)||$node->get($field)->isEmpty()) return '—'; $entity=$node->get($field)->entity; return $entity?(string)$entity->label():$this->fieldValue($node,$field); }
  private function assertProject(NodeInterface $node): void { if($node->bundle()!=='brebo_project') throw new NotFoundHttpException('BREBO project does not exist.'); }
}
