<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Project workforce workspace for planning, presence and exceptions.
 */
final class ProjectInzetHubController extends ControllerBase {

  public function title(NodeInterface $node): string {
    $this->assertProject($node);
    return 'Inzet · ' . $node->label();
  }

  /** @return array<string, mixed> */
  public function overview(NodeInterface $node): array {
    $this->assertProject($node);
    $projectId = (int) $node->id();
    $storage = $this->entityTypeManager()->getStorage('node');
    $today = (new DrupalDateTime('now'))->format('Y-m-d');

    $team = $node->hasField('field_brebo_project_team') && !$node->get('field_brebo_project_team')->isEmpty()
      ? $node->get('field_brebo_project_team')->referencedEntities()
      : [];

    $assignmentIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_personnel_assignment')
      ->condition('field_brebo_project_ref', $projectId)
      ->condition('field_brebo_plan_date', $today)
      ->condition('field_brebo_assignment_status', 'cancelled', '<>')
      ->sort('field_brebo_assignment_start', 'ASC')
      ->execute();
    $assignments = $assignmentIds ? $storage->loadMultiple($assignmentIds) : [];

    $activeIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_clock_registration')
      ->condition('field_brebo_project_ref', $projectId)
      ->condition('field_brebo_clock_status', 'Open')
      ->execute();

    $deviationIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_clock_registration')
      ->condition('field_brebo_project_ref', $projectId)
      ->condition('field_brebo_clock_severity', ['oranje', 'rood'], 'IN')
      ->execute();

    $plannedRows = [];
    foreach ($assignments as $assignment) {
      if (!$assignment instanceof NodeInterface) {
        continue;
      }
      $person = $assignment->get('field_brebo_plan_user')->entity;
      $status = (string) ($assignment->get('field_brebo_assignment_status')->value ?? 'planned');
      $plannedRows[] = [
        $person ? $person->label() : $this->t('Onbekende medewerker'),
        trim(
          (string) ($assignment->get('field_brebo_assignment_start')->value ?? '') .
          ' - ' .
          (string) ($assignment->get('field_brebo_assignment_end')->value ?? ''),
          ' -'
        ) ?: '-',
        number_format((float) ($assignment->get('field_brebo_planned_hours')->value ?? 0), 2, ',', '.') . ' u',
        $status === 'confirmed' ? $this->t('Bevestigd') : $this->t('Gepland'),
      ];
    }

    $teamCount = count($team);
    $plannedCount = count($plannedRows);
    $activeCount = count($activeIds);
    $deviationCount = count($deviationIds);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-inzet-workspace']],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => [
          'node:' . $projectId,
          'node_list:brebo_personnel_assignment',
          'node_list:brebo_clock_registration',
        ],
        'max-age' => 60,
      ],
      'header' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-page-header']],
        'main' => [
          '#markup' => '<div class="brebo-page-header__main"><p class="brebo-page-header__eyebrow">BREBO INZET</p><h1 class="brebo-page-header__title">' .
            $this->t('Inzet vandaag') .
            '</h1><p class="brebo-page-header__description">' .
            $this->t('Stuur het projectteam, de planning en de werkelijke aanwezigheid vanuit één scherm. Projectteam bepaalt wie bij het project hoort; dagplanning bepaalt wie vandaag wordt verwacht.') .
            '</p></div>',
        ],
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-action-bar']],
        'hours' => Link::fromTextAndUrl(
          $this->t('Uren controleren'),
          Url::fromRoute('brebo_inzet.project_hours_control', ['node' => $projectId])
        )->toRenderable(),
        'planning' => Link::fromTextAndUrl(
          $this->t('Planning'),
          Url::fromRoute('brebo_inzet.project_planning', ['node' => $projectId])
        )->toRenderable(),
        'clock' => Link::fromTextAndUrl(
          $this->t('Klokken'),
          Url::fromRoute('brebo_inzet.mobile_clock', ['node' => $projectId])
        )->toRenderable(),
        'deviations' => Link::fromTextAndUrl(
          $this->t('Afwijkingen bekijken'),
          Url::fromRoute('brebo_inzet.project_clock_deviations', ['node' => $projectId])
        )->toRenderable(),
      ],
      'kpis' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-kpis']],
        'team' => [
          '#markup' => '<div class="brebo-kpi ' . ($teamCount > 0 ? 'brebo-kpi--positive' : 'brebo-kpi--attention') . '"><span class="brebo-kpi__value">' . $teamCount . '</span><span class="brebo-kpi__label">Projectteam</span></div>',
        ],
        'planned' => [
          '#markup' => '<div class="brebo-kpi brebo-kpi--neutral"><span class="brebo-kpi__value">' . $plannedCount . '</span><span class="brebo-kpi__label">Vandaag gepland</span></div>',
        ],
        'active' => [
          '#markup' => '<div class="brebo-kpi ' . ($activeCount > 0 ? 'brebo-kpi--positive' : 'brebo-kpi--neutral') . '"><span class="brebo-kpi__value">' . $activeCount . '</span><span class="brebo-kpi__label">Nu aanwezig</span></div>',
        ],
        'deviations' => [
          '#markup' => '<div class="brebo-kpi ' . ($deviationCount > 0 ? 'brebo-kpi--attention' : 'brebo-kpi--positive') . '"><span class="brebo-kpi__value">' . $deviationCount . '</span><span class="brebo-kpi__label">Afwijkingen</span></div>',
        ],
      ],
      'today' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-section']],
        'heading' => [
          '#markup' => '<div class="brebo-section-heading"><div><p class="brebo-page-header__eyebrow">VANDAAG</p><h2>Geplande bezetting</h2></div></div>',
        ],
        'table' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Medewerker'),
            $this->t('Tijd'),
            $this->t('Uren'),
            $this->t('Status'),
          ],
          '#rows' => $plannedRows,
          '#empty' => $this->t('Voor vandaag is nog niemand ingepland. Het projectteam kan wel al aan dit project gekoppeld zijn.'),
        ],
      ],
    ];

    $build['actions']['hours']['#attributes']['class'] = ['button'];
    $build['actions']['planning']['#attributes']['class'] = ['button', 'button--primary', 'brebo-button--primary'];
    $build['actions']['clock']['#attributes']['class'] = ['button'];
    $build['actions']['deviations']['#attributes']['class'] = ['button'];

    return $build;
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
  }

}
