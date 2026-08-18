<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Provides the operational project portfolio.
 */
final class ProjectPortfolioController extends ControllerBase {

  /**
   * Builds a steering-ready overview of all accessible projects.
   */
  public function overview(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_project')
      ->sort('changed', 'DESC')
      ->pager(25)
      ->execute();

    $projects = $storage->loadMultiple($ids);
    $status_counts = [];
    $rows = [];

    foreach ($projects as $project) {
      if (!$project instanceof NodeInterface) {
        continue;
      }

      $status = $this->value($project, 'field_brebo_status');
      $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
      $buildings = $project->hasField('field_brebo_building_refs')
        ? $project->get('field_brebo_building_refs')->referencedEntities()
        : [];
      $building_links = [];
      foreach ($buildings as $building) {
        if ($building instanceof NodeInterface) {
          $building_links[] = Link::fromTextAndUrl(
            $building->label(),
            Url::fromRoute('brebo_office_core.building_dashboard', ['node' => $building->id()])
          )->toString();
        }
      }

      $project_id = (int) $project->id();
      $scope_count = $this->relatedCount('brebo_project_scope', $project_id);
      $action_count = $this->relatedCount('brebo_action', $project_id);
      $risk_count = $this->relatedCount('brebo_risk', $project_id);
      $signal_count = $this->relatedCount('brebo_signal', $project_id);
      $attention = $action_count + $risk_count + $signal_count;

      $rows[] = [
        ['data' => Link::fromTextAndUrl(
          $project->label(),
          Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $project_id])
        )->toRenderable()],
        $this->value($project, 'field_brebo_project_code'),
        $this->value($project, 'field_brebo_client'),
        ['data' => $building_links ? ['#markup' => implode('<br>', $building_links)] : '—'],
        $this->value($project, 'field_brebo_project_kind'),
        $status,
        $scope_count,
        $attention,
        $this->dateFormatter()->format($project->getChangedTime(), 'short'),
        ['data' => [
          '#type' => 'operations',
          '#links' => [
            'open' => [
              'title' => $this->t('Open project'),
              'url' => Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $project_id]),
            ],
            'edit' => [
              'title' => $this->t('Bewerken'),
              'url' => Url::fromRoute('entity.node.edit_form', ['node' => $project_id]),
            ],
            'documents' => [
              'title' => $this->t('Documenten'),
              'url' => Url::fromRoute('brebo_document_data.node_dossier', ['node' => $project_id]),
            ],
          ],
        ]],
      ];
    }

    ksort($status_counts, SORT_NATURAL | SORT_FLAG_CASE);
    $status_items = [];
    foreach ($status_counts as $status => $count) {
      $status_items[] = $this->t('@status: @count', ['@status' => $status, '@count' => $count]);
    }

    return [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'add' => [
          '#type' => 'link',
          '#title' => $this->t('Nieuw project'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_project']),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
      ],
      'summary' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-project-signals']],
        'total' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('<strong>@count projecten zichtbaar</strong>', ['@count' => count($rows)]),
        ],
        'statuses' => [
          '#theme' => 'item_list',
          '#items' => $status_items,
          '#empty' => $this->t('Nog geen projecten.'),
        ],
      ],
      'projects' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Project'),
          $this->t('Code'),
          $this->t('Opdrachtgever'),
          $this->t('Gebouw'),
          $this->t('Soort'),
          $this->t('Status'),
          $this->t('Scopes'),
          $this->t('Aandacht'),
          $this->t('Gewijzigd'),
          $this->t('Acties'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nog geen projecten aangemaakt.'),
        '#sticky' => TRUE,
      ],
      'pager' => ['#type' => 'pager'],
      '#cache' => [
        'contexts' => ['user.permissions', 'url.query_args:pagers'],
        'tags' => [
          'node_list:brebo_project',
          'node_list:brebo_project_scope',
          'node_list:brebo_action',
          'node_list:brebo_risk',
          'node_list:brebo_signal',
        ],
      ],
    ];
  }

  /**
   * Counts accessible records linked directly to a project.
   */
  private function relatedCount(string $bundle, int $projectId): int {
    return (int) $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('field_brebo_project_ref.target_id', $projectId)
      ->count()
      ->execute();
  }

  /**
   * Returns a display-safe scalar field value.
   */
  private function value(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '—';
    }
    return (string) ($node->get($field)->value ?? '—');
  }

}
