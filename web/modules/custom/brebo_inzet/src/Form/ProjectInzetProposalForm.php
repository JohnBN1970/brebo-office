<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Form;

use Drupal\brebo_finance\Service\LabourProductivityManager;
use Drupal\brebo_inzet\Service\ProjectInzetProposalBuilder;
use Drupal\brebo_inzet\Service\PersonnelFinanceSynchronizer;
use Drupal\brebo_inzet\Service\PersonnelLabourLineResolver;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Previews and confirms a complete project-period personnel deployment.
 */
final class ProjectInzetProposalForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ProjectInzetProposalBuilder $proposalBuilder,
    private readonly LabourProductivityManager $labourProductivity,
    private readonly PersonnelFinanceSynchronizer $financeSynchronizer,
    private readonly PersonnelLabourLineResolver $labourLineResolver,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('brebo_inzet.project_inzet_proposal_builder'),
      $container->get('brebo_finance.labour_productivity_manager'),
      $container->get('brebo_inzet.personnel_finance_synchronizer'),
      $container->get('brebo_inzet.personnel_labour_line_resolver'),
    );
  }

  public function getFormId(): string {
    return 'brebo_inzet_project_proposal';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $teamOptions = [];
    if ($node->hasField('field_brebo_project_team')) {
      foreach ($node->get('field_brebo_project_team')->referencedEntities() as $account) {
        if ($account instanceof UserInterface && $account->isActive()) {
          $teamOptions[(int) $account->id()] = $account->getDisplayName();
        }
      }
    }

    $defaultUsers = array_keys($teamOptions);
    $detected = $this->proposalBuilder->build($node, $defaultUsers);

    $selected = $form_state->getValue('users');
    $selected = is_array($selected)
      ? array_values(array_filter(array_map('intval', $selected)))
      : $defaultUsers;

    $start = (string) ($form_state->getValue('start_date') ?: ($detected['start'] ?? ''));
    $end = (string) ($form_state->getValue('end_date') ?: ($detected['end'] ?? ''));
    $startTime = (string) ($form_state->getValue('start_time') ?: '07:00');
    $endTime = (string) ($form_state->getValue('end_time') ?: '16:00');

    $proposal = $this->proposalBuilder->build($node, $selected, $start ?: NULL, $end ?: NULL, $startTime, $endTime);
    $labourLines = $this->labourProductivity->labourBudgetLines((int) $node->id());
    $financeBudgetHours = array_sum(array_map(
      static fn (array $line): float => max(0.0, (float) ($line['budget_hours'] ?? 0)),
      $labourLines
    ));
    // Finance locked labour lines are the authoritative labour budget.
    $proposal['budget_hours'] = round($financeBudgetHours, 2);
    $proposal['delta_hours'] = round((float) $proposal['proposed_hours'] - $financeBudgetHours, 2);
    $conflicts = $this->crossProjectConflicts((int) $node->id(), $selected, $start, $end, $startTime, $endTime);
    $unavailable = $this->unavailabilityConflicts($selected, $start, $end);
    $delta = (float) $proposal['delta_hours'];
    $tone = $proposal['budget_hours'] <= 0
      ? 'attention'
      : (abs($delta) < 0.01 ? 'positive' : ($delta > 0 ? 'critical' : 'attention'));

    $form['header'] = [
      '#markup' => '<div class="brebo-page-header__main"><p class="brebo-page-header__eyebrow">BREBO INZET</p><h1>Projectinzet voorstellen</h1><p class="brebo-page-header__description">Vul de volledige uitvoeringsperiode vanuit het projectteam. Office vergelijkt het voorstel eerst met de uren uit de werkbegroting; pas na bevestigen worden daginzetten aangemaakt.</p></div>',
    ];

    if ($teamOptions === []) {
      $form['empty'] = [
        '#markup' => '<div class="messages messages--warning">Dit project heeft nog geen actief projectteam. Voeg eerst medewerkers toe aan Projectteam.</div>',
      ];
      return $form;
    }

    $form['users'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Projectteam inzetten'),
      '#options' => $teamOptions,
      '#default_value' => $selected,
      '#required' => TRUE,
    ];
    $form['period'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline']],
    ];
    $form['period']['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Van'),
      '#default_value' => $start,
      '#required' => TRUE,
    ];
    $form['period']['end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Tot en met'),
      '#default_value' => $end,
      '#required' => TRUE,
    ];
    $form['times'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline']],
    ];
    $form['times']['start_time'] = [
      '#type' => 'time',
      '#title' => $this->t('Werkdag vanaf'),
      '#default_value' => $startTime,
      '#required' => TRUE,
    ];
    $form['times']['end_time'] = [
      '#type' => 'time',
      '#title' => $this->t('Werkdag tot'),
      '#default_value' => $endTime,
      '#required' => TRUE,
    ];

    $form['comparison'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-kpis']],
      'budget' => [
        '#markup' => '<div class="brebo-kpi brebo-kpi--neutral"><span class="brebo-kpi__value">' . number_format((float) $proposal['budget_hours'], 2, ',', '.') . ' u</span><span class="brebo-kpi__label">Werkbegroting</span></div>',
      ],
      'days' => [
        '#markup' => '<div class="brebo-kpi brebo-kpi--neutral"><span class="brebo-kpi__value">' . (int) $proposal['workdays'] . '</span><span class="brebo-kpi__label">Werkdagen</span></div>',
      ],
      'proposal' => [
        '#markup' => '<div class="brebo-kpi brebo-kpi--neutral"><span class="brebo-kpi__value">' . number_format((float) $proposal['proposed_hours'], 2, ',', '.') . ' u</span><span class="brebo-kpi__label">Voorgestelde inzet</span></div>',
      ],
      'delta' => [
        '#markup' => '<div class="brebo-kpi brebo-kpi--' . $tone . '"><span class="brebo-kpi__value">' . ($delta > 0 ? '+' : '') . number_format($delta, 2, ',', '.') . ' u</span><span class="brebo-kpi__label">Verschil t.o.v. begroting</span></div>',
      ],
    ];

    if ($labourLines !== []) {
      $lineRows = [];
      $totalLineHours = array_sum(array_map(static fn (array $line): float => max(0.0, (float) ($line['budget_hours'] ?? 0)), $labourLines));
      foreach ($labourLines as $line) {
        $hours = max(0.0, (float) ($line['budget_hours'] ?? 0));
        $share = $totalLineHours > 0 ? ($hours / $totalLineHours) * 100 : 0.0;
        $lineRows[] = [
          htmlspecialchars((string) ($line['description'] ?? 'Arbeid'), ENT_QUOTES, 'UTF-8'),
          '€ ' . number_format((float) ($line['hourly_cost_ex_vat'] ?? 0), 2, ',', '.') . '/u',
          number_format($hours, 2, ',', '.') . ' u',
          number_format($share, 1, ',', '.') . '%',
        ];
      }
      $form['labour_lines'] = [
        '#type' => 'table',
        '#caption' => $this->t('Arbeidsbudget'),
        '#header' => [$this->t('Arbeid'), $this->t('Begroot tarief'), $this->t('Begrote uren'), $this->t('Aandeel')],
        '#rows' => $lineRows,
        '#empty' => $this->t('Geen vergrendelde arbeidsregels gevonden.'),
      ];
      $form['line_distribution_note'] = [
        '#markup' => '<div class="messages messages--status"><strong>Begroot tarief en medewerkerkostprijs staan los van elkaar.</strong> Office verdeelt geplande uren over de beschikbare arbeidsbegroting; de werkelijke medewerkerkostprijs wordt apart gebruikt voor de financiële prognose. Een medewerker hoeft dus niet hetzelfde uurtarief te hebben als de begroting.</div>',
      ];
    }

    if ($unavailable !== []) {
      $conflicts = array_merge($conflicts, $unavailable);
    }

    $capacity = $this->capacityGap($node, $selected, $start, $end, $startTime, $endTime, (float) $proposal['budget_hours']);
    $form['capacity'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-kpis']],
      'available' => [
        '#markup' => '<div class="brebo-kpi brebo-kpi--neutral"><span class="brebo-kpi__value">' . number_format($capacity['available_hours'], 2, ',', '.') . ' u</span><span class="brebo-kpi__label">Beschikbare ploegcapaciteit</span></div>',
      ],
      'shortage' => [
        '#markup' => '<div class="brebo-kpi brebo-kpi--' . ($capacity['shortage_hours'] > 0 ? 'critical' : 'positive') . '"><span class="brebo-kpi__value">' . number_format($capacity['shortage_hours'], 2, ',', '.') . ' u</span><span class="brebo-kpi__label">Capaciteitstekort</span></div>',
      ],
      'days' => [
        '#markup' => '<div class="brebo-kpi brebo-kpi--' . ($capacity['shortage_days'] > 0 ? 'critical' : 'positive') . '"><span class="brebo-kpi__value">' . number_format($capacity['shortage_days'], 1, ',', '.') . '</span><span class="brebo-kpi__label">Ontbrekende mandagen</span></div>',
      ],
    ];

    if ($capacity['shortage_hours'] > 0) {
      $suggestions = $this->availableReplacementCandidates((int) $node->id(), $selected, $start, $end, $startTime, $endTime);
      $text = $suggestions === []
        ? 'Er zijn op basis van de huidige Office-planning geen direct vrije actieve medewerkers gevonden.'
        : 'Vrije medewerkers om te onderzoeken: <strong>' . implode(', ', array_map(static fn (string $name): string => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $suggestions)) . '</strong>.';
      $form['capacity_warning'] = [
        '#markup' => '<div class="messages messages--warning"><strong>De gekozen ploeg levert binnen deze periode ' . number_format($capacity['shortage_hours'], 2, ',', '.') . ' uur te weinig capaciteit voor de werkbegroting.</strong> Dat is circa ' . number_format($capacity['shortage_days'], 1, ',', '.') . ' mandag(en). ' . $text . '</div>',
      ];
    }

    if ($conflicts !== []) {
      $items = '';
      foreach ($conflicts as $conflict) {
        $items .= '<li>' . htmlspecialchars($conflict, ENT_QUOTES, 'UTF-8') . '</li>';
      }
      $form['conflict_warning'] = [
        '#markup' => '<div class="messages messages--error"><strong>Bezettingsconflict gevonden.</strong><ul>' . $items . '</ul><p>Office blokkeert definitieve projectvulling zolang dezelfde medewerker op hetzelfde tijdstip op een ander project staat.</p></div>',
      ];
      $form_state->set('cross_project_conflicts', $conflicts);
    }
    else {
      $form_state->set('cross_project_conflicts', []);
    }

    if ((float) $proposal['budget_hours'] <= 0) {
      $form['budget_warning'] = [
        '#markup' => '<div class="messages messages--warning">Er zijn voor dit project nog geen gebudgetteerde uren gevonden in de gekoppelde werkbegrotingen. Het inzetvoorstel kan wel worden bekeken, maar de budgetvergelijking is nog niet volledig.</div>',
      ];
    }
    elseif ($delta > 0) {
      $form['budget_warning'] = [
        '#markup' => '<div class="messages messages--warning"><strong>Voorstel overschrijdt de werkbegroting met ' . number_format($delta, 2, ',', '.') . ' uur.</strong> Pas ploeg, periode of werktijden aan voordat je bevestigt als deze overschrijding niet bewust is.</div>',
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['preview'] = [
      '#type' => 'submit',
      '#value' => $this->t('Voorstel herberekenen'),
      '#submit' => ['::previewSubmit'],
    ];
    $form['actions']['confirm'] = [
      '#type' => 'submit',
      '#value' => $this->t('Volledige projectinzet bevestigen'),
      '#button_type' => 'primary',
      '#submit' => ['::confirmSubmit'],
      '#disabled' => $conflicts !== [],
    ];

    $form_state->set('project_id', (int) $node->id());
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $start = (string) $form_state->getValue('start_date');
    $end = (string) $form_state->getValue('end_date');
    if ($start !== '' && $end !== '' && $end < $start) {
      $form_state->setErrorByName('period][end_date', $this->t('Einddatum moet op of na de startdatum liggen.'));
    }
    $from = (string) $form_state->getValue('start_time');
    $to = (string) $form_state->getValue('end_time');
    if ($from !== '' && $to !== '' && $to <= $from) {
      $form_state->setErrorByName('times][end_time', $this->t('Eindtijd moet na de begintijd liggen.'));
    }
  }

  public function previewSubmit(array &$form, FormStateInterface $form_state): void {
    // period/times are visual containers only (no #tree), so their child
    // values already live at the top level. Rebuild directly from submitted
    // values instead of overwriting them with non-existent nested values.
    $form_state->setRebuild(TRUE);
  }

  public function confirmSubmit(array &$form, FormStateInterface $form_state): void {
    if ((array) $form_state->get('cross_project_conflicts') !== []) {
      $this->messenger()->addError($this->t('Projectinzet is niet aangemaakt: los eerst de bezettingsconflicten op.'));
      $form_state->setRebuild(TRUE);
      return;
    }
    $projectId = (int) $form_state->get('project_id');
    $project = $this->entityTypeManager->getStorage('node')->load($projectId);
    if (!$project instanceof NodeInterface || $project->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $selected = array_values(array_filter(array_map('intval', (array) $form_state->getValue('users'))));
    $start = (string) $form_state->getValue('start_date');
    $end = (string) $form_state->getValue('end_date');
    $startTime = (string) $form_state->getValue('start_time');
    $endTime = (string) $form_state->getValue('end_time');
    $dates = $this->proposalBuilder->dates($start, $end);
    $hours = max(0, (strtotime('1970-01-01 ' . $endTime) - strtotime('1970-01-01 ' . $startTime)) / 3600);

    $labourLines = $this->labourProductivity->labourBudgetLines($projectId);
    if ($labourLines === []) {
      $this->messenger()->addError($this->t('Projectinzet is niet aangemaakt: er is geen vergrendelde arbeidsbegroting met arbeid beschikbaar.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $userStorage = $this->entityTypeManager->getStorage('user');

    $storage = $this->entityTypeManager->getStorage('node');
    $existingIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_personnel_assignment')
      ->condition('field_brebo_project_ref', $projectId)
      ->condition('field_brebo_plan_user', $selected, 'IN')
      ->condition('field_brebo_plan_date', $dates, 'IN')
      ->condition('field_brebo_assignment_status', 'cancelled', '<>')
      ->execute();

    $existingByPersonDate = [];
    foreach ($storage->loadMultiple($existingIds) as $existingAssignment) {
      if (!$existingAssignment instanceof NodeInterface) {
        continue;
      }
      $uid = (int) ($existingAssignment->get('field_brebo_plan_user')->target_id ?? 0);
      $date = (string) ($existingAssignment->get('field_brebo_plan_date')->value ?? '');
      if ($uid > 0 && $date !== '') {
        $existingByPersonDate[$uid . ':' . $date] = TRUE;
      }
    }

    $created = 0;
    $skipped = 0;

    // Plan day-first so the full selected crew is filled evenly through time.
    foreach ($dates as $date) {
      foreach ($selected as $uid) {
        if (isset($existingByPersonDate[$uid . ':' . $date])) {
          $skipped++;
          continue;
        }

        $account = $userStorage->load($uid);
        if (!$account instanceof UserInterface) {
          continue;
        }
        try {
          $labourLine = $this->labourLineResolver->resolve($projectId, $account);
        }
        catch (\Throwable $e) {
          $this->messenger()->addError($e->getMessage());
          continue;
        }
        $budgetLineId = (int) $labourLine['id'];

        $assignment = $storage->create([
          'type' => 'brebo_personnel_assignment',
          'title' => sprintf('%s - %s - %s', $project->label(), $account?->label() ?? ('Gebruiker ' . $uid), $date),
          'status' => 1,
          'field_brebo_project_ref' => ['target_id' => $projectId],
          'field_brebo_plan_user' => ['target_id' => $uid],
          'field_brebo_plan_date' => $date,
          'field_brebo_assignment_start' => $startTime,
          'field_brebo_assignment_end' => $endTime,
          'field_brebo_planned_hours' => round($hours, 2),
          'field_brebo_budget_line_id' => $budgetLineId,
          'field_brebo_assignment_status' => 'planned',
        ]);
        $assignment->save();
        $this->financeSynchronizer->synchronize($assignment);
        $existingByPersonDate[$uid . ':' . $date] = TRUE;
        $created++;
      }
    }

    $this->messenger()->addStatus($this->t('@created daginzet(ten) aangemaakt; @skipped bestaande daginzet(ten) zijn behouden.', [
      '@created' => $created,
      '@skipped' => $skipped,
    ]));
    $form_state->setRedirect('brebo_inzet.project_week_planning', ['node' => $projectId], ['query' => ['week' => $start]]);
  }

  /**
   * @return string[]
   */
  private function crossProjectConflicts(int $projectId, array $userIds, string $start, string $end, string $startTime, string $endTime): array {
    if ($userIds === [] || $start === '' || $end === '') {
      return [];
    }

    $dates = $this->proposalBuilder->dates($start, $end);
    if ($dates === []) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_personnel_assignment')
      ->condition('field_brebo_plan_user', $userIds, 'IN')
      ->condition('field_brebo_plan_date', $dates, 'IN')
      ->condition('field_brebo_project_ref', $projectId, '<>')
      ->condition('field_brebo_assignment_status', 'cancelled', '<>')
      ->execute();

    $conflicts = [];
    foreach ($storage->loadMultiple($ids) as $assignment) {
      if (!$assignment instanceof NodeInterface) {
        continue;
      }
      $otherStart = (string) ($assignment->get('field_brebo_assignment_start')->value ?? '');
      $otherEnd = (string) ($assignment->get('field_brebo_assignment_end')->value ?? '');
      if ($otherStart === '' || $otherEnd === '' || !$this->timesOverlap($startTime, $endTime, $otherStart, $otherEnd)) {
        continue;
      }
      $person = $assignment->get('field_brebo_plan_user')->entity;
      $project = $assignment->get('field_brebo_project_ref')->entity;
      $date = (string) ($assignment->get('field_brebo_plan_date')->value ?? '');
      $conflicts[] = sprintf(
        '%s · %s · %s (%s-%s)',
        $person?->label() ?? 'Onbekende medewerker',
        $date,
        $project?->label() ?? 'Ander project',
        $otherStart,
        $otherEnd,
      );
    }

    return array_values(array_unique($conflicts));
  }

  /**
   * @return string[]
   */
  private function unavailabilityConflicts(array $userIds, string $start, string $end): array {
    if ($userIds === [] || $start === '' || $end === '') {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_workforce_availability')
      ->condition('field_brebo_plan_user', $userIds, 'IN')
      ->condition('field_brebo_unavailable_start', $end, '<=')
      ->condition('field_brebo_unavailable_end', $start, '>=')
      ->execute();

    $conflicts = [];
    foreach ($storage->loadMultiple($ids) as $period) {
      if (!$period instanceof NodeInterface) {
        continue;
      }
      $person = $period->get('field_brebo_plan_user')->entity;
      $type = (string) ($period->get('field_brebo_unavailable_type')->value ?? '');
      $label = $type === 'leave' ? 'Verlof' : 'Niet beschikbaar';
      $conflicts[] = sprintf(
        '%s · %s · %s t/m %s',
        $person?->label() ?? 'Onbekende medewerker',
        $label,
        (string) $period->get('field_brebo_unavailable_start')->value,
        (string) $period->get('field_brebo_unavailable_end')->value,
      );
    }
    return array_values(array_unique($conflicts));
  }

  /**
   * @return array{available_hours: float, shortage_hours: float, shortage_days: float}
   */
  private function capacityGap(NodeInterface $project, array $userIds, string $start, string $end, string $startTime, string $endTime, float $budgetHours): array {
    $dates = $this->proposalBuilder->dates($start, $end);
    $hoursPerDay = max(0.0, (strtotime('1970-01-01 ' . $endTime) - strtotime('1970-01-01 ' . $startTime)) / 3600);
    $blocked = [];
    foreach ($this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'brebo_workforce_availability']) as $period) {
      if (!$period instanceof NodeInterface) {
        continue;
      }
      $uid = (int) ($period->get('field_brebo_plan_user')->target_id ?? 0);
      if (!in_array($uid, $userIds, TRUE)) {
        continue;
      }
      $from = (string) ($period->get('field_brebo_unavailable_start')->value ?? '');
      $to = (string) ($period->get('field_brebo_unavailable_end')->value ?? '');
      foreach ($dates as $date) {
        if ($from <= $date && $to >= $date) {
          $blocked[$uid . ':' . $date] = TRUE;
        }
      }
    }
    // Cross-project assignments consume the same capacity even when the
    // proposal itself is blocked from confirmation. Count those occupied
    // person/date slots so the capacity KPI never overstates availability.
    if ($userIds !== [] && $dates !== []) {
      $assignmentStorage = $this->entityTypeManager->getStorage('node');
      $assignmentIds = $assignmentStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'brebo_personnel_assignment')
        ->condition('field_brebo_plan_user', $userIds, 'IN')
        ->condition('field_brebo_plan_date', $dates, 'IN')
        ->condition('field_brebo_project_ref', (int) $project->id(), '<>')
        ->condition('field_brebo_assignment_status', 'cancelled', '<>')
        ->execute();
      foreach ($assignmentStorage->loadMultiple($assignmentIds) as $assignment) {
        if (!$assignment instanceof NodeInterface) {
          continue;
        }
        $otherStart = (string) ($assignment->get('field_brebo_assignment_start')->value ?? '');
        $otherEnd = (string) ($assignment->get('field_brebo_assignment_end')->value ?? '');
        if ($otherStart === '' || $otherEnd === '' || !$this->timesOverlap($startTime, $endTime, $otherStart, $otherEnd)) {
          continue;
        }
        $uid = (int) ($assignment->get('field_brebo_plan_user')->target_id ?? 0);
        $date = (string) ($assignment->get('field_brebo_plan_date')->value ?? '');
        if ($uid > 0 && $date !== '') {
          $blocked[$uid . ':' . $date] = TRUE;
        }
      }
    }

    $availableSlots = max(0, (count($userIds) * count($dates)) - count($blocked));
    $availableHours = round($availableSlots * $hoursPerDay, 2);
    $shortage = round(max(0.0, $budgetHours - $availableHours), 2);
    return [
      'available_hours' => $availableHours,
      'shortage_hours' => $shortage,
      'shortage_days' => $hoursPerDay > 0 ? round($shortage / $hoursPerDay, 1) : 0.0,
    ];
  }

  /**
   * Finds active employees who have no overlapping assignment/unavailability.
   *
   * @return string[]
   */
  private function availableReplacementCandidates(int $projectId, array $selected, string $start, string $end, string $startTime, string $endTime): array {
    $dates = $this->proposalBuilder->dates($start, $end);
    if ($dates === []) {
      return [];
    }
    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['status' => 1]);
    $candidates = [];
    foreach ($users as $account) {
      if (!$account instanceof UserInterface || (int) $account->id() === 0 || in_array((int) $account->id(), $selected, TRUE)) {
        continue;
      }
      $uid = (int) $account->id();
      if ($this->crossProjectConflicts($projectId, [$uid], $start, $end, $startTime, $endTime) !== []) {
        continue;
      }
      if ($this->unavailabilityConflicts([$uid], $start, $end) !== []) {
        continue;
      }
      $candidates[] = $account->getDisplayName();
      if (count($candidates) >= 8) {
        break;
      }
    }
    sort($candidates, SORT_NATURAL | SORT_FLAG_CASE);
    return $candidates;
  }

  private function timesOverlap(string $startA, string $endA, string $startB, string $endB): bool {
    return $startA < $endB && $startB < $endA;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
