<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Form;

use Drupal\brebo_inzet\Service\PersonnelLabourLineResolver;
use Drupal\brebo_inzet\Service\PersonnelFinanceSynchronizer;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Plans one or more project-team members without exposing the node form.
 */
final class ProjectQuickPlanningForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PersonnelLabourLineResolver $labourLineResolver,
    private readonly PersonnelFinanceSynchronizer $financeSynchronizer,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('brebo_inzet.personnel_labour_line_resolver'),
      $container->get('brebo_inzet.personnel_finance_synchronizer'),
    );
  }

  public function getFormId(): string {
    return 'brebo_inzet_project_quick_planning';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $team = $node->hasField('field_brebo_project_team')
      ? $node->get('field_brebo_project_team')->referencedEntities()
      : [];

    $options = [];
    foreach ($team as $account) {
      if ($account instanceof UserInterface && $account->isActive()) {
        $options[(int) $account->id()] = $account->getDisplayName();
      }
    }

    $form['intro'] = [
      '#markup' => '<div class="brebo-page-header__main"><p class="brebo-page-header__eyebrow">BREBO INZET</p><h2>Medewerkers plannen</h2><p class="brebo-page-header__description">Selecteer mensen uit het projectteam en zet ze in één handeling op dezelfde dag en werktijd. Geen los Drupal-invoerformulier per medewerker.</p></div>',
    ];

    if ($options === []) {
      $form['empty'] = [
        '#markup' => '<div class="messages messages--warning">Dit project heeft nog geen projectteam. Voeg eerst medewerkers toe via Projectteam.</div>',
      ];
      return $form;
    }

    $form['users'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Medewerkers'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['planning_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Periode'),
      '#options' => [
        'day' => $this->t('Eén dag'),
        'weekdays' => $this->t('Werkweek (maandag t/m vrijdag)'),
      ],
      '#default_value' => 'day',
    ];
    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Startdatum'),
      '#default_value' => (new DrupalDateTime('now'))->format('Y-m-d'),
      '#required' => TRUE,
      '#description' => $this->t('Bij Werkweek wordt de maandag van deze week gebruikt en worden maandag t/m vrijdag ingepland.'),
    ];
    $form['time'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline']],
      'start' => [
        '#type' => 'time',
        '#title' => $this->t('Van'),
        '#default_value' => '07:00',
        '#required' => TRUE,
      ],
      'end' => [
        '#type' => 'time',
        '#title' => $this->t('Tot'),
        '#default_value' => '16:00',
        '#required' => TRUE,
      ],
    ];
    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => ['planned' => $this->t('Gepland'), 'confirmed' => $this->t('Bevestigd')],
      '#default_value' => 'planned',
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Inplannen'),
      '#button_type' => 'primary',
    ];

    $form_state->set('project_id', (int) $node->id());
    return $form;
  }

  private function labourProductivityAvailable(int $projectId): bool {
    /** @var \Drupal\brebo_finance\Service\LabourProductivityManager $manager */
    $manager = \Drupal::service('brebo_finance.labour_productivity_manager');
    return $manager->labourBudgetLines($projectId) !== [];
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $start = (string) $form_state->getValue(['time', 'start']);
    $end = (string) $form_state->getValue(['time', 'end']);
    if ($start !== '' && $end !== '' && $end <= $start) {
      $form_state->setErrorByName('time][end', $this->t('Eindtijd moet na de begintijd liggen.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    $project = $this->entityTypeManager->getStorage('node')->load($projectId);
    if (!$project instanceof NodeInterface || $project->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $date = (string) $form_state->getValue('date');
    $mode = (string) $form_state->getValue('planning_mode');
    $dates = [$date];
    if ($mode === 'weekdays') {
      $monday = new DrupalDateTime($date);
      $monday->modify('monday this week');
      $dates = [];
      for ($offset = 0; $offset < 5; $offset++) {
        $planningDate = clone $monday;
        if ($offset > 0) {
          $planningDate->modify('+' . $offset . ' days');
        }
        $dates[] = $planningDate->format('Y-m-d');
      }
    }
    $start = (string) $form_state->getValue(['time', 'start']);
    $end = (string) $form_state->getValue(['time', 'end']);
    $status = (string) $form_state->getValue('status');
    $hours = max(0, (strtotime('1970-01-01 ' . $end) - strtotime('1970-01-01 ' . $start)) / 3600);

    $selected = array_filter(array_map('intval', (array) $form_state->getValue('users')));
    $hasLabourBudget = $this->labourProductivityAvailable($projectId);
    $storage = $this->entityTypeManager->getStorage('node');
    $created = 0;
    $skipped = 0;

    foreach ($selected as $uid) {
      $account = $this->entityTypeManager->getStorage('user')->load($uid);
      if (!$account instanceof UserInterface) {
        continue;
      }
      foreach ($dates as $planningDate) {
        $budgetLineId = 0;
        if ($hasLabourBudget) {
          try {
            $labourLine = $this->labourLineResolver->resolve($projectId, $account);
            $budgetLineId = (int) $labourLine['id'];
          }
          catch (\Throwable $e) {
            $this->messenger()->addError($e->getMessage());
            continue;
          }
        }
        $existing = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition('type', 'brebo_personnel_assignment')
          ->condition('field_brebo_project_ref', $projectId)
          ->condition('field_brebo_plan_user', $uid)
          ->condition('field_brebo_plan_date', $planningDate)
          ->condition('field_brebo_assignment_status', 'cancelled', '<>')
          ->range(0, 1)
          ->execute();
        if ($existing !== []) {
          $skipped++;
          continue;
        }

        $assignment = $storage->create([
          'type' => 'brebo_personnel_assignment',
          'title' => sprintf('%s - %s - %s', $project->label(), $account?->label() ?? ('Gebruiker ' . $uid), $planningDate),
          'status' => 1,
          'field_brebo_project_ref' => ['target_id' => $projectId],
          'field_brebo_plan_user' => ['target_id' => $uid],
          'field_brebo_plan_date' => $planningDate,
          'field_brebo_assignment_start' => $start,
          'field_brebo_assignment_end' => $end,
          'field_brebo_planned_hours' => round($hours, 2),
          'field_brebo_budget_line_id' => $budgetLineId > 0 ? $budgetLineId : NULL,
          'field_brebo_assignment_status' => $status,
        ]);
        $assignment->save();
        if ($budgetLineId > 0) {
          $this->financeSynchronizer->synchronize($assignment);
        }
        $created++;
      }
    }

    if (!$hasLabourBudget && $created > 0) {
      $this->messenger()->addWarning($this->t('De inzet is gepland zonder financiële koppeling; er is nog geen vergrendeld arbeidsbudget.'));
    }
    $this->messenger()->addStatus($this->t('@created medewerker(s) ingepland. @skipped bestaande daginzet(ten) overgeslagen.', [
      '@created' => $created,
      '@skipped' => $skipped,
    ]));
    $weekAnchor = $dates[0] ?? $date;
    $form_state->setRedirect('brebo_inzet.project_week_planning', ['node' => $projectId], ['query' => ['week' => $weekAnchor]]);
  }

}
