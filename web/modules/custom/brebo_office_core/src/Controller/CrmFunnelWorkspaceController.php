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

  private const STAGES = ['Marketing lead', 'Lead', 'Kans', 'Afspraak', 'Calculatie/offerte', 'Onderhandeling', 'Gewonnen', 'Verloren'];
  private const FUNNEL_STAGES = ['Marketing lead', 'Lead', 'Kans', 'Afspraak', 'Calculatie/offerte', 'Onderhandeling', 'Gewonnen'];
  private const MARKUP_TAGS = ['a', 'div', 'form', 'header', 'h2', 'label', 'option', 'p', 'progress', 'section', 'select', 'small', 'span', 'strong'];
  private const PERIODS = [
    'today' => 'Vandaag', 'this_week' => 'Deze week', 'last_week' => 'Vorige week',
    'this_month' => 'Deze maand', 'last_month' => 'Vorige maand',
    'this_quarter' => 'Dit kwartaal', 'last_quarter' => 'Vorig kwartaal',
    'last_12_months' => 'Afgelopen 12 maanden', 'this_year' => 'Dit jaar', 'last_year' => 'Vorig jaar',
  ];

  public function __construct(private readonly EntityTypeManagerInterface $crmEntityTypeManager, private readonly ClassResolverInterface $classResolver) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'), $container->get('class_resolver'));
  }

  public function overview(Request $request): array {
    $view = in_array((string) $request->query->get('view', 'dashboard'), ['dashboard', 'list', 'kanban'], TRUE) ? (string) $request->query->get('view', 'dashboard') : 'dashboard';
    if ($view !== 'dashboard') {
      /** @var \Drupal\brebo_office_core\Controller\CrmController $legacy */
      $legacy = $this->classResolver->getInstanceFromDefinition(CrmController::class);
      $request->query->set('view', $view);
      return ['#attached' => ['library' => ['brebo_office_core/crm-dashboard']], 'workspace_switch' => $this->workspaceSwitch($request, $view), 'content' => $legacy->funnel($request)];
    }

    $storage = $this->crmEntityTypeManager->getStorage('node');
    $query = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'brebo_opportunity')->sort('changed', 'DESC');
    $this->applyFilters($query, $request);
    $opportunities = $storage->loadMultiple($query->execute());
    $stageData = [];
    foreach (self::STAGES as $stage) $stageData[$stage] = ['count' => 0, 'value' => 0.0, 'weighted' => 0.0];

    $timezoneName = (string) ($this->config('system.date')->get('timezone.default') ?: 'UTC');
    try { $siteTimezone = new \DateTimeZone($timezoneName); } catch (\Throwable) { $siteTimezone = new \DateTimeZone('UTC'); }
    $today = new \DateTimeImmutable('today', $siteTimezone);
    $periodKey = (string) $request->query->get('analytics_period', 'last_12_months');
    if (!isset(self::PERIODS[$periodKey])) $periodKey = 'last_12_months';
    $period = $this->periodDefinition($periodKey, $today);
    $inflow = $this->emptyBuckets($period['start'], $period['end'], $period['bucket']);
    $forecast = $this->emptyBuckets($period['start'], $period['end'], $period['bucket'], TRUE);

    $activeCount = 0; $pipelineValue = 0.0; $weightedValue = 0.0; $wonValue = 0.0; $lostCount = 0; $overdue = 0;
    foreach ($opportunities as $opportunity) {
      if (!$opportunity instanceof NodeInterface) continue;
      $stage = trim((string) ($opportunity->get('field_brebo_opp_stage')->value ?? '')) ?: 'Geen fase';
      if (!isset($stageData[$stage])) $stageData[$stage] = ['count' => 0, 'value' => 0.0, 'weighted' => 0.0];
      $value = (float) ($opportunity->get('field_brebo_opp_value')->value ?? 0);
      $probability = max(0, min(100, (int) ($opportunity->get('field_brebo_opp_probability')->value ?? 0)));
      $weighted = $value * ($probability / 100);
      $active = (bool) ($opportunity->get('field_brebo_opp_active')->value ?? FALSE);
      $stageData[$stage]['count']++; $stageData[$stage]['value'] += $value; $stageData[$stage]['weighted'] += $weighted;
      if ($active) { $activeCount++; $pipelineValue += $value; $weightedValue += $weighted; }
      if ($stage === 'Gewonnen') $wonValue += $value;
      if ($stage === 'Verloren') $lostCount++;

      $nextActionDate = $opportunity->hasField('field_brebo_opp_next_date') ? trim((string) ($opportunity->get('field_brebo_opp_next_date')->value ?? '')) : '';
      if ($active && $nextActionDate !== '') { try { if (new \DateTimeImmutable($nextActionDate, $siteTimezone) < $today) $overdue++; } catch (\Throwable) {} }

      if ($active && $opportunity->hasField('field_brebo_opp_close_date')) {
        $closeDate = trim((string) ($opportunity->get('field_brebo_opp_close_date')->value ?? ''));
        if ($closeDate !== '') {
          try {
            $close = new \DateTimeImmutable($closeDate, $siteTimezone);
            if ($close >= $period['start'] && $close <= $period['end']) {
              $key = $this->bucketKey($close, $period['bucket']);
              if (isset($forecast[$key])) { $forecast[$key]['count']++; $forecast[$key]['value'] += $value; $forecast[$key]['weighted'] += $weighted; }
            }
          } catch (\Throwable) {}
        }
      }

      $created = (new \DateTimeImmutable('@' . (int) $opportunity->getCreatedTime()))->setTimezone($siteTimezone);
      if ($created >= $period['start'] && $created <= $period['end']) {
        $createdKey = $this->bucketKey($created, $period['bucket']);
        if (isset($inflow[$createdKey])) { $inflow[$createdKey]['count']++; $inflow[$createdKey]['value'] += $value; }
      }
    }

    $funnelRows = []; $pipelineRows = []; $previousCount = NULL;
    foreach (self::FUNNEL_STAGES as $index => $stage) {
      $data = $stageData[$stage];
      $conversion = $previousCount !== NULL && $previousCount > 0 ? number_format(($data['count'] / $previousCount) * 100, 1, ',', '.') . '% door' : ($data['count'] > 0 ? '100% start' : '0%');
      $funnelRows[] = $this->funnelRow($request, $stage, $data['count'], $conversion, $index);
      $pipelineRows[] = $this->pipelineRow($request, $stage, $data['value'], $data['weighted'], $index);
      $previousCount = $data['count'];
    }
    $lostValue = (float) ($stageData['Verloren']['value'] ?? 0.0); $wonCount = (int) ($stageData['Gewonnen']['count'] ?? 0); $closedCount = $wonCount + $lostCount;
    $winRate = $closedCount > 0 ? ($wonCount / $closedCount) * 100 : 0.0;
    $forecastMax = max([1.0, ...array_values(array_map(static fn(array $row): float => $row['value'], $forecast))]);
    $inflowMax = max([1, ...array_values(array_map(static fn(array $row): int => $row['count'], $inflow))]);

    return [
      '#type' => 'container', '#attributes' => ['class' => ['brebo-crm-dashboard']], '#attached' => ['library' => ['brebo_office_core/crm-dashboard']],
      'workspace_switch' => $this->workspaceSwitch($request, 'dashboard'),
      'kpis' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-crm-dashboard__kpis']],
        'active' => $this->kpi('Open kansen', (string) $activeCount, 'Actieve commerciële dossiers'), 'pipeline' => $this->kpi('Pipeline', $this->money($pipelineValue), 'Ongewogen open omzet'),
        'weighted' => $this->kpi('Gewogen pipeline', $this->money($weightedValue), 'Op basis van scoringskans'), 'won' => $this->kpi('Gewonnen omzet', $this->money($wonValue), 'Waarde in fase Gewonnen'),
        'overdue' => $this->kpi('Achterstallige opvolging', (string) $overdue, 'Open kansen met verlopen actiedatum'), 'lost' => $this->kpi('Verloren kansen', (string) $lostCount, 'Kansen in fase Verloren')],
      'charts' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-crm-dashboard__charts']],
        'funnel' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-crm-chart', 'brebo-crm-chart--funnel']], 'heading' => $this->markup('<div class="brebo-crm-chart__heading"><h2>Conversietrechter</h2><p>Vaste commerciële route van marketing lead naar gewonnen. Klik op een fase om de lijst te openen.</p></div>'), 'rows' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-crm-funnel']]] + $funnelRows, 'outflow' => $this->outflow($request, $lostCount, $lostValue, 'list')],
        'pipeline' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-crm-chart', 'brebo-crm-chart--pipeline']], 'heading' => $this->markup('<div class="brebo-crm-chart__heading"><h2>Omzetpijplijn</h2><p>Verwachte en gewogen omzet binnen dezelfde vaste commerciële trechter.</p></div>'), 'legend' => $this->markup('<div class="brebo-crm-chart__legend"><span><i class="brebo-crm-chart__legend-total"></i> Verwachte omzet</span><span><i class="brebo-crm-chart__legend-weighted"></i> Gewogen omzet</span></div>', ['i']), 'rows' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-crm-pipeline']]] + $pipelineRows, 'outflow' => $this->outflow($request, $lostCount, $lostValue, 'kanban')]],
      'period_selector' => $this->periodSelector($request, $periodKey, $period['label']),
      'analytics' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-crm-analytics']], 'forecast' => $this->forecastCard($forecast, $forecastMax, $period['label']), 'outcome' => $this->outcomeCard($request, $wonCount, $lostCount, $wonValue, $lostValue, $winRate), 'inflow' => $this->inflowCard($inflow, $inflowMax, $period['label'])],
      '#cache' => ['contexts' => ['user', 'user.permissions', 'url.query_args'], 'tags' => ['node_list:brebo_opportunity'], 'max-age' => 3600],
    ];
  }

  private function applyFilters(object $query, Request $request): void {
    $mine = (bool) $request->query->get('mine', FALSE); $ownerFilter = max(0, (int) $request->query->get('owner_filter', 0)); $organizationFilter = max(0, (int) $request->query->get('organization_filter', 0));
    $sourceFilter = trim((string) $request->query->get('source_filter', '')); $channelFilter = trim((string) $request->query->get('channel_filter', '')); $stageFilter = trim((string) $request->query->get('stage_filter', ''));
    $statusFilter = in_array((string) $request->query->get('status', 'all'), ['all', 'open', 'closed'], TRUE) ? (string) $request->query->get('status', 'all') : 'all';
    if ($organizationFilter > 0) $query->condition('field_brebo_opp_org_ref.target_id', $organizationFilter); if ($sourceFilter !== '') $query->condition('field_brebo_opp_source', $sourceFilter); if ($channelFilter !== '') $query->condition('field_brebo_opp_channel', $channelFilter);
    if (in_array($stageFilter, self::STAGES, TRUE)) $query->condition('field_brebo_opp_stage', $stageFilter); if ($statusFilter === 'open') $query->condition('field_brebo_opp_active', 1); elseif ($statusFilter === 'closed') $query->condition('field_brebo_opp_active', 0);
    if ($mine) $query->condition('field_brebo_opp_owner.target_id', (int) $this->currentUser()->id()); elseif ($ownerFilter > 0) $query->condition('field_brebo_opp_owner.target_id', $ownerFilter);
  }

  private function workspaceSwitch(Request $request, string $active): array {
    $baseQuery = $request->query->all(); unset($baseQuery['view']); $links = [];
    foreach (['dashboard' => 'Dashboard', 'list' => 'Lijst', 'kanban' => 'Kanban'] as $view => $label) $links[$view] = ['#type' => 'link', '#title' => $this->t($label), '#url' => Url::fromRoute('brebo_office_core.funnel', [], ['query' => $baseQuery + ['view' => $view]]), '#attributes' => ['class' => $active === $view ? ['is-active'] : []]];
    return ['#type' => 'container', '#attributes' => ['class' => ['brebo-crm-workspace-switch'], 'aria-label' => $this->t('CRM-weergave')]] + $links;
  }

  private function periodSelector(Request $request, string $selected, string $label): array {
    $query = $request->query->all(); unset($query['analytics_period']); $hidden = '';
    foreach ($query as $name => $value) if (is_scalar($value)) $hidden .= '<input type="hidden" name="' . htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '">';
    $options = ''; foreach (self::PERIODS as $key => $optionLabel) $options .= '<option value="' . $key . '"' . ($key === $selected ? ' selected' : '') . '>' . htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') . '</option>';
    return $this->markup('<form class="brebo-crm-period" method="get"><div><strong>Managementperiode</strong><small>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ' · grafieken passen automatisch hun schaal aan</small></div>' . $hidden . '<label><span>Periode</span><select name="analytics_period" onchange="this.form.submit()">' . $options . '</select></label></form>', ['input']);
  }

  private function kpi(string $label, string $value, string $detail): array { return $this->markup('<section class="brebo-crm-kpi"><span class="brebo-crm-kpi__label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span><strong class="brebo-crm-kpi__value">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</strong><span class="brebo-crm-kpi__detail">' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</span></section>'); }
  private function funnelRow(Request $request, string $stage, int $count, string $conversion, int $index): array { $tone = $index + 1; $url = $this->stageUrl($request, $stage, 'list'); return $this->markup('<a class="brebo-crm-funnel__row brebo-crm-funnel__row--tone-' . $tone . '" data-conversion="' . htmlspecialchars($conversion, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"><span class="brebo-crm-funnel__stage">' . htmlspecialchars($stage, ENT_QUOTES, 'UTF-8') . '</span><span class="brebo-crm-funnel__bar-wrap"><span class="brebo-crm-funnel__bar"><strong>' . $count . '</strong></span></span></a>'); }
  private function pipelineRow(Request $request, string $stage, float $value, float $weighted, int $index): array { $tone = $index + 1; $url = $this->stageUrl($request, $stage, 'kanban'); $weightedPercent = $value > 0 ? max(0, min(100, (int) round(($weighted / $value) * 100))) : 0; return $this->markup('<a class="brebo-crm-pipeline__row brebo-crm-pipeline__row--tone-' . $tone . '" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"><span class="brebo-crm-pipeline__stage">' . htmlspecialchars($stage, ENT_QUOTES, 'UTF-8') . '</span><span class="brebo-crm-pipeline__bar-wrap"><span class="brebo-crm-pipeline__bar"><progress class="brebo-crm-pipeline__weighted" max="100" value="' . $weightedPercent . '"></progress></span></span><span class="brebo-crm-pipeline__value"><strong>' . htmlspecialchars($this->money($value), ENT_QUOTES, 'UTF-8') . '</strong><small>' . htmlspecialchars($this->money($weighted), ENT_QUOTES, 'UTF-8') . ' gewogen</small></span></a>'); }
  private function outflow(Request $request, int $count, float $value, string $view): array { $url = $this->stageUrl($request, 'Verloren', $view); return $this->markup('<a class="brebo-crm-outflow" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"><span><strong>Verloren</strong><small>Uitstroom buiten de hoofdtrechter</small></span><span><strong>' . $count . '</strong><small>' . htmlspecialchars($this->money($value), ENT_QUOTES, 'UTF-8') . '</small></span></a>'); }
  private function forecastCard(array $forecast, float $max, string $periodLabel): array { $rows = ''; foreach ($forecast as $row) $rows .= '<div class="brebo-crm-analytics__row"><span><strong>' . htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') . '</strong><small>' . (int) $row['count'] . ' kansen</small></span><span class="brebo-crm-analytics__meter"><progress max="' . $max . '" value="' . (float) $row['value'] . '"></progress><progress class="is-weighted" max="' . $max . '" value="' . (float) $row['weighted'] . '"></progress></span><span class="brebo-crm-analytics__value"><strong>' . htmlspecialchars($this->money((float) $row['value']), ENT_QUOTES, 'UTF-8') . '</strong><small>' . htmlspecialchars($this->money((float) $row['weighted']), ENT_QUOTES, 'UTF-8') . ' gewogen</small></span></div>'; return $this->markup('<section class="brebo-crm-analytics__card"><header><h2>Sluitprognose</h2><p>' . htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') . ' · open pipeline op verwachte sluitdatum.</p></header><div class="brebo-crm-analytics__rows">' . $rows . '</div></section>'); }
  private function outcomeCard(Request $request, int $wonCount, int $lostCount, float $wonValue, float $lostValue, float $winRate): array { $closed = max(1, $wonCount + $lostCount); $wonUrl = $this->stageUrl($request, 'Gewonnen', 'list'); $lostUrl = $this->stageUrl($request, 'Verloren', 'list'); return $this->markup('<section class="brebo-crm-analytics__card"><header><h2>Gewonnen versus verloren</h2><p>Actuele uitkomstverdeling van afgesloten kansen.</p></header><div class="brebo-crm-outcome"><div class="brebo-crm-outcome__rate"><strong>' . number_format($winRate, 1, ',', '.') . '%</strong><span>winrate</span></div><a href="' . htmlspecialchars($wonUrl, ENT_QUOTES, 'UTF-8') . '"><span>Gewonnen</span><progress class="is-won" max="' . $closed . '" value="' . $wonCount . '"></progress><strong>' . $wonCount . ' · ' . htmlspecialchars($this->money($wonValue), ENT_QUOTES, 'UTF-8') . '</strong></a><a href="' . htmlspecialchars($lostUrl, ENT_QUOTES, 'UTF-8') . '"><span>Verloren</span><progress class="is-lost" max="' . $closed . '" value="' . $lostCount . '"></progress><strong>' . $lostCount . ' · ' . htmlspecialchars($this->money($lostValue), ENT_QUOTES, 'UTF-8') . '</strong></a></div></section>'); }
  private function inflowCard(array $inflow, int $max, string $periodLabel): array { $rows = ''; foreach ($inflow as $row) $rows .= '<div class="brebo-crm-analytics__row"><span><strong>' . htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') . '</strong><small>nieuwe kansen</small></span><span class="brebo-crm-analytics__meter"><progress max="' . $max . '" value="' . (int) $row['count'] . '"></progress></span><span class="brebo-crm-analytics__value"><strong>' . (int) $row['count'] . '</strong><small>' . htmlspecialchars($this->money((float) $row['value']), ENT_QUOTES, 'UTF-8') . ' instroom</small></span></div>'; return $this->markup('<section class="brebo-crm-analytics__card"><header><h2>Commerciële instroom</h2><p>' . htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') . ' · nieuwe kansen op aanmaakdatum.</p></header><div class="brebo-crm-analytics__rows">' . $rows . '</div></section>'); }

  private function periodDefinition(string $key, \DateTimeImmutable $today): array {
    $endToday = $today->setTime(23, 59, 59); $year = (int) $today->format('Y'); $month = (int) $today->format('n'); $quarterMonth = ((int) floor(($month - 1) / 3) * 3) + 1;
    return match ($key) {
      'today' => ['start' => $today, 'end' => $endToday, 'bucket' => 'day', 'label' => self::PERIODS[$key]],
      'this_week' => ['start' => $today->modify('monday this week'), 'end' => $endToday, 'bucket' => 'day', 'label' => self::PERIODS[$key]],
      'last_week' => ['start' => $today->modify('monday last week'), 'end' => $today->modify('sunday last week')->setTime(23, 59, 59), 'bucket' => 'day', 'label' => self::PERIODS[$key]],
      'this_month' => ['start' => $today->modify('first day of this month'), 'end' => $endToday, 'bucket' => 'day', 'label' => self::PERIODS[$key]],
      'last_month' => ['start' => $today->modify('first day of last month'), 'end' => $today->modify('last day of last month')->setTime(23, 59, 59), 'bucket' => 'week', 'label' => self::PERIODS[$key]],
      'this_quarter' => ['start' => $today->setDate($year, $quarterMonth, 1), 'end' => $endToday, 'bucket' => 'week', 'label' => self::PERIODS[$key]],
      'last_quarter' => ['start' => $today->setDate($year, $quarterMonth, 1)->modify('-3 months'), 'end' => $today->setDate($year, $quarterMonth, 1)->modify('-1 day')->setTime(23, 59, 59), 'bucket' => 'week', 'label' => self::PERIODS[$key]],
      'this_year' => ['start' => $today->setDate($year, 1, 1), 'end' => $endToday, 'bucket' => 'month', 'label' => self::PERIODS[$key]],
      'last_year' => ['start' => $today->setDate($year - 1, 1, 1), 'end' => $today->setDate($year - 1, 12, 31)->setTime(23, 59, 59), 'bucket' => 'month', 'label' => self::PERIODS[$key]],
      default => ['start' => $today->modify('first day of this month')->modify('-11 months'), 'end' => $endToday, 'bucket' => 'month', 'label' => self::PERIODS['last_12_months']],
    };
  }

  private function emptyBuckets(\DateTimeImmutable $start, \DateTimeImmutable $end, string $bucket, bool $forecast = FALSE): array {
    $rows = []; $cursor = $this->bucketStart($start, $bucket); $last = $this->bucketStart($end, $bucket);
    while ($cursor <= $last) { $key = $this->bucketKey($cursor, $bucket); $rows[$key] = ['label' => $this->bucketLabel($cursor, $bucket), 'count' => 0, 'value' => 0.0] + ($forecast ? ['weighted' => 0.0] : []); $cursor = $this->nextBucket($cursor, $bucket); }
    return $rows;
  }
  private function bucketStart(\DateTimeImmutable $date, string $bucket): \DateTimeImmutable { return match ($bucket) { 'month' => $date->modify('first day of this month')->setTime(0, 0), 'week' => $date->modify('monday this week')->setTime(0, 0), default => $date->setTime(0, 0) }; }
  private function nextBucket(\DateTimeImmutable $date, string $bucket): \DateTimeImmutable { return $date->modify($bucket === 'month' ? '+1 month' : ($bucket === 'week' ? '+1 week' : '+1 day')); }
  private function bucketKey(\DateTimeImmutable $date, string $bucket): string { return match ($bucket) { 'month' => $date->format('Y-m'), 'week' => $date->format('o-\WW'), default => $date->format('Y-m-d') }; }
  private function bucketLabel(\DateTimeImmutable $date, string $bucket): string { if ($bucket === 'month') return $this->monthLabel($date); if ($bucket === 'week') return 'week ' . $date->format('W'); return $date->format('d-m'); }
  private function markup(string $markup, array $extraTags = []): array { return ['#markup' => $markup, '#allowed_tags' => array_values(array_unique([...self::MARKUP_TAGS, ...$extraTags]))]; }
  private function stageUrl(Request $request, string $stage, string $view): string { $query = $request->query->all(); $query['stage_filter'] = $stage; $query['view'] = $view; return Url::fromRoute('brebo_office_core.funnel', [], ['query' => $query])->toString(); }
  private function monthLabel(\DateTimeImmutable $month): string { $months = [1 => 'jan', 2 => 'feb', 3 => 'mrt', 4 => 'apr', 5 => 'mei', 6 => 'jun', 7 => 'jul', 8 => 'aug', 9 => 'sep', 10 => 'okt', 11 => 'nov', 12 => 'dec']; return $months[(int) $month->format('n')] . ' ' . $month->format('Y'); }
  private function money(float $value): string { return '€ ' . number_format($value, 0, ',', '.'); }
}
