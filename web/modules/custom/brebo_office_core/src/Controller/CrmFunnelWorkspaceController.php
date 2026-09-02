<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/** CRM workspace: management dashboard, list and Kanban. */
final class CrmFunnelWorkspaceController extends ControllerBase {

  private const STAGES = [
    'Marketing lead',
    'Lead',
    'Kans',
    'Afspraak',
    'Calculatie/offerte',
    'Onderhandeling',
    'Gewonnen',
    'Verloren',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $crmEntityTypeManager,
    private readonly ClassResolverInterface $classResolver,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('class_resolver'),
    );
  }

  public function overview(Request $request): array {
    $view = in_array((string) $request->query->get('view', 'dashboard'), ['dashboard', 'list', 'kanban'], TRUE)
      ? (string) $request->query->get('view', 'dashboard')
      : 'dashboard';

    if ($view !== 'dashboard') {
      /** @var \Drupal\brebo_office_core\Controller\CrmController $legacy */
      $legacy = $this->classResolver->getInstanceFromDefinition(CrmController::class);
      $request->query->set('view', $view);
      $build = $legacy->funnel($request);
      return [
        '#attached' => ['library' => ['brebo_office_core/crm-dashboard']],
        'workspace_switch' => $this->workspaceSwitch($request, $view),
        'content' => $build,
      ];
    }

    $storage = $this->crmEntityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_opportunity')
      ->sort('changed', 'DESC');

    $this->applyFilters($query, $request);
    $opportunities = $storage->loadMultiple($query->execute());

    $stageData = [];
    foreach (self::STAGES as $stage) {
      $stageData[$stage] = ['count' => 0, 'value' => 0.0, 'weighted' => 0.0];
    }

    $activeCount = 0;
    $pipelineValue = 0.0;
    $weightedValue = 0.0;
    $wonValue = 0.0;
    $lostCount = 0;
    $overdue = 0;
    $today = new \DateTimeImmutable('today');

    foreach ($opportunities as $opportunity) {
      if (!$opportunity instanceof NodeInterface) continue;
      $stage = trim((string) ($opportunity->get('field_brebo_opp_stage')->value ?? '')) ?: 'Geen fase';
      if (!isset($stageData[$stage])) $stageData[$stage] = ['count' => 0, 'value' => 0.0, 'weighted' => 0.0];
      $value = (float) ($opportunity->get('field_brebo_opp_value')->value ?? 0);
      $probability = max(0, min(100, (int) ($opportunity->get('field_brebo_opp_probability')->value ?? 0)));
      $weighted = $value * ($probability / 100);
      $active = (bool) ($opportunity->get('field_brebo_opp_active')->value ?? FALSE);
      $stageData[$stage]['count']++;
      $stageData[$stage]['value'] += $value;
      $stageData[$stage]['weighted'] += $weighted;

      if ($active) {
        $activeCount++;
        $pipelineValue += $value;
        $weightedValue += $weighted;
      }
      if ($stage === 'Gewonnen') $wonValue += $value;
      if ($stage === 'Verloren') $lostCount++;

      $nextActionDate = $opportunity->hasField('field_brebo_opp_next_date')
        ? trim((string) ($opportunity->get('field_brebo_opp_next_date')->value ?? ''))
        : '';
      if ($active && $nextActionDate !== '') {
        try {
          if (new \DateTimeImmutable($nextActionDate) < $today) $overdue++;
        }
        catch (\Throwable) {}
      }
    }

    $maxCount = max(1, ...array_map(static fn(array $item): int => $item['count'], $stageData));
    $maxValue = max(1.0, ...array_map(static fn(array $item): float => $item['value'], $stageData));
    $funnelRows = [];
    $pipelineRows = [];
    foreach ($stageData as $stage => $data) {
      $funnelRows[] = $this->funnelRow($request, $stage, $data['count'], $maxCount);
      $pipelineRows[] = $this->pipelineRow($request, $stage, $data['value'], $data['weighted'], $maxValue);
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-crm-dashboard']],
      '#attached' => ['library' => ['brebo_office_core/crm-dashboard']],
      'workspace_switch' => $this->workspaceSwitch($request, 'dashboard'),
      'kpis' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-crm-dashboard__kpis']],
        'active' => $this->kpi('Open kansen', (string) $activeCount, 'Actieve commerciële dossiers'),
        'pipeline' => $this->kpi('Pipeline', $this->money($pipelineValue), 'Ongewogen open omzet'),
        'weighted' => $this->kpi('Gewogen pipeline', $this->money($weightedValue), 'Op basis van scoringskans'),
        'won' => $this->kpi('Gewonnen omzet', $this->money($wonValue), 'Waarde in fase Gewonnen'),
        'overdue' => $this->kpi('Achterstallige opvolging', (string) $overdue, 'Open kansen met verlopen actiedatum'),
        'lost' => $this->kpi('Verloren kansen', (string) $lostCount, 'Kansen in fase Verloren'),
      ],
      'charts' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-crm-dashboard__charts']],
        'funnel' => [
          '#type' => 'container', '#attributes' => ['class' => ['brebo-crm-chart', 'brebo-crm-chart--funnel']],
          'heading' => ['#markup' => '<div class="brebo-crm-chart__heading"><h2>Conversietrechter</h2><p>Aantallen per commerciële fase. Klik op een fase om de lijst te openen.</p></div>'],
          'rows' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-crm-funnel']]] + $funnelRows,
        ],
        'pipeline' => [
          '#type' => 'container', '#attributes' => ['class' => ['brebo-crm-chart', 'brebo-crm-chart--pipeline']],
          'heading' => ['#markup' => '<div class="brebo-crm-chart__heading"><h2>Omzetpijplijn</h2><p>Verwachte en gewogen omzet per fase. Klik op een fase om door te sturen.</p></div>'],
          'legend' => ['#markup' => '<div class="brebo-crm-chart__legend"><span><i class="brebo-crm-chart__legend-total"></i> Verwachte omzet</span><span><i class="brebo-crm-chart__legend-weighted"></i> Gewogen omzet</span></div>'],
          'rows' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-crm-pipeline']]] + $pipelineRows,
        ],
      ],
      '#cache' => [
        'contexts' => ['user.permissions', 'url.query_args'],
        'tags' => ['node_list:brebo_opportunity'],
      ],
    ];
  }

  private function applyFilters(object $query, Request $request): void {
    $mine = (bool) $request->query->get('mine', FALSE);
    $ownerFilter = max(0, (int) $request->query->get('owner_filter', 0));
    $organizationFilter = max(0, (int) $request->query->get('organization_filter', 0));
    $sourceFilter = trim((string) $request->query->get('source_filter', ''));
    $channelFilter = trim((string) $request->query->get('channel_filter', ''));
    $stageFilter = trim((string) $request->query->get('stage_filter', ''));
    $statusFilter = in_array((string) $request->query->get('status', 'all'), ['all', 'open', 'closed'], TRUE) ? (string) $request->query->get('status', 'all') : 'all';
    if ($organizationFilter > 0) $query->condition('field_brebo_opp_org_ref.target_id', $organizationFilter);
    if ($sourceFilter !== '') $query->condition('field_brebo_opp_source', $sourceFilter);
    if ($channelFilter !== '') $query->condition('field_brebo_opp_channel', $channelFilter);
    if (in_array($stageFilter, self::STAGES, TRUE)) $query->condition('field_brebo_opp_stage', $stageFilter);
    if ($statusFilter === 'open') $query->condition('field_brebo_opp_active', 1);
    elseif ($statusFilter === 'closed') $query->condition('field_brebo_opp_active', 0);
    if ($mine) $query->condition('field_brebo_opp_owner.target_id', (int) $this->currentUser()->id());
    elseif ($ownerFilter > 0) $query->condition('field_brebo_opp_owner.target_id', $ownerFilter);
  }

  private function workspaceSwitch(Request $request, string $active): array {
    $baseQuery = $request->query->all();
    unset($baseQuery['view']);
    $links = [];
    foreach (['dashboard' => 'Dashboard', 'list' => 'Lijst', 'kanban' => 'Kanban'] as $view => $label) {
      $links[$view] = [
        '#type' => 'link', '#title' => $this->t($label),
        '#url' => Url::fromRoute('brebo_office_core.funnel', [], ['query' => $baseQuery + ['view' => $view]]),
        '#attributes' => ['class' => $active === $view ? ['is-active'] : []],
      ];
    }
    return ['#type' => 'container', '#attributes' => ['class' => ['brebo-crm-workspace-switch'], 'aria-label' => $this->t('CRM-weergave')]] + $links;
  }

  private function kpi(string $label, string $value, string $detail): array {
    return ['#markup' => '<section class="brebo-crm-kpi"><span class="brebo-crm-kpi__label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span><strong class="brebo-crm-kpi__value">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</strong><span class="brebo-crm-kpi__detail">' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</span></section>'];
  }

  private function funnelRow(Request $request, string $stage, int $count, int $maxCount): array {
    $width = max(22, (int) round(($count / $maxCount) * 100));
    $url = $this->stageUrl($request, $stage, 'list');
    $markup = '<a class="brebo-crm-funnel__row" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"><span class="brebo-crm-funnel__stage">' . htmlspecialchars($stage, ENT_QUOTES, 'UTF-8') . '</span><span class="brebo-crm-funnel__bar-wrap"><span class="brebo-crm-funnel__bar" style="width:' . $width . '%"><strong>' . $count . '</strong></span></span></a>';
    return ['#markup' => $markup];
  }

  private function pipelineRow(Request $request, string $stage, float $value, float $weighted, float $maxValue): array {
    $totalWidth = $value > 0 ? max(2, (int) round(($value / $maxValue) * 100)) : 0;
    $weightedWidth = $value > 0 ? max(0, min(100, (int) round(($weighted / $value) * 100))) : 0;
    $url = $this->stageUrl($request, $stage, 'kanban');
    $markup = '<a class="brebo-crm-pipeline__row" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"><span class="brebo-crm-pipeline__stage">' . htmlspecialchars($stage, ENT_QUOTES, 'UTF-8') . '</span><span class="brebo-crm-pipeline__bar-wrap"><span class="brebo-crm-pipeline__bar" style="width:' . $totalWidth . '%"><span class="brebo-crm-pipeline__weighted" style="width:' . $weightedWidth . '%"></span></span></span><span class="brebo-crm-pipeline__value"><strong>' . htmlspecialchars($this->money($value), ENT_QUOTES, 'UTF-8') . '</strong><small>' . htmlspecialchars($this->money($weighted), ENT_QUOTES, 'UTF-8') . ' gewogen</small></span></a>';
    return ['#markup' => $markup];
  }

  private function stageUrl(Request $request, string $stage, string $view): string {
    $query = $request->query->all();
    $query['stage_filter'] = $stage;
    $query['view'] = $view;
    return Url::fromRoute('brebo_office_core.funnel', [], ['query' => $query])->toString();
  }

  private function money(float $value): string {
    return '€ ' . number_format($value, 0, ',', '.');
  }

}
