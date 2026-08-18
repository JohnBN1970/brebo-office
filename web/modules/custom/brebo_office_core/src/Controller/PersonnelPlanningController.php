<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\brebo_office_core\Service\PersonnelBudgetControl;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides the operational personnel planning cockpit.
 */
final class PersonnelPlanningController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PersonnelBudgetControl $budgetControl,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('brebo_office_core.personnel_budget_control'),
    );
  }

  public function title(?NodeInterface $node = NULL): string {
    if ($node instanceof NodeInterface) {
      $this->assertProject($node);
      return (string) $this->t('BREBO Inzet — @project', ['@project' => $node->label()]);
    }
    return (string) $this->t('BREBO Inzet');
  }

  public function overview(?NodeInterface $node = NULL): array {
    if ($node instanceof NodeInterface) {
      $this->assertProject($node);
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_staff_assignment')
      ->sort('field_brebo_staff_start', 'ASC');
    if ($node instanceof NodeInterface) {
      $query->condition('field_brebo_staff_project.target_id', $node->id());
    }

    $rows = [];
    $planned_hours = 0.0;
    $actual_hours = 0.0;
    $conflicts = 0;
    $confirmed = 0;
    $assignment_input = [];
    $budget_line_ids = [];
    foreach ($storage->loadMultiple($query->execute()) as $assignment) {
      if (!$assignment instanceof NodeInterface) {
        continue;
      }
      $planned = (float) ($assignment->get('field_brebo_staff_plan_hours')->value ?? 0);
      $actual = (float) ($assignment->get('field_brebo_staff_actual_hours')->value ?? 0);
      $conflict = (string) ($assignment->get('field_brebo_staff_conflict')->value ?? 'Geen');
      $status = (string) ($assignment->get('field_brebo_staff_status')->value ?? '—');
      $planned_hours += $planned;
      $actual_hours += $actual;
      $conflicts += $conflict !== 'Geen' ? 1 : 0;
      $confirmed += in_array($status, ['Bevestigd', 'Gestart', 'Afgerond'], TRUE) ? 1 : 0;

      $person = $assignment->get('field_brebo_staff_contact')->entity
        ?? $assignment->get('field_brebo_staff_user')->entity
        ?? $assignment->get('field_brebo_staff_team')->entity;
      $activity = $assignment->get('field_brebo_staff_activity')->entity;
      $project = $assignment->get('field_brebo_staff_project')->entity;
      $organization = $assignment->get('field_brebo_staff_org')->entity;
      $budget_line = $assignment->get('field_brebo_staff_finance_ref')->entity;
      $budget_line_id = $budget_line instanceof NodeInterface ? (int) $budget_line->id() : NULL;
      if ($budget_line_id !== NULL) {
        $budget_line_ids[$budget_line_id] = $budget_line_id;
      }
      $assignment_input[(int) $assignment->id()] = [
        'budget_line_id' => $budget_line_id,
        'planned_hours' => $planned,
        'actual_hours' => $actual,
        'label' => (string) $assignment->label(),
      ];
      $rows[] = [
        ['data' => Link::fromTextAndUrl($assignment->label(), $assignment->toUrl())->toRenderable()],
        $person ? $person->label() : $this->t('Niet toegewezen'),
        $organization instanceof NodeInterface ? $organization->label() : '—',
        $project instanceof NodeInterface ? $project->label() : '—',
        $activity instanceof NodeInterface ? $activity->label() : '—',
        (string) ($assignment->get('field_brebo_staff_role')->value ?? '—'),
        (string) ($assignment->get('field_brebo_staff_start')->value ?? '—'),
        (string) ($assignment->get('field_brebo_staff_end')->value ?? '—'),
        number_format($planned, 2, ',', '.'),
        number_format($actual, 2, ',', '.'),
        $status,
        $conflict,
      ];
    }

    $budget_input = [];
    foreach ($storage->loadMultiple($budget_line_ids) as $budget_line) {
      if (!$budget_line instanceof NodeInterface || $budget_line->bundle() !== 'brebo_work_budget_line') {
        continue;
      }
      $budget_input[(int) $budget_line->id()] = [
        'label' => (string) $budget_line->label(),
        'budget_hours' => (float) ($budget_line->get('field_brebo_budget_hours')->value ?? 0),
        'budget_actual_hours' => (float) ($budget_line->get('field_brebo_actual_hours')->value ?? 0),
      ];
    }
    $budget_result = $this->budgetControl->analyze($budget_input, $assignment_input);
    $budget_rows = [];
    foreach ($budget_result['lines'] as $line) {
      $budget_rows[] = [
        $line['label'] ?? '—',
        number_format((float) $line['budget_hours'], 2, ',', '.'),
        number_format((float) $line['allocated_hours'], 2, ',', '.'),
        number_format((float) $line['assignment_actual_hours'], 2, ',', '.'),
        number_format((float) $line['remaining_to_allocate'], 2, ',', '.'),
        number_format((float) $line['allocation_percent'], 1, ',', '.') . '%',
        $line['control_status'],
        $line['control_message'],
      ];
    }
    foreach ($budget_result['unlinked'] as $unlinked) {
      $budget_rows[] = [
        $unlinked['label'] ?? $this->t('Ongekoppelde inzet'),
        '—',
        number_format((float) ($unlinked['planned_hours'] ?? 0), 2, ',', '.'),
        number_format((float) ($unlinked['actual_hours'] ?? 0), 2, ',', '.'),
        '—', '—',
        $unlinked['control_status'],
        $unlinked['control_message'],
      ];
    }

    $availability_query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_availability')
      ->condition('field_brebo_avail_end', date('Y-m-d'), '>=')
      ->sort('field_brebo_avail_start', 'ASC')
      ->range(0, 25);
    $availability_rows = [];
    foreach ($storage->loadMultiple($availability_query->execute()) as $availability) {
      if (!$availability instanceof NodeInterface) {
        continue;
      }
      $resource = $availability->get('field_brebo_avail_contact')->entity
        ?? $availability->get('field_brebo_avail_user')->entity
        ?? $availability->get('field_brebo_avail_team')->entity;
      $availability_rows[] = [
        $resource ? $resource->label() : $this->t('Niet toegewezen'),
        (string) ($availability->get('field_brebo_avail_start')->value ?? '—'),
        (string) ($availability->get('field_brebo_avail_end')->value ?? '—'),
        (string) ($availability->get('field_brebo_avail_hours_day')->value ?? '—'),
        (string) ($availability->get('field_brebo_avail_type')->value ?? '—'),
        (string) ($availability->get('field_brebo_avail_status')->value ?? '—'),
      ];
    }

    $team_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_staff_team')
      ->condition('field_brebo_team_active', 1)
      ->sort('title', 'ASC')
      ->execute();
    $team_rows = [];
    foreach ($storage->loadMultiple($team_ids) as $team) {
      if (!$team instanceof NodeInterface) {
        continue;
      }
      $organization = $team->get('field_brebo_team_org')->entity;
      $lead = $team->get('field_brebo_team_lead')->entity;
      $team_rows[] = [
        ['data' => Link::fromTextAndUrl($team->label(), $team->toUrl())->toRenderable()],
        (string) ($team->get('field_brebo_team_code')->value ?? '—'),
        $organization instanceof NodeInterface ? $organization->label() : '—',
        $lead instanceof NodeInterface ? $lead->label() : '—',
        count($team->get('field_brebo_team_members')->referencedEntities()),
        (string) ($team->get('field_brebo_team_capacity')->value ?? '—'),
      ];
    }

    $build = [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'capacity' => [
          '#type' => 'link', '#title' => $this->t('Capaciteitsprognose'),
          '#url' => Url::fromRoute('brebo_office_core.inzet_capacity'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'schedule' => [
          '#type' => 'link', '#title' => $this->t('Weekrooster openen'),
          '#url' => Url::fromRoute('brebo_office_core.inzet_schedule'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'assignment' => [
          '#type' => 'link',
          '#title' => $this->t('Personeelsinzet toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_staff_assignment'], [
            'query' => $node instanceof NodeInterface ? ['project' => $node->id()] : [],
          ]),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'availability' => [
          '#type' => 'link', '#title' => $this->t('Beschikbaarheid toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_availability']),
          '#attributes' => ['class' => ['button']],
        ],
        'team' => [
          '#type' => 'link', '#title' => $this->t('Ploeg toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_staff_team']),
          '#attributes' => ['class' => ['button']],
        ],
        'shift' => [
          '#type' => 'link', '#title' => $this->t('Dienst toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_shift']),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'hours' => [
          '#type' => 'link', '#title' => $this->t('Uren registreren'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_time_entry']),
          '#attributes' => ['class' => ['button']],
        ],
        'skills' => [
          '#type' => 'link', '#title' => $this->t('Vakbekwaamheden'),
          '#url' => Url::fromRoute('brebo_office_core.inzet_skills'),
          '#attributes' => ['class' => ['button']],
        ],
        'qualifications' => [
          '#type' => 'link', '#title' => $this->t('Kwalificatiebewijzen'),
          '#url' => Url::fromRoute('brebo_office_core.inzet_qualifications'),
          '#attributes' => ['class' => ['button']],
        ],
        'leave' => [
          '#type' => 'link', '#title' => $this->t('Verlof aanvragen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_leave_request']),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'summary' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['brebo-calc-summary']],
        '#header' => [$this->t('Inzetten'), $this->t('Bevestigd'), $this->t('Geplande uren'), $this->t('Werkelijke uren'), $this->t('Conflicten')],
        '#rows' => [[count($rows), $confirmed, number_format($planned_hours, 2, ',', '.'), number_format($actual_hours, 2, ',', '.'), $conflicts]],
      ],
      'assignments_heading' => ['#markup' => '<h2>' . $this->t('Inzetplanning') . '</h2>'],
      'assignments' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Inzet'), $this->t('Persoon/ploeg'), $this->t('Organisatie'),
          $this->t('Project'), $this->t('Activiteit'), $this->t('Rol/vak'),
          $this->t('Start'), $this->t('Gereed'), $this->t('Gepland'),
          $this->t('Werkelijk'), $this->t('Status'), $this->t('Capaciteit'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nog geen personeelsinzet gepland.'),
        '#sticky' => TRUE,
      ],
      'budget_heading' => ['#markup' => '<h2>' . $this->t('Controle vanuit werkbegroting') . '</h2>'],
      'budget_control' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Werkbegrotingsregel'), $this->t('Begroot'), $this->t('Toegewezen'),
          $this->t('Werkelijk'), $this->t('Nog te verdelen'), $this->t('Dekking'),
          $this->t('Controle'), $this->t('Toelichting'),
        ],
        '#rows' => $budget_rows,
        '#empty' => $this->t('Nog geen personeelsinzet aan een werkbegrotingsregel gekoppeld.'),
        '#sticky' => TRUE,
      ],
      'availability_heading' => ['#markup' => '<h2>' . $this->t('Beschikbaarheid') . '</h2>'],
      'availability' => [
        '#type' => 'table',
        '#header' => [$this->t('Persoon/ploeg'), $this->t('Start'), $this->t('Einde'), $this->t('Uren/dag'), $this->t('Type'), $this->t('Status')],
        '#rows' => $availability_rows,
        '#empty' => $this->t('Geen actuele beschikbaarheidstijdvakken.'),
      ],
      'teams_heading' => ['#markup' => '<h2>' . $this->t('Actieve ploegen') . '</h2>'],
      'teams' => [
        '#type' => 'table',
        '#header' => [$this->t('Ploeg'), $this->t('Code'), $this->t('Organisatie'), $this->t('Voorman'), $this->t('Leden'), $this->t('Uren/dag')],
        '#rows' => $team_rows,
        '#empty' => $this->t('Nog geen actieve ploegen ingericht.'),
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['node_list:brebo_staff_assignment', 'node_list:brebo_availability', 'node_list:brebo_staff_team'],
        'max-age' => 300,
      ],
    ];
    if ($node instanceof NodeInterface) {
      $build['actions']['planning'] = [
        '#type' => 'link', '#title' => $this->t('Terug naar projectplanning'),
        '#url' => Url::fromRoute('brebo_office_core.project_planning', ['node' => $node->id()]),
        '#attributes' => ['class' => ['button']],
      ];
      $build['#cache']['tags'] = array_merge($build['#cache']['tags'], $node->getCacheTags());
    }
    return $build;
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
  }

}
