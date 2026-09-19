<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\brebo_office_core\Project\ProjectLifecycleStatus;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/** Provides the operational project portfolio. */
final class ProjectPortfolioController extends ControllerBase {

  /** Builds a steering-ready overview of all accessible projects. */
  public function overview(Request $request): array {
    $allowedViews = ['list', 'kanban', 'planning'];
    $view = in_array((string) $request->query->get('view'), $allowedViews, TRUE)
      ? (string) $request->query->get('view')
      : 'list';
    $search = trim((string) $request->query->get('q', ''));
    $statusFilter = trim((string) $request->query->get('status', ''));
    $attentionFilter = trim((string) $request->query->get('attention', ''));
    $sort = trim((string) $request->query->get('sort', 'changed_desc'));

    $storage = $this->entityTypeManager()->getStorage('node');
    $projectIds = array_values($storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_project')
      ->execute());
    $projects = $storage->loadMultiple($projectIds);
    $relatedCounts = $this->relatedCounts(array_map('intval', $projectIds));
    $planning = $this->planningSummary(array_map('intval', $projectIds));

    $statusOptions = ProjectLifecycleStatus::options();
    $items = [];
    $today = new \DateTimeImmutable('today');
    $recentBoundary = $today->modify('-7 days')->getTimestamp();

    foreach ($projects as $project) {
      if (!$project instanceof NodeInterface) {
        continue;
      }
      $projectId = (int) $project->id();
      $statusValue = ProjectLifecycleStatus::value($project);
      $status = ProjectLifecycleStatus::label($project);

      $buildings = $project->hasField('field_brebo_building_refs')
        ? $project->get('field_brebo_building_refs')->referencedEntities()
        : [];
      $buildingLabels = [];
      $buildingLinks = [];
      foreach ($buildings as $building) {
        if (!$building instanceof NodeInterface) {
          continue;
        }
        $buildingLabels[] = (string) $building->label();
        $buildingLinks[] = Link::fromTextAndUrl(
          $building->label(),
          Url::fromRoute('brebo_office_core.building_dashboard', ['node' => $building->id()])
        )->toString();
      }

      $counts = $relatedCounts[$projectId] ?? ['brebo_project_scope' => 0, 'brebo_action' => 0, 'brebo_risk' => 0, 'brebo_signal' => 0];
      $plan = $planning[$projectId] ?? [
        'start' => NULL,
        'end' => NULL,
        'total' => 0,
        'done' => 0,
        'late' => 0,
        'blocked' => 0,
        'critical' => 0,
        'progress' => 0,
        'signal' => 'green',
      ];
      $dossierCount = $counts['brebo_action'] + $counts['brebo_risk'] + $counts['brebo_signal'];
      $attention = $plan['signal'] !== 'green' || $dossierCount > 0;
      $searchText = mb_strtolower(implode(' ', array_filter([
        (string) $project->label(),
        $this->value($project, 'field_brebo_project_code'),
        $this->value($project, 'field_brebo_client'),
        $this->value($project, 'field_brebo_project_kind'),
        implode(' ', $buildingLabels),
      ], static fn (string $value): bool => $value !== '—')));

      if ($search !== '' && !str_contains($searchText, mb_strtolower($search))) {
        continue;
      }
      if ($statusFilter !== '' && $statusValue !== $statusFilter) {
        continue;
      }
      if ($attentionFilter !== '' && $plan['signal'] !== $attentionFilter) {
        continue;
      }

      $items[] = [
        'project' => $project,
        'id' => $projectId,
        'status' => $status,
        'status_value' => $statusValue,
        'active' => ProjectLifecycleStatus::isActive($project),
        'execution' => ProjectLifecycleStatus::isExecution($project),
        'buildings' => $buildingLabels,
        'building_links' => $buildingLinks,
        'counts' => $counts,
        'dossier_count' => $dossierCount,
        'plan' => $plan,
        'attention' => $attention,
        'changed' => $project->getChangedTime(),
      ];
    }

    $this->sortItems($items, $sort);

    $kpis = [
      'active' => 0,
      'execution' => 0,
      'attention' => 0,
      'recent' => 0,
    ];
    foreach ($items as $item) {
      $kpis['active'] += $item['active'] ? 1 : 0;
      $kpis['execution'] += $item['execution'] ? 1 : 0;
      $kpis['attention'] += $item['attention'] ? 1 : 0;
      $kpis['recent'] += $item['changed'] >= $recentBoundary ? 1 : 0;
    }

    $baseQuery = array_filter([
      'q' => $search,
      'status' => $statusFilter,
      'attention' => $attentionFilter,
      'sort' => $sort,
    ], static fn (string $value): bool => $value !== '');

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-status-overview', 'brebo-project-portfolio']],
      '#attached' => [
        'library' => ['brebo_office_core/status-list-kanban'],
      ],
      'top' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-project-portfolio__top']],
        'kpis' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['brebo-project-kpis']],
          'active' => $this->kpi((string) $kpis['active'], (string) $this->t('Actieve projecten')),
          'execution' => $this->kpi((string) $kpis['execution'], (string) $this->t('Projecten in uitvoering')),
          'attention' => $this->kpi((string) $kpis['attention'], (string) $this->t('Projecten met aandacht')),
          'recent' => $this->kpi((string) $kpis['recent'], (string) $this->t('Recent gewijzigd')),
        ],
      ],
      'controls' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-project-controls']],
        'form' => [
          '#type' => 'inline_template',
          '#template' => '<form method="get" class="brebo-project-controls__form">'
            . '<input type="hidden" name="view" value="{{ view }}">'
            . '<label><span>Zoeken</span><input type="search" name="q" value="{{ q }}" placeholder="Project, code, opdrachtgever of gebouw"></label>'
            . '<label><span>Status</span><select name="status"><option value="">Alle statussen</option>{% for value, label in statuses %}<option value="{{ value }}"{% if value == status %} selected{% endif %}>{{ label }}</option>{% endfor %}</select></label>'
            . '<label><span>Voortgangssignaal</span><select name="attention"><option value="">Alles</option><option value="red"{% if attention == "red" %} selected{% endif %}>Rood</option><option value="orange"{% if attention == "orange" %} selected{% endif %}>Oranje</option><option value="green"{% if attention == "green" %} selected{% endif %}>Groen</option></select></label>'
            . '<label><span>Sorteren</span><select name="sort"><option value="changed_desc"{% if sort == "changed_desc" %} selected{% endif %}>Laatst gewijzigd</option><option value="name_asc"{% if sort == "name_asc" %} selected{% endif %}>Projectnaam</option><option value="code_asc"{% if sort == "code_asc" %} selected{% endif %}>Projectnummer</option><option value="start_asc"{% if sort == "start_asc" %} selected{% endif %}>Startdatum</option><option value="end_asc"{% if sort == "end_asc" %} selected{% endif %}>Einddatum</option><option value="progress_desc"{% if sort == "progress_desc" %} selected{% endif %}>Voortgang</option><option value="attention"{% if sort == "attention" %} selected{% endif %}>Urgentie</option></select></label>'
            . '<button type="submit" class="button">Toepassen</button><a href="{{ reset }}" class="button">Wissen</a></form>',
          '#context' => [
            'view' => $view,
            'q' => $search,
            'statuses' => $statusOptions,
            'status' => $statusFilter,
            'attention' => $attentionFilter,
            'sort' => $sort,
            'reset' => Url::fromRoute('brebo_office_core.projects', [], ['query' => ['view' => $view]])->toString(),
          ],
        ],
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-status-overview__actions']],
        'add' => [
          '#type' => 'link',
          '#title' => $this->t('Nieuw project'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_project']),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'switch' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['brebo-status-overview__switch'], 'aria-label' => $this->t('Weergave')],
          'list' => $this->viewLink('Lijst', 'list', $view, $baseQuery),
          'kanban' => $this->viewLink('Kanban', 'kanban', $view, $baseQuery),
          'planning' => $this->viewLink('Verzamelplanning', 'planning', $view, $baseQuery),
        ],
      ],
    ];

    if ($view === 'list') {
      $build['projects'] = $this->listView($items);
    }
    elseif ($view === 'kanban') {
      $build['projects'] = $this->kanbanView($items);
    }
    else {
      $build['traffic'] = $this->trafficLights($items, $baseQuery, $attentionFilter);
      $build['projects'] = $this->planningView($items);
    }

    $build['#cache'] = [
      'contexts' => ['user.permissions', 'url.query_args'],
      'tags' => [
        'node_list:brebo_project',
        'node_list:brebo_project_scope',
        'node_list:brebo_action',
        'node_list:brebo_risk',
        'node_list:brebo_signal',
        'node_list:brebo_plan_activity',
      ],
      'max-age' => 300,
    ];
    return $build;
  }

  /** @return array<string, mixed> */
  private function listView(array $items): array {
    $rows = [];
    foreach ($items as $item) {
      /** @var \Drupal\node\NodeInterface $project */
      $project = $item['project'];
      $projectUrl = Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $item['id']]);
      $planningUrl = Url::fromRoute('brebo_office_core.project_planning', ['node' => $item['id']]);
      $rows[] = [
        ['data' => Link::fromTextAndUrl($project->label(), $projectUrl)->toRenderable()],
        $this->value($project, 'field_brebo_project_code'),
        $this->value($project, 'field_brebo_client'),
        ['data' => $item['building_links'] ? ['#markup' => implode('<br>', $item['building_links'])] : '—'],
        $this->value($project, 'field_brebo_project_kind'),
        $item['status'],
        $item['plan']['progress'] . '%',
        $this->signalLabel($item['plan']['signal']),
        \Drupal::service('date.formatter')->format($item['changed'], 'short'),
        ['data' => ['#type' => 'operations', '#links' => [
          'open' => ['title' => $this->t('Open project'), 'url' => $projectUrl],
          'planning' => ['title' => $this->t('Planning'), 'url' => $planningUrl],
          'edit' => ['title' => $this->t('Bewerken'), 'url' => Url::fromRoute('entity.node.edit_form', ['node' => $item['id']])],
          'documents' => ['title' => $this->t('Documenten'), 'url' => Url::fromRoute('brebo_document_data.node_dossier', ['node' => $item['id']])],
        ]]],
      ];
    }
    return [
      '#type' => 'table',
      '#header' => [$this->t('Project'), $this->t('Code'), $this->t('Opdrachtgever'), $this->t('Gebouw'), $this->t('Soort'), $this->t('Status'), $this->t('Voortgang'), $this->t('Voortgangssignaal'), $this->t('Gewijzigd'), $this->t('Acties')],
      '#rows' => $rows,
      '#empty' => $this->t('Geen projecten binnen deze selectie.'),
      '#sticky' => TRUE,
    ];
  }

  /** @return array<string, mixed> */
  private function kanbanView(array $items): array {
    $kanban = [];
    foreach ($items as $item) {
      /** @var \Drupal\node\NodeInterface $project */
      $project = $item['project'];
      $projectUrl = Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $item['id']]);
      $planningUrl = Url::fromRoute('brebo_office_core.project_planning', ['node' => $item['id']]);
      $kanban[$item['status']][] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-status-kanban__card']],
        'title' => ['#type' => 'link', '#title' => $project->label(), '#url' => $projectUrl, '#attributes' => ['class' => ['brebo-status-kanban__title']]],
        'traffic' => ['#markup' => '<span class="brebo-project-signal brebo-project-signal--' . $item['plan']['signal'] . '">' . htmlspecialchars($this->signalLabel($item['plan']['signal']), ENT_QUOTES, 'UTF-8') . '</span>'],
        'meta' => ['#markup' => '<div class="brebo-status-kanban__meta"><span>' . htmlspecialchars($this->value($project, 'field_brebo_project_code'), ENT_QUOTES, 'UTF-8') . '</span><span>' . htmlspecialchars($this->value($project, 'field_brebo_client'), ENT_QUOTES, 'UTF-8') . '</span><span>' . $this->t('@progress% voortgang', ['@progress' => $item['plan']['progress']]) . '</span></div>'],
        'actions' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-kanban__actions']],
          'open' => ['#type' => 'link', '#title' => $this->t('Openen'), '#url' => $projectUrl],
          'planning' => ['#type' => 'link', '#title' => $this->t('Planning'), '#url' => $planningUrl],
        ],
      ];
    }
    ksort($kanban, SORT_NATURAL | SORT_FLAG_CASE);
    $board = ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-kanban']]];
    foreach ($kanban as $status => $cards) {
      $board['status_' . md5($status)] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-status-kanban__column']],
        'header' => ['#markup' => '<div class="brebo-status-kanban__header"><span>' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span><span class="brebo-status-kanban__count">' . count($cards) . '</span></div>'],
        'cards' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-kanban__cards']]] + $cards,
      ];
    }
    if (!$kanban) {
      $board['empty'] = ['#markup' => '<p>' . $this->t('Geen projecten binnen deze selectie.') . '</p>'];
    }
    return $board;
  }

  /** @return array<string, mixed> */
  private function planningView(array $items): array {
    $rows = [];
    foreach ($items as $item) {
      if (!$item['plan']['start'] || !$item['plan']['end']) {
        continue;
      }
      /** @var \Drupal\node\NodeInterface $project */
      $project = $item['project'];
      $rows[] = [
        'id' => $item['id'],
        'title' => (string) $project->label(),
        'code' => $this->value($project, 'field_brebo_project_code'),
        'client' => $this->value($project, 'field_brebo_client'),
        'start' => $item['plan']['start'],
        'end' => $item['plan']['end'],
        'progress' => $item['plan']['progress'],
        'signal' => $item['plan']['signal'],
        'url' => Url::fromRoute('brebo_office_core.project_planning', ['node' => $item['id']])->toString(),
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['brebo-project-gantt'],
        'data-brebo-project-gantt' => 'true',
        'data-projects' => json_encode($rows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
      ],
      'toolbar' => [
        '#markup' => '<div class="brebo-project-gantt__toolbar"><strong>' . $this->t('Verzamelplanning') . '</strong><div class="brebo-project-gantt__scale" data-brebo-gantt-scale><button type="button" data-scale="month">Maand</button><button type="button" data-scale="quarter" class="is-active">Kwartaal</button><button type="button" data-scale="year">Jaar</button></div></div>',
      ],
      'canvas' => [
        '#markup' => $rows
          ? '<div class="brebo-project-gantt__canvas" data-brebo-gantt-canvas><p>' . $this->t('Planning laden…') . '</p></div>'
          : '<div class="brebo-project-gantt__empty"><p>' . $this->t('Voor deze selectie zijn nog geen projecten met geplande activiteiten.') . '</p></div>',
      ],
    ];
  }

  /** @return array<string, mixed> */
  private function trafficLights(array $items, array $query, string $current): array {
    $counts = ['green' => 0, 'orange' => 0, 'red' => 0];
    foreach ($items as $item) {
      $counts[$item['plan']['signal']]++;
    }
    $links = [];
    foreach (['green' => 'Op schema', 'orange' => 'Aandacht', 'red' => 'Actie nodig'] as $signal => $label) {
      $target = $query;
      $target['view'] = 'planning';
      $target['attention'] = $current === $signal ? '' : $signal;
      $links[$signal] = [
        '#type' => 'link',
        '#title' => $this->t('@label: @count', ['@label' => $label, '@count' => $counts[$signal]]),
        '#url' => Url::fromRoute('brebo_office_core.projects', [], ['query' => array_filter($target)]),
        '#attributes' => ['class' => ['brebo-project-traffic__item', 'brebo-project-traffic__item--' . $signal, $current === $signal ? 'is-active' : '']],
      ];
    }
    return ['#type' => 'container', '#attributes' => ['class' => ['brebo-project-traffic']]] + $links;
  }

  /** @return array<string, array<string, int>> */
  private function relatedCounts(array $projectIds): array {
    $counts = [];
    if (!$projectIds) {
      return $counts;
    }
    foreach (['brebo_project_scope', 'brebo_action', 'brebo_risk', 'brebo_signal'] as $bundle) {
      $ids = $this->entityTypeManager()->getStorage('node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', $bundle)
        ->condition('field_brebo_project_ref.target_id', $projectIds, 'IN')
        ->execute();
      foreach ($this->entityTypeManager()->getStorage('node')->loadMultiple($ids) as $entity) {
        if (!$entity instanceof NodeInterface || !$entity->hasField('field_brebo_project_ref')) {
          continue;
        }
        $projectId = (int) $entity->get('field_brebo_project_ref')->target_id;
        if ($projectId > 0) {
          $counts[$projectId][$bundle] = ($counts[$projectId][$bundle] ?? 0) + 1;
        }
      }
    }
    foreach ($counts as &$count) {
      foreach (['brebo_project_scope', 'brebo_action', 'brebo_risk', 'brebo_signal'] as $bundle) {
        $count[$bundle] ??= 0;
      }
    }
    return $counts;
  }

  /** @return array<int, array<string, mixed>> */
  private function planningSummary(array $projectIds): array {
    $summary = [];
    if (!$projectIds) {
      return $summary;
    }
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_plan_activity')
      ->condition('field_brebo_project_ref.target_id', $projectIds, 'IN')
      ->execute();
    $today = date('Y-m-d');
    foreach ($storage->loadMultiple($ids) as $activity) {
      if (!$activity instanceof NodeInterface || !$activity->hasField('field_brebo_project_ref')) {
        continue;
      }
      $projectId = (int) $activity->get('field_brebo_project_ref')->target_id;
      if ($projectId <= 0) {
        continue;
      }
      $row = $summary[$projectId] ?? ['start' => NULL, 'end' => NULL, 'total' => 0, 'done' => 0, 'late' => 0, 'blocked' => 0, 'critical' => 0, 'progress_sum' => 0.0];
      $start = $this->value($activity, 'field_brebo_plan_start');
      $end = $this->value($activity, 'field_brebo_plan_end');
      $status = $this->value($activity, 'field_brebo_plan_status');
      $progress = (float) $this->rawValue($activity, 'field_brebo_plan_progress', 0);
      $done = $status === 'Gereed' || $progress >= 100;
      $blocked = $status === 'Geblokkeerd';
      $late = !$done && $end !== '—' && $end < $today;
      $critical = (bool) $this->rawValue($activity, 'field_brebo_plan_critical', FALSE);
      if ($start !== '—' && ($row['start'] === NULL || $start < $row['start'])) {
        $row['start'] = $start;
      }
      if ($end !== '—' && ($row['end'] === NULL || $end > $row['end'])) {
        $row['end'] = $end;
      }
      $row['total']++;
      $row['done'] += $done ? 1 : 0;
      $row['late'] += $late ? 1 : 0;
      $row['blocked'] += $blocked ? 1 : 0;
      $row['critical'] += $critical ? 1 : 0;
      $row['progress_sum'] += max(0, min(100, $progress));
      $summary[$projectId] = $row;
    }
    foreach ($summary as $projectId => &$row) {
      $row['progress'] = $row['total'] > 0 ? (int) round($row['progress_sum'] / $row['total']) : 0;
      $row['signal'] = ($row['blocked'] > 0 || $row['late'] > 0)
        ? 'red'
        : ($row['critical'] > 0 ? 'orange' : 'green');
      unset($row['progress_sum']);
    }
    return $summary;
  }

  private function sortItems(array &$items, string $sort): void {
    usort($items, function (array $left, array $right) use ($sort): int {
      /** @var \Drupal\node\NodeInterface $leftProject */
      $leftProject = $left['project'];
      /** @var \Drupal\node\NodeInterface $rightProject */
      $rightProject = $right['project'];
      return match ($sort) {
        'name_asc' => strnatcasecmp((string) $leftProject->label(), (string) $rightProject->label()),
        'code_asc' => strnatcasecmp($this->value($leftProject, 'field_brebo_project_code'), $this->value($rightProject, 'field_brebo_project_code')),
        'start_asc' => strcmp((string) ($left['plan']['start'] ?? '9999-12-31'), (string) ($right['plan']['start'] ?? '9999-12-31')),
        'end_asc' => strcmp((string) ($left['plan']['end'] ?? '9999-12-31'), (string) ($right['plan']['end'] ?? '9999-12-31')),
        'progress_desc' => $right['plan']['progress'] <=> $left['plan']['progress'],
        'attention' => $this->signalRank($right['plan']['signal']) <=> $this->signalRank($left['plan']['signal']),
        default => $right['changed'] <=> $left['changed'],
      };
    });
  }

  private function signalRank(string $signal): int {
    return match ($signal) {
      'red' => 3,
      'orange' => 2,
      default => 1,
    };
  }

  private function signalLabel(string $signal): string {
    return match ($signal) {
      'red' => (string) $this->t('Actie nodig'),
      'orange' => (string) $this->t('Aandacht'),
      default => (string) $this->t('Op schema'),
    };
  }

  /** @return array<string, mixed> */
  private function kpi(string $value, string $label): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-kpi']],
      'value' => ['#plain_text' => $value, '#prefix' => '<strong>', '#suffix' => '</strong>'],
      'label' => ['#plain_text' => $label, '#prefix' => '<span>', '#suffix' => '</span>'],
    ];
  }

  /** @return array<string, mixed> */
  private function viewLink(string $label, string $targetView, string $currentView, array $query): array {
    $query['view'] = $targetView;
    return [
      '#type' => 'link',
      '#title' => $this->t($label),
      '#url' => Url::fromRoute('brebo_office_core.projects', [], ['query' => $query]),
      '#attributes' => ['class' => $currentView === $targetView ? ['is-active'] : []],
    ];
  }

  private function value(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '—';
    }
    return (string) ($node->get($field)->value ?? '—');
  }

  private function rawValue(NodeInterface $node, string $field, mixed $default = NULL): mixed {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return $default;
    }
    return $node->get($field)->value ?? $default;
  }

}
