<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Form;

use Drupal\brebo_inzet\Service\ProjectInzetProposalBuilder;
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
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('brebo_inzet.project_inzet_proposal_builder'),
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
    ];

    $form_state->set('project_id', (int) $node->id());
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $start = (string) $form_state->getValue(['period', 'start_date']);
    $end = (string) $form_state->getValue(['period', 'end_date']);
    if ($start !== '' && $end !== '' && $end < $start) {
      $form_state->setErrorByName('period][end_date', $this->t('Einddatum moet op of na de startdatum liggen.'));
    }
    $from = (string) $form_state->getValue(['times', 'start_time']);
    $to = (string) $form_state->getValue(['times', 'end_time']);
    if ($from !== '' && $to !== '' && $to <= $from) {
      $form_state->setErrorByName('times][end_time', $this->t('Eindtijd moet na de begintijd liggen.'));
    }
  }

  public function previewSubmit(array &$form, FormStateInterface $form_state): void {
    $form_state->setValue('start_date', $form_state->getValue(['period', 'start_date']));
    $form_state->setValue('end_date', $form_state->getValue(['period', 'end_date']));
    $form_state->setValue('start_time', $form_state->getValue(['times', 'start_time']));
    $form_state->setValue('end_time', $form_state->getValue(['times', 'end_time']));
    $form_state->setRebuild(TRUE);
  }

  public function confirmSubmit(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    $project = $this->entityTypeManager->getStorage('node')->load($projectId);
    if (!$project instanceof NodeInterface || $project->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $selected = array_values(array_filter(array_map('intval', (array) $form_state->getValue('users'))));
    $start = (string) $form_state->getValue(['period', 'start_date']);
    $end = (string) $form_state->getValue(['period', 'end_date']);
    $startTime = (string) $form_state->getValue(['times', 'start_time']);
    $endTime = (string) $form_state->getValue(['times', 'end_time']);
    $dates = $this->proposalBuilder->dates($start, $end);
    $hours = max(0, (strtotime('1970-01-01 ' . $endTime) - strtotime('1970-01-01 ' . $startTime)) / 3600);

    $storage = $this->entityTypeManager->getStorage('node');
    $created = 0;
    $skipped = 0;
    foreach ($selected as $uid) {
      $account = $this->entityTypeManager->getStorage('user')->load($uid);
      foreach ($dates as $date) {
        $existing = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition('type', 'brebo_personnel_assignment')
          ->condition('field_brebo_project_ref', $projectId)
          ->condition('field_brebo_plan_user', $uid)
          ->condition('field_brebo_plan_date', $date)
          ->condition('field_brebo_assignment_status', 'cancelled', '<>')
          ->range(0, 1)
          ->execute();
        if ($existing !== []) {
          $skipped++;
          continue;
        }

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
          'field_brebo_assignment_status' => 'planned',
        ]);
        $assignment->save();
        $created++;
      }
    }

    $this->messenger()->addStatus($this->t('@created daginzet(ten) aangemaakt; @skipped bestaande daginzet(ten) zijn behouden.', [
      '@created' => $created,
      '@skipped' => $skipped,
    ]));
    $form_state->setRedirect('brebo_inzet.project_week_planning', ['node' => $projectId], ['query' => ['week' => $start]]);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
