<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/** Provides the operational project portfolio. */
final class ProjectPortfolioController extends ControllerBase {

  /** Builds a steering-ready overview of all accessible projects. */
  public function overview(Request $request): array {
    $view = $request->query->get('view') === 'kanban' ? 'kanban' : 'list';
    $storage = $this->entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_project')
      ->sort('changed', 'DESC');
    if ($view === 'list') {
      $query->pager(25);
    }
    $projects = $storage->loadMultiple($query->execute());
    $projectIds = array_map('intval', array_keys($projects));
    $relatedCounts = $this->relatedCounts($projectIds);

    $status_counts = [];
    $rows = [];
    $kanban = [];

    foreach ($projects as $project) {
      if (!$project instanceof NodeInterface) continue;
      $status = $this->value($project, 'field_brebo_status');
      if ($status === '—' || $status === '') $status = (string) $this->t('Geen status');
      $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
      $buildings = $project->hasField('field_brebo_building_refs') ? $project->get('field_brebo_building_refs')->referencedEntities() : [];
      $building_links = [];
      $building_labels = [];
      foreach ($buildings as $building) {
        if ($building instanceof NodeInterface) {
          $building_links[] = Link::fromTextAndUrl($building->label(), Url::fromRoute('brebo_office_core.building_dashboard', ['node' => $building->id()]))->toString();
          $building_labels[] = $building->label();
        }
      }

      $project_id = (int) $project->id();
      $counts = $relatedCounts[$project_id] ?? ['brebo_project_scope' => 0, 'brebo_action' => 0, 'brebo_risk' => 0, 'brebo_signal' => 0];
      $scope_count = $counts['brebo_project_scope'];
      $dossier_count = $counts['brebo_action'] + $counts['brebo_risk'] + $counts['brebo_signal'];
      $project_url = Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $project_id]);
      $planning_url = Url::fromRoute('brebo_office_core.project_planning', ['node' => $project_id]);
      $changed = \Drupal::service('date.formatter')->format($project->getChangedTime(), 'short');

      $rows[] = [
        ['data' => Link::fromTextAndUrl($project->label(), $project_url)->toRenderable()],
        $this->value($project, 'field_brebo_project_code'), $this->value($project, 'field_brebo_client'),
        ['data' => $building_links ? ['#markup' => implode('<br>', $building_links)] : '—'],
        $this->value($project, 'field_brebo_project_kind'), $status, $scope_count, $dossier_count, $changed,
        ['data' => ['#type' => 'operations', '#links' => [
          'open' => ['title' => $this->t('Open project'), 'url' => $project_url],
          'planning' => ['title' => $this->t('Planning'), 'url' => $planning_url],
          'edit' => ['title' => $this->t('Bewerken'), 'url' => Url::fromRoute('entity.node.edit_form', ['node' => $project_id])],
          'documents' => ['title' => $this->t('Documenten'), 'url' => Url::fromRoute('brebo_document_data.node_dossier', ['node' => $project_id])],
        ]]],
      ];

      $kanban[$status][] = [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-status-kanban__card']],
        'title' => ['#type' => 'link', '#title' => $project->label(), '#url' => $project_url, '#attributes' => ['class' => ['brebo-status-kanban__title']]],
        'meta' => ['#markup' => '<div class="brebo-status-kanban__meta"><span>' . htmlspecialchars($this->value($project, 'field_brebo_project_code'), ENT_QUOTES, 'UTF-8') . '</span><span>' . htmlspecialchars($this->value($project, 'field_brebo_client'), ENT_QUOTES, 'UTF-8') . '</span>' . ($building_labels ? '<span>' . htmlspecialchars(implode(', ', $building_labels), ENT_QUOTES, 'UTF-8') . '</span>' : '') . '<span>' . $this->t('@scopes scopes · @dossier acties/risico’s/signalen', ['@scopes' => $scope_count, '@dossier' => $dossier_count]) . '</span><span>' . $this->t('Gewijzigd: @date', ['@date' => $changed]) . '</span></div>'],
        'actions' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-kanban__actions']],
          'open' => ['#type' => 'link', '#title' => $this->t('Openen'), '#url' => $project_url],
          'planning' => ['#type' => 'link', '#title' => $this->t('Planning'), '#url' => $planning_url],
          'documents' => ['#type' => 'link', '#title' => $this->t('Documenten'), '#url' => Url::fromRoute('brebo_document_data.node_dossier', ['node' => $project_id])],
        ],
      ];
    }

    ksort($status_counts, SORT_NATURAL | SORT_FLAG_CASE);
    ksort($kanban, SORT_NATURAL | SORT_FLAG_CASE);
    $status_items = [];
    foreach ($status_counts as $status => $count) $status_items[] = $this->t('@status: @count', ['@status' => $status, '@count' => $count]);

    $build = [
      '#type' => 'container', '#attributes' => ['class' => ['brebo-status-overview']], '#attached' => ['library' => ['brebo_office_core/status-list-kanban']],
      'actions' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-overview__actions']],
        'add' => ['#type' => 'link', '#title' => $this->t('Nieuw project'), '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_project']), '#attributes' => ['class' => ['button', 'button--primary']]],
        'switch' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-overview__switch'], 'aria-label' => $this->t('Weergave')],
          'list' => ['#type' => 'link', '#title' => $this->t('Lijst'), '#url' => Url::fromRoute('brebo_office_core.projects', [], ['query' => ['view' => 'list']]), '#attributes' => ['class' => $view === 'list' ? ['is-active'] : []]],
          'kanban' => ['#type' => 'link', '#title' => $this->t('Kanban'), '#url' => Url::fromRoute('brebo_office_core.projects', [], ['query' => ['view' => 'kanban']]), '#attributes' => ['class' => $view === 'kanban' ? ['is-active'] : []]],
        ],
      ],
      'summary' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-project-signals']],
        'total' => ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('<strong>@count projecten zichtbaar</strong>', ['@count' => count($rows)])],
        'statuses' => ['#theme' => 'item_list', '#items' => $status_items, '#empty' => $this->t('Nog geen projecten.')],
      ],
    ];
    if ($view === 'list') {
      $build['projects'] = ['#type' => 'table', '#header' => [$this->t('Project'), $this->t('Code'), $this->t('Opdrachtgever'), $this->t('Gebouw'), $this->t('Soort'), $this->t('Status'), $this->t('Scopes'), $this->t('Acties/risico’s/signalen'), $this->t('Gewijzigd'), $this->t('Acties')], '#rows' => $rows, '#empty' => $this->t('Nog geen projecten aangemaakt.'), '#sticky' => TRUE];
      $build['pager'] = ['#type' => 'pager'];
    }
    else {
      $board = ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-kanban']]];
      foreach ($kanban as $status => $cards) {
        $board['status_' . md5($status)] = ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-kanban__column']],
          'header' => ['#markup' => '<div class="brebo-status-kanban__header"><span>' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span><span class="brebo-status-kanban__count">' . count($cards) . '</span></div>'],
          'cards' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-kanban__cards']]] + $cards,
        ];
      }
      $build['projects'] = $board;
    }
    $build['#cache'] = ['contexts' => ['user.permissions', 'url.query_args:view', 'url.query_args:pagers'], 'tags' => ['node_list:brebo_project', 'node_list:brebo_project_scope', 'node_list:brebo_action', 'node_list:brebo_risk', 'node_list:brebo_signal']];
    return $build;
  }

  /** Returns related object counts for all visible projects in four aggregate queries. */
  private function relatedCounts(array $projectIds): array {
    $counts = [];
    if (!$projectIds) return $counts;
    foreach (['brebo_project_scope', 'brebo_action', 'brebo_risk', 'brebo_signal'] as $bundle) {
      $query = $this->entityTypeManager()->getStorage('node')->getQuery()
        ->accessCheck(TRUE)->condition('type', $bundle)->condition('field_brebo_project_ref.target_id', $projectIds, 'IN');
      $ids = $query->execute();
      if (!$ids) continue;
      $entities = $this->entityTypeManager()->getStorage('node')->loadMultiple($ids);
      foreach ($entities as $entity) {
        if (!$entity instanceof NodeInterface || !$entity->hasField('field_brebo_project_ref')) continue;
        $projectId = (int) $entity->get('field_brebo_project_ref')->target_id;
        if ($projectId > 0) $counts[$projectId][$bundle] = ($counts[$projectId][$bundle] ?? 0) + 1;
      }
    }
    return $counts;
  }

  private function value(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) return '—';
    return (string) ($node->get($field)->value ?? '—');
  }

}
