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
      $buildings = $project->hasField('field_brebo_building_refs') ? $project->get('field_brebo_building_refs')->referencedEntities() : [];
      $buildingLabels = [];
      $buildingLinks = [];
      foreach ($buildings as $building) {
        if (!$building instanceof NodeInterface) {
          continue;
        }
        $buildingLabels[] = (string) $building->label();
        $buildingLinks[] = Link::fromTextAndUrl($building->label(), Url::fromRoute('brebo_office_core.building_dashboard', ['node' => $building->id()]))->toString();
      }
      $counts = $relatedCounts[$projectId] ?? ['brebo_project_scope' => 0, 'brebo_action' => 0, 'brebo_risk' => 0, 'brebo_signal' => 0];
      $plan = $planning[$projectId] ?? ['start' => NULL, 'end' => NULL, 'total' => 0, 'done' => 0, 'late' => 0, 'blocked' => 0, 'critical' => 0, 'progress' => 0, 'signal' => 'green'];
      $dossierCount = $counts['brebo_action'] + $counts['brebo_risk'] + $counts['brebo_signal'];
      $attention = $plan['signal'] !== 'green' || $dossierCount > 0;
      $searchText = mb_strtolower(implode(' ', array_filter([(string) $project->label(), $this->value($project, 'field_brebo_project_code'), $this->value($project, 'field_brebo_client'), $this->value($project, 'field_brebo_project_kind'), implode(' ', $buildingLabels)], static fn (string $value): bool => $value !== '—')));
      if ($search !== '' && !str_contains($searchText, mb_strtolower($search))) {
        continue;
      }
      if ($statusFilter !== '' && $statusValue !== $statusFilter) {
        continue;
      }
      if ($attentionFilter !== '' && $plan['signal'] !== $attentionFilter) {
        continue;
      }
      $items[] = ['project' => $project, 'id' => $projectId, 'status' => $status, 'status_value' => $statusValue, 'active' => ProjectLifecycleStatus::isActive($project), 'execution' => ProjectLifecycleStatus::isExecution($project), 'buildings' => $buildingLabels, 'building_links' => $buildingLinks, 'counts' => $counts, 'dossier_count' => $dossierCount, 'plan' => $plan, 'attention' => $attention, 'changed' => $project->getChangedTime()];
    }

    $this->sortItems($items, $sort);
    $kpis = ['active' => 0, 'execution' => 0, 'attention' => 0, 'recent' => 0];
    foreach ($items as $item) {
      $kpis['active'] += $item['active'] ? 1 : 0;
      $kpis['execution'] += $item['execution'] ? 1 : 0;
      $kpis['attention'] += $item['attention'] ? 1 : 0;
      $kpis['recent'] += $item['changed'] >= $recentBoundary ? 1 : 0;
    }
    $baseQuery = array_filter(['q' => $search, 'status' => $statusFilter, 'attention' => $attentionFilter, 'sort' => $sort], static fn (string $value): bool => $value !== '');

    $build = [
      '#type' => 'container', '#attributes' => ['class' => ['brebo-status-overview', 'brebo-project-portfolio']], '#attached' => ['library' => ['brebo_office_core/status-list-kanban']],
      'top' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-project-portfolio__top']], 'kpis' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-project-kpis']], 'active' => $this->kpi((string) $kpis['active'], (string) $this->t('Actieve projecten')), 'execution' => $this->kpi((string) $kpis['execution'], (string) $this->t('Projecten in uitvoering')), 'attention' => $this->kpi((string) $kpis['attention'], (string) $this->t('Projecten met aandacht')), 'recent' => $this->kpi((string) $kpis['recent'], (string) $this->t('Recent gewijzigd'))]],
      'controls' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-project-controls']], 'form' => ['#type' => 'inline_template', '#template' => '<form method="get" class="brebo-project-controls__form"><input type="hidden" name="view" value="{{ view }}"><label><span>Zoeken</span><input type="search" name="q" value="{{ q }}" placeholder="Project, code, opdrachtgever of gebouw"></label><label><span>Status</span><select name="status"><option value="">Alle statussen</option>{% for value, label in statuses %}<option value="{{ value }}"{% if value == status %} selected{% endif %}>{{ label }}</option>{% endfor %}</select></label><label><span>Voortgang</span><select name="attention"><option value="">Alles</option><option value="red"{% if attention == "red" %} selected{% endif %}>Rood</option><option value="orange"{% if attention == "orange" %} selected{% endif %}>Oranje</option><option value="green"{% if attention == "green" %} selected{% endif %}>Groen</option></select></label><label><span>Sorteren</span><select name="sort"><option value="changed_desc"{% if sort == "changed_desc" %} selected{% endif %}>Laatst gewijzigd</option><option value="name_asc"{% if sort == "name_asc" %} selected{% endif %}>Projectnaam</option><option value="code_asc"{% if sort == "code_asc" %} selected{% endif %}>Projectnummer</option><option value="start_asc"{% if sort == "start_asc" %} selected{% endif %}>Startdatum</option><option value="end_asc"{% if sort == "end_asc" %} selected{% endif %}>Einddatum</option><option value="progress_desc"{% if sort == "progress_desc" %} selected{% endif %}>Voortgang</option><option value="attention"{% if sort == "attention" %} selected{% endif %}>Urgentie</option></select></label><button type="submit" class="button">Toepassen</button><a href="{{ reset }}" class="button">Wissen</a></form>', '#context' => ['view' => $view, 'q' => $search, 'statuses' => $statusOptions, 'status' => $statusFilter, 'attention' => $attentionFilter, 'sort' => $sort, 'reset' => Url::fromRoute('brebo_office_core.projects', [], ['query' => ['view' => $view]])->toString()]]],
      'actions' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-overview__actions']], 'add' => ['#type' => 'link', '#title' => $this->t('Nieuw project'), '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_project']), '#attributes' => ['class' => ['button', 'button--primary']]], 'switch' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-overview__switch'], 'aria-label' => $this->t('Weergave')], 'list' => $this->viewLink('Lijst', 'list', $view, $baseQuery), 'kanban' => $this->viewLink('Kanban', 'kanban', $view, $baseQuery), 'planning' => $this->viewLink('Verzamelplanning', 'planning', $view, $baseQuery)]],
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
    $build['#cache'] = ['contexts' => ['user.permissions', 'url.query_args'], 'tags' => ['node_list:brebo_project', 'node_list:brebo_project_scope', 'node_list:brebo_action', 'node_list:brebo_risk', 'node_list:brebo_signal', 'node_list:brebo_plan_activity'], 'max-age' => 300];
    return $build;
  }

  private function listView(array $items): array {
    $rows = [];
    foreach ($items as $item) {
      $project = $item['project'];
      $projectUrl = Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $item['id']]);
      $planningUrl = Url::fromRoute('brebo_office_core.project_planning', ['node' => $item['id']]);
      $rows[] = [['data' => Link::fromTextAndUrl($project->label(), $projectUrl)->toRenderable()], $this->value($project, 'field_brebo_project_code'), $this->value($project, 'field_brebo_client'), ['data' => $item['building_links'] ? ['#markup' => implode('<br>', $item['building_links'])] : '—'], $this->value($project, 'field_brebo_project_kind'), $item['status'], $item['plan']['progress'] . '%', $this->signalLabel($item['plan']['signal']), \Drupal::service('date.formatter')->format($item['changed'], 'short'), ['data' => ['#type' => 'operations', '#links' => ['open' => ['title' => $this->t('Open project'), 'url' => $projectUrl], 'planning' => ['title' => $this->t('Planning'), 'url' => $planningUrl], 'edit' => ['title' => $this->t('Bewerken'), 'url' => Url::fromRoute('entity.node.edit_form', ['node' => $item['id']])], 'documents' => ['title' => $this->t('Documenten'), 'url' => Url::fromRoute('brebo_document_data.node_dossier', ['node' => $item['id']])]]]]];
    }
    return ['#type' => 'table', '#header' => [$this->t('Project'), $this->t('Code'), $this->t('Opdrachtgever'), $this->t('Gebouw'), $this->t('Soort'), $this->t('Status'), $this->t('Voortgang'), $this->t('Voortgangssignaal'), $this->t('Gewijzigd'), $this->t('Acties')], '#rows' => $rows, '#empty' => $this->t('Geen projecten binnen deze selectie.'), '#sticky' => TRUE];
  }

  private function kanbanView(array $items): array {
    $kanban = [];
    foreach ($items as $item) {
      $project = $item['project'];
      $projectUrl = Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $item['id']]);
      $planningUrl = Url::fromRoute('brebo_office_core.project_planning', ['node' => $item['id']]);
      $kanban[$item['status']][] = ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-kanban__card']], 'title' => ['#type' => 'link', '#title' => $project->label(), '#url' => $projectUrl, '#attributes' => ['class' => ['brebo-status-kanban__title']]], 'traffic' => ['#markup' => '<span class="brebo-project-signal brebo-project-signal--' . $item['plan']['signal'] . '">' . htmlspecialchars($this->signalLabel($item['plan']['signal']), ENT_QUOTES, 'UTF-8') . '</span>'], 'meta' => ['#markup' => '<div class="brebo-status-kanban__meta"><span>' . htmlspecialchars($this->value($project, 'field_brebo_project_code'), ENT_QUOTES, 'UTF-8') . '</span><span>' . htmlspecialchars($this->value($project, 'field_brebo_client'), ENT_QUOTES, 'UTF-8') . '</span><span>' . $this->t('@progress% voortgang', ['@progress' => $item['plan']['progress']]) . '</span></div>'], 'planning' => ['#type' => 'link', '#title' => $this->t('Planning'), '#url' => $planningUrl, '#attributes' => ['class' => ['button', 'button--small']]]];
    }
    $columns = [];
    foreach (ProjectLifecycleStatus::options() as $status) {
      if (!empty($kanban[$status])) {
        $columns[] = ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-kanban__column']], 'heading' => ['#markup' => '<h2>' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . ' <span>' . count($kanban[$status]) . '</span></h2>'], 'cards' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-kanban__cards']]] + $kanban[$status]];
      }
    }
    return ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-kanban']], 'columns' => $columns];
  }

  private function planningView(array $items): array {
    $rows = [];
    foreach ($items as $item) {
      $project = $item['project'];
      $plan = $item['plan'];
      $rows[] = [['data' => Link::fromTextAndUrl($project->label(), Url::fromRoute('brebo_office_core.project_planning', ['node' => $item['id']]))->toRenderable()], $this->dateLabel($plan['start']), $this->dateLabel($plan['end']), $plan['progress'] . '%', (string) $plan['late'], (string) $plan['blocked'], (string) $plan['critical'], $this->signalLabel($plan['signal'])];
    }
    return ['#type' => 'table', '#header' => [$this->t('Project'), $this->t('Start'), $this->t('Einde'), $this->t('Voortgang'), $this->t('Te laat'), $this->t('Geblokkeerd'), $this->t('Kritiek'), $this->t('Voortgangssignaal')], '#rows' => $rows, '#empty' => $this->t('Geen projecten met planning binnen deze selectie.'), '#sticky' => TRUE];
  }

  private function trafficLights(array $items, array $baseQuery, string $active): array {
    $counts = ['red' => 0, 'orange' => 0, 'green' => 0];
    foreach ($items as $item) {
      if (isset($counts[$item['plan']['signal']])) {
        $counts[$item['plan']['signal']]++;
      }
    }
    $links = [];
    foreach (['red' => 'Rood', 'orange' => 'Oranje', 'green' => 'Groen'] as $signal => $label) {
      $query = $baseQuery;
      $query['attention'] = $signal;
      $query['view'] = 'planning';
      $links[$signal] = ['#type' => 'link', '#title' => $this->t('@label · @count', ['@label' => $label, '@count' => $counts[$signal]]), '#url' => Url::fromRoute('brebo_office_core.projects', [], ['query' => $query]), '#attributes' => ['class' => ['brebo-project-traffic__item', 'brebo-project-traffic__item--' . $signal, $active === $signal ? 'is-active' : '']]];
    }
    return ['#type' => 'container', '#attributes' => ['class' => ['brebo-project-traffic'], 'aria-label' => $this->t('Voortgangssignalen')], 'label' => ['#markup' => '<strong>' . $this->t('Voortgang') . '</strong>'], 'links' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-project-traffic__links']]] + $links];
  }

  private function relatedCounts(array $projectIds): array {
    $result = [];
    foreach ($projectIds as $projectId) {
      $result[$projectId] = ['brebo_project_scope' => 0, 'brebo_action' => 0, 'brebo_risk' => 0, 'brebo_signal' => 0];
    }
    if (!$projectIds) {
      return $result;
    }
    $storage = $this->entityTypeManager()->getStorage('node');
    foreach (array_keys($result[reset($projectIds)]) as $type) {
      $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', $type)->condition('field_brebo_project_ref', $projectIds, 'IN')->execute();
      foreach ($storage->loadMultiple($ids) as $entity) {
        if (!$entity instanceof NodeInterface || !$entity->hasField('field_brebo_project_ref') || $entity->get('field_brebo_project_ref')->isEmpty()) {
          continue;
        }
        $projectId = (int) $entity->get('field_brebo_project_ref')->target_id;
        if (isset($result[$projectId][$type])) {
          $result[$projectId][$type]++;
        }
      }
    }
    return $result;
  }

  private function planningSummary(array $projectIds): array {
    $result = [];
    foreach ($projectIds as $projectId) {
      $result[$projectId] = ['start' => NULL, 'end' => NULL, 'total' => 0, 'done' => 0, 'late' => 0, 'blocked' => 0, 'critical' => 0, 'progress' => 0, 'signal' => 'green'];
    }
    if (!$projectIds) {
      return $result;
    }
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'brebo_plan_activity')->condition('field_brebo_project_ref', $projectIds, 'IN')->execute();
    $today = new \DateTimeImmutable('today');
    foreach ($storage->loadMultiple($ids) as $activity) {
      if (!$activity instanceof NodeInterface || $activity->get('field_brebo_project_ref')->isEmpty()) {
        continue;
      }
      $projectId = (int) $activity->get('field_brebo_project_ref')->target_id;
      $row = &$result[$projectId];
      $row['total']++;
      $status = $this->value($activity, 'field_brebo_plan_status');
      $start = $this->rawDate($activity, 'field_brebo_plan_start');
      $end = $this->rawDate($activity, 'field_brebo_plan_end');
      if ($status === 'Gereed') {
        $row['done']++;
      }
      if ($status === 'Geblokkeerd') {
        $row['blocked']++;
      }
      if ($this->boolValue($activity, 'field_brebo_plan_critical')) {
        $row['critical']++;
      }
      if ($end && $status !== 'Gereed' && new \DateTimeImmutable($end) < $today) {
        $row['late']++;
      }
      if ($start && (!$row['start'] || $start < $row['start'])) {
        $row['start'] = $start;
      }
      if ($end && (!$row['end'] || $end > $row['end'])) {
        $row['end'] = $end;
      }
      unset($row);
    }
    foreach ($result as &$row) {
      $row['progress'] = $row['total'] > 0 ? (int) round(($row['done'] / $row['total']) * 100) : 0;
      $row['signal'] = $row['blocked'] > 0 ? 'red' : ($row['late'] > 0 ? 'orange' : 'green');
    }
    unset($row);
    return $result;
  }

  private function sortItems(array &$items, string $sort): void {
    $signalWeight = ['red' => 0, 'orange' => 1, 'green' => 2];
    usort($items, static function (array $a, array $b) use ($sort, $signalWeight): int {
      return match ($sort) {
        'name_asc' => strcasecmp((string) $a['project']->label(), (string) $b['project']->label()),
        'code_asc' => strcasecmp((string) ($a['project']->get('field_brebo_project_code')->value ?? ''), (string) ($b['project']->get('field_brebo_project_code')->value ?? '')),
        'start_asc' => strcmp((string) ($a['plan']['start'] ?? '9999-12-31'), (string) ($b['plan']['start'] ?? '9999-12-31')),
        'end_asc' => strcmp((string) ($a['plan']['end'] ?? '9999-12-31'), (string) ($b['plan']['end'] ?? '9999-12-31')),
        'progress_desc' => $b['plan']['progress'] <=> $a['plan']['progress'],
        'attention' => ($signalWeight[$a['plan']['signal']] ?? 9) <=> ($signalWeight[$b['plan']['signal']] ?? 9),
        default => $b['changed'] <=> $a['changed'],
      };
    });
  }

  private function kpi(string $value, string $label): array {
    return ['#type' => 'container', '#attributes' => ['class' => ['brebo-project-kpi']], 'value' => ['#markup' => '<strong>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</strong>'], 'label' => ['#markup' => '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>']];
  }

  private function viewLink(string $label, string $target, string $current, array $query): array {
    $query['view'] = $target;
    return ['#type' => 'link', '#title' => $this->t($label), '#url' => Url::fromRoute('brebo_office_core.projects', [], ['query' => $query]), '#attributes' => ['class' => ['button', $target === $current ? 'is-active' : '']]];
  }

  private function value(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '—';
    }
    return trim((string) $node->get($field)->value) ?: '—';
  }

  private function boolValue(NodeInterface $node, string $field): bool {
    return $node->hasField($field) && !$node->get($field)->isEmpty() && (bool) $node->get($field)->value;
  }

  private function rawDate(NodeInterface $node, string $field): ?string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return NULL;
    }
    return substr((string) $node->get($field)->value, 0, 10);
  }

  private function dateLabel(?string $value): string {
    return $value ? (new \DateTimeImmutable($value))->format('d-m-Y') : '—';
  }

  private function signalLabel(string $signal): string {
    return match ($signal) {'red' => 'Rood', 'orange' => 'Oranje', default => 'Groen'};
  }

}
