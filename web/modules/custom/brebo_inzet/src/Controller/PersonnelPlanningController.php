<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Provides the BREBO personnel planning overview.
 */
final class PersonnelPlanningController extends ControllerBase {

  /**
   * Lists upcoming and recent planned personnel assignments.
   */
  public function overview(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_personnel_assignment')
      ->sort('field_brebo_plan_date', 'ASC')
      ->sort('field_brebo_plan_start', 'ASC')
      ->range(0, 100)
      ->execute();

    $rows = [];
    foreach ($storage->loadMultiple($ids) as $assignment) {
      if (!$assignment instanceof NodeInterface || !$assignment->access('view')) {
        continue;
      }

      $project = $assignment->get('field_brebo_project_ref')->entity;
      $person = $assignment->get('field_brebo_plan_user')->entity;
      $status_item = $assignment->get('field_brebo_plan_status')->first();
      $status = $status_item ? (string) $status_item->getString() : '';
      $status_labels = [
        'planned' => $this->t('Gepland'),
        'confirmed' => $this->t('Bevestigd'),
        'cancelled' => $this->t('Geannuleerd'),
      ];

      $rows[] = [
        Link::fromTextAndUrl(
          $assignment->label(),
          Url::fromRoute('entity.node.canonical', ['node' => $assignment->id()]),
        )->toRenderable(),
        $person ? $person->label() : '-',
        $project ? $project->label() : '-',
        (string) ($assignment->get('field_brebo_plan_date')->value ?? ''),
        trim((string) ($assignment->get('field_brebo_plan_start')->value ?? '') . ' - ' . (string) ($assignment->get('field_brebo_plan_end')->value ?? ''), ' -'),
        (string) ($assignment->get('field_brebo_planned_hours')->value ?? ''),
        $status_labels[$status] ?? $status,
      ];
    }

    $build = [
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['node_list:brebo_personnel_assignment'],
      ],
      'header' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-page-header']],
        'main' => [
          '#markup' => '<div class="brebo-page-header__main"><p class="brebo-page-header__eyebrow">BREBO INZET</p><h1 class="brebo-page-header__title">Personeelsplanning</h1><p class="brebo-page-header__description">Geplande inzet per persoon, project en datum. Werkelijke aanwezigheid blijft afkomstig uit de klokregistraties.</p></div>',
        ],
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-action-bar']],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Inzet'),
          $this->t('Persoon'),
          $this->t('Project'),
          $this->t('Datum'),
          $this->t('Tijd'),
          $this->t('Uren'),
          $this->t('Status'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Er is nog geen personeelsinzet gepland.'),
      ],
    ];

    $create_url = Url::fromRoute('node.add', ['node_type' => 'brebo_personnel_assignment']);
    if ($create_url->access($this->currentUser())) {
      $build['actions']['add'] = Link::fromTextAndUrl($this->t('Personeelsinzet toevoegen'), $create_url)->toRenderable();
      $build['actions']['add']['#attributes']['class'] = ['button', 'brebo-button--primary'];
    }

    return $build;
  }

}
