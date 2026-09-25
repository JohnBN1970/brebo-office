<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Form;

use Drupal\brebo_finance\Service\LabourProductivityManager;
use Drupal\brebo_inzet\Service\PersonnelFinanceSynchronizer;
use Drupal\brebo_inzet\Service\PersonnelLabourLineResolver;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Single project planning workbench with one independent row per employee.
 */
final class ProjectPlanningWorkbenchForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PersonnelLabourLineResolver $labourLineResolver,
    private readonly PersonnelFinanceSynchronizer $financeSynchronizer,
    private readonly LabourProductivityManager $labourProductivity,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('brebo_inzet.personnel_labour_line_resolver'),
      $container->get('brebo_inzet.personnel_finance_synchronizer'),
      $container->get('brebo_finance.labour_productivity_manager'),
    );
  }

  public function getFormId(): string {
    return 'brebo_inzet_project_planning_workbench';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $projectId = (int) $node->id();
    $team = $node->hasField('field_brebo_project_team')
      ? $node->get('field_brebo_project_team')->referencedEntities()
      : [];

    $form['header'] = [
      '#markup' => '<div class="brebo-page-header__main"><p class="brebo-page-header__eyebrow">BREBO INZET</p><h1>Planning</h1><p class="brebo-page-header__description">Plan iedere medewerker onafhankelijk. De planning is wat BREBO vooraf verwacht; OnSite registreert wat er werkelijk gebeurt.</p></div>',
    ];

    $teamLink = Link::fromTextAndUrl(
      $this->t('Projectteam wijzigen'),
      Url::fromRoute('brebo_inzet.project_team', ['node' => $projectId])
    )->toRenderable();
    $teamLink['#attributes']['class'] = ['button'];
    $form['team_action'] = $teamLink;

    if ($team === []) {
      $form['empty'] = [
        '#markup' => '<div class="messages messages--warning">Dit project heeft nog geen projectteam. Voeg eerst medewerkers toe.</div>',
      ];
      return $form;
    }

    $defaults = $this->existingPlanningDefaults($projectId);

    $form['rows'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [
        $this->t('Inzetten'),
        $this->t('Medewerker'),
        $this->t('Van'),
        $this->t('Tot en met'),
        $this->t('Vanaf'),
        $this->t('Tot'),
        $this->t('Bestaand'),
      ],
    ];

    foreach ($team as $account) {
      if (!$account instanceof UserInterface || !$account->isActive()) {
        continue;
      }
      $uid = (int) $account->id();
      $default = $defaults[$uid] ?? [
        'start_date' => (new DrupalDateTime('now'))->format('Y-m-d'),
        'end_date' => (new DrupalDateTime('now'))->format('Y-m-d'),
        'start_time' => '07:00',
        'end_time' => '16:00',
        'days' => 0,
        'hours' => 0.0,
      ];

      $form['rows'][$uid]['enabled'] = [
        '#type' => 'checkbox',
        '#default_value' => FALSE,
      ];
      $form['rows'][$uid]['name'] = [
        '#markup' => '<strong>' . htmlspecialchars($account->getDisplayName(), ENT_QUOTES, 'UTF-8') . '</strong>',
      ];
      $form['rows'][$uid]['start_date'] = [
        '#type' => 'date',
        '#default_value' => $default['start_date'],
      ];
      $form['rows'][$uid]['end_date'] = [
        '#type' => 'date',
        '#default_value' => $default['end_date'],
      ];
      $form['rows'][$uid]['start_time'] = [
        '#type' => 'time',
        '#default_value' => $default['start_time'],
      ];
      $form['rows'][$uid]['end_time'] = [
        '#type' => 'time',
        '#default_value' => $default['end_time'],
      ];
      $form['rows'][$uid]['existing'] = [
        '#markup' => $default['days'] > 0
          ? $this->t('@days dag(en) · @hours u', [
            '@days' => $default['days'],
            '@hours' => number_format((float) $default['hours'], 2, ',', '.'),
          ])
          : $this->t('Nog niet gepland'),
      ];
    }

    $form['explanation'] = [
      '#markup' => '<div class="messages messages--status"><strong>Ad-hoc hoef je hier niet vooraf te plannen.</strong> Als iemand onverwacht op een ander BREBO-gebouw/project werkt, registreert OnSite de werkelijke aanwezigheid. Office vergelijkt dat later met deze planning.</div>',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Planning opslaan'),
      '#button_type' => 'primary',
    ];

    $form_state->set('project_id', $projectId);
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    foreach ((array) $form_state->getValue('rows') as $uid => $row) {
      if (empty($row['enabled'])) {
        continue;
      }
      $startDate = (string) ($row['start_date'] ?? '');
      $endDate = (string) ($row['end_date'] ?? '');
      $startTime = (string) ($row['start_time'] ?? '');
      $endTime = (string) ($row['end_time'] ?? '');

      if ($startDate === '' || $endDate === '' || $startTime === '' || $endTime === '') {
        $form_state->setErrorByName('rows][' . $uid, $this->t('Vul voor iedere geselecteerde medewerker periode en werktijden volledig in.'));
        continue;
      }
      if ($endDate < $startDate) {
        $form_state->setErrorByName('rows][' . $uid . '][end_date', $this->t('Einddatum moet op of na de startdatum liggen.'));
      }
      if ($endTime <= $startTime) {
        $form_state->setErrorByName('rows][' . $uid . '][end_time', $this->t('Eindtijd moet na de begintijd liggen.'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    $project = $this->entityTypeManager->getStorage('node')->load($projectId);
    if (!$project instanceof NodeInterface || $project->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $userStorage = $this->entityTypeManager->getStorage('user');
    $hasLabourBudget = $this->labourProductivity->labourBudgetLines($projectId) !== [];

    $created = 0;
    $updated = 0;
    $conflicts = [];

    foreach ((array) $form_state->getValue('rows') as $uidValue => $row) {
      if (empty($row['enabled'])) {
        continue;
      }
      $uid = (int) $uidValue;
      $account = $userStorage->load($uid);
      if (!$account instanceof UserInterface || !$account->isActive()) {
        continue;
      }

      $startDate = (string) $row['start_date'];
      $endDate = (string) $row['end_date'];
      $startTime = (string) $row['start_time'];
      $endTime = (string) $row['end_time'];
      $hours = round(max(0, (strtotime('1970-01-01 ' . $endTime) - strtotime('1970-01-01 ' . $startTime)) / 3600), 2);

      foreach ($this->workdays($startDate, $endDate) as $date) {
        $other = $this->overlappingOtherProject($projectId, $uid, $date, $startTime, $endTime);
        if ($other !== NULL) {
          $conflicts[] = sprintf('%s · %s · al gepland op %s', $account->getDisplayName(), $date, $other);
          continue;
        }

        $existingIds = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition('type', 'brebo_personnel_assignment')
          ->condition('field_brebo_project_ref', $projectId)
          ->condition('field_brebo_plan_user', $uid)
          ->condition('field_brebo_plan_date', $date)
          ->condition('field_brebo_assignment_status', 'cancelled', '<>')
          ->range(0, 1)
          ->execute();

        $assignment = $existingIds !== []
          ? $storage->load((int) reset($existingIds))
          : NULL;

        $budgetLineId = 0;
        if ($hasLabourBudget) {
          try {
            $budgetLineId = (int) $this->labourLineResolver->resolve($projectId, $account)['id'];
          }
          catch (\Throwable) {
            $budgetLineId = 0;
          }
        }

        if (!$assignment instanceof NodeInterface) {
          $assignment = $storage->create([
            'type' => 'brebo_personnel_assignment',
            'title' => sprintf('%s - %s - %s', $project->label(), $account->getDisplayName(), $date),
            'status' => 1,
            'field_brebo_project_ref' => ['target_id' => $projectId],
            'field_brebo_plan_user' => ['target_id' => $uid],
            'field_brebo_plan_date' => $date,
            'field_brebo_assignment_status' => 'planned',
          ]);
          $created++;
        }
        else {
          $updated++;
        }

        $assignment->set('field_brebo_assignment_start', $startTime);
        $assignment->set('field_brebo_assignment_end', $endTime);
        $assignment->set('field_brebo_planned_hours', $hours);
        $assignment->set('field_brebo_budget_line_id', $budgetLineId > 0 ? $budgetLineId : NULL);
        $assignment->save();

        if ($budgetLineId > 0) {
          $this->financeSynchronizer->synchronize($assignment);
        }
      }
    }

    if ($conflicts !== []) {
      $this->messenger()->addWarning($this->t('@count conflicterende dag(en) zijn niet overschreven. Bekijk Afwijkingen of pas de periode aan.', ['@count' => count($conflicts)]));
      foreach (array_slice($conflicts, 0, 5) as $conflict) {
        $this->messenger()->addWarning($conflict);
      }
    }

    if (!$hasLabourBudget && ($created + $updated) > 0) {
      $this->messenger()->addWarning($this->t('Planning opgeslagen. De financiële vergelijking volgt zodra een arbeidsbudget is vergrendeld.'));
    }

    $this->messenger()->addStatus($this->t('Planning opgeslagen: @created daginzet(ten) nieuw, @updated bijgewerkt.', [
      '@created' => $created,
      '@updated' => $updated,
    ]));
    $form_state->setRedirect('brebo_inzet.project_planning', ['node' => $projectId]);
  }

  /**
   * @return array<int, array{start_date:string,end_date:string,start_time:string,end_time:string,days:int,hours:float}>
   */
  private function existingPlanningDefaults(int $projectId): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_personnel_assignment')
      ->condition('field_brebo_project_ref', $projectId)
      ->condition('field_brebo_assignment_status', 'cancelled', '<>')
      ->sort('field_brebo_plan_date', 'ASC')
      ->execute();

    $result = [];
    foreach ($storage->loadMultiple($ids) as $assignment) {
      if (!$assignment instanceof NodeInterface) {
        continue;
      }
      $uid = (int) ($assignment->get('field_brebo_plan_user')->target_id ?? 0);
      $date = (string) ($assignment->get('field_brebo_plan_date')->value ?? '');
      if ($uid <= 0 || $date === '') {
        continue;
      }
      $start = (string) ($assignment->get('field_brebo_assignment_start')->value ?? '07:00');
      $end = (string) ($assignment->get('field_brebo_assignment_end')->value ?? '16:00');
      $hours = (float) ($assignment->get('field_brebo_planned_hours')->value ?? 0);
      if (!isset($result[$uid])) {
        $result[$uid] = [
          'start_date' => $date,
          'end_date' => $date,
          'start_time' => $start !== '' ? $start : '07:00',
          'end_time' => $end !== '' ? $end : '16:00',
          'days' => 0,
          'hours' => 0.0,
        ];
      }
      $result[$uid]['end_date'] = $date;
      $result[$uid]['days']++;
      $result[$uid]['hours'] += $hours;
    }
    return $result;
  }

  /**
   * @return string[]
   */
  private function workdays(string $start, string $end): array {
    $dates = [];
    $cursor = new \DateTimeImmutable($start);
    $last = new \DateTimeImmutable($end);
    while ($cursor <= $last) {
      if ((int) $cursor->format('N') <= 5) {
        $dates[] = $cursor->format('Y-m-d');
      }
      $cursor = $cursor->modify('+1 day');
    }
    return $dates;
  }

  private function overlappingOtherProject(int $projectId, int $uid, string $date, string $start, string $end): ?string {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_personnel_assignment')
      ->condition('field_brebo_plan_user', $uid)
      ->condition('field_brebo_plan_date', $date)
      ->condition('field_brebo_project_ref', $projectId, '<>')
      ->condition('field_brebo_assignment_status', 'cancelled', '<>')
      ->execute();

    foreach ($storage->loadMultiple($ids) as $assignment) {
      if (!$assignment instanceof NodeInterface) {
        continue;
      }
      $otherStart = (string) ($assignment->get('field_brebo_assignment_start')->value ?? '');
      $otherEnd = (string) ($assignment->get('field_brebo_assignment_end')->value ?? '');
      if ($otherStart !== '' && $otherEnd !== '' && $start < $otherEnd && $otherStart < $end) {
        return $assignment->get('field_brebo_project_ref')->entity?->label() ?? 'ander project';
      }
    }
    return NULL;
  }

}
