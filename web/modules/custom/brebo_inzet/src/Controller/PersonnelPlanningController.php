<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\brebo_inzet\Service\PersonnelAssignmentComparison;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the BREBO personnel planning overview.
 */
final class PersonnelPlanningController extends ControllerBase {

  public function __construct(
    private readonly PersonnelAssignmentComparison $comparison,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_inzet.personnel_assignment_comparison'));
  }

  /**
   * Lists planned personnel assignments with actual clock comparison.
   */
  public function overview(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_personnel_assignment')
      ->sort('field_brebo_plan_date', 'ASC')
      ->sort('field_brebo_assignment_start', 'ASC')
      ->pager(50)
      ->execute();

    $stateLabels = [
      'future' => $this->t('Toekomstig'),
      'active' => $this->t('Nu actief'),
      'today_pending' => $this->t('Vandaag nog niet geklokt'),
      'unclocked' => $this->t('Niet geklokt'),
      'clocked_without_plan' => $this->t('Wel geklokt, geen urennorm'),
      'match' => $this->t('Volgens planning'),
      'under' => $this->t('Minder dan gepland'),
      'over' => $this->t('Meer dan gepland'),
      'incomplete' => $this->t('Planning onvolledig'),
    ];
    $statusLabels = [
      'planned' => $this->t('Gepland'),
      'confirmed' => $this->t('Bevestigd'),
      'cancelled' => $this->t('Geannuleerd'),
    ];

    $rows = [];
    foreach ($storage->loadMultiple($ids) as $assignment) {
      if (!$assignment instanceof NodeInterface || !$assignment->access('view')) {
        continue;
      }

      $project = $assignment->get('field_brebo_project_ref')->entity;
      $person = $assignment->get('field_brebo_plan_user')->entity;
      $statusItem = $assignment->get('field_brebo_assignment_status')->first();
      $status = $statusItem ? (string) $statusItem->getString() : '';
      $actual = $this->comparison->compare($assignment);
      $delta = (float) $actual['delta_hours'];
      $deltaLabel = ($delta > 0 ? '+' : '') . number_format($delta, 2, ',', '.') . ' u';

      $rows[] = [
        Link::fromTextAndUrl(
          $assignment->label(),
          Url::fromRoute('entity.node.canonical', ['node' => $assignment->id()]),
        )->toRenderable(),
        $person ? $person->label() : '-',
        $project ? $project->label() : '-',
        (string) ($assignment->get('field_brebo_plan_date')->value ?? ''),
        trim((string) ($assignment->get('field_brebo_assignment_start')->value ?? '') . ' - ' . (string) ($assignment->get('field_brebo_assignment_end')->value ?? ''), ' -'),
        number_format((float) $actual['planned_hours'], 2, ',', '.') . ' u',
        number_format((float) $actual['clocked_hours'], 2, ',', '.') . ' u',
        $deltaLabel,
        $stateLabels[(string) $actual['state']] ?? (string) $actual['state'],
        $statusLabels[$status] ?? $status,
      ];
    }

    $build = [
      '#cache' => [
        'contexts' => ['user', 'url.query_args:pagers'],
        'tags' => ['node_list:brebo_personnel_assignment', 'node_list:brebo_clock_registration'],
        'max-age' => 60,
      ],
      'header' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-page-header']],
        'main' => [
          '#markup' => '<div class="brebo-page-header__main"><p class="brebo-page-header__eyebrow">BREBO INZET</p><h1 class="brebo-page-header__title">Personeelsplanning</h1><p class="brebo-page-header__description">Geplande inzet naast werkelijk geklokte uren. Afwijkingen worden automatisch zichtbaar zonder een tweede urenregistratie.</p></div>',
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
          $this->t('Gepland'),
          $this->t('Geklokt'),
          $this->t('Verschil'),
          $this->t('Controle'),
          $this->t('Status'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Er is nog geen personeelsinzet gepland.'),
      ],
      'pager' => ['#type' => 'pager'],
    ];

    $createUrl = Url::fromRoute('node.add', ['node_type' => 'brebo_personnel_assignment']);
    if ($createUrl->access($this->currentUser())) {
      $build['actions']['add'] = Link::fromTextAndUrl($this->t('Personeelsinzet toevoegen'), $createUrl)->toRenderable();
      $build['actions']['add']['#attributes']['class'] = ['button', 'brebo-button--primary'];
    }

    return $build;
  }

}
