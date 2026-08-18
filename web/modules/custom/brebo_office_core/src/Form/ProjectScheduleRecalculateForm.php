<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\brebo_office_core\Service\ProjectScheduleCalculator;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Previews and explicitly applies dependency-driven project dates.
 */
final class ProjectScheduleRecalculateForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ProjectScheduleCalculator $calculator,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('brebo_office_core.project_schedule_calculator'),
    );
  }

  public function getFormId(): string {
    return 'brebo_project_schedule_recalculate';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
    $form_state->set('project_id', (int) $node->id());
    $result = $this->calculator->calculate($this->activities((int) $node->id()));

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Voorvertoning op basis van voorgangers, FS/SS/FF/SF-relaties, wachtdagen en een maandag-tot-vrijdagwerkweek. Er wordt pas opgeslagen nadat u hieronder expliciet toepast.') . '</p>',
    ];
    if ($result['errors']) {
      $form['errors'] = [
        '#theme' => 'item_list',
        '#title' => $this->t('Planning kan niet worden doorgerekend'),
        '#items' => $result['errors'],
        '#attributes' => ['class' => ['messages', 'messages--error']],
      ];
      return $form;
    }

    $rows = [];
    $changed = 0;
    foreach ($result['activities'] as $activity) {
      if (!($activity['changed'] ?? FALSE)) {
        continue;
      }
      $changed++;
      $rows[] = [
        $activity['code'],
        $activity['label'],
        $activity['start'],
        $activity['proposed_start'],
        $activity['end'],
        $activity['proposed_end'],
        $activity['reason'],
      ];
    }
    $form['preview'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Code'), $this->t('Activiteit'), $this->t('Huidige start'),
        $this->t('Nieuwe start'), $this->t('Huidig gereed'),
        $this->t('Nieuw gereed'), $this->t('Reden'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Alle activiteiten staan al overeenkomstig hun relaties.'),
      '#sticky' => TRUE,
    ];
    $form['confirmation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ik heb de voorgestelde datumwijzigingen gecontroleerd.'),
      '#required' => $changed > 0,
      '#access' => $changed > 0,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('@count datumwijzigingen toepassen', ['@count' => $changed]),
      '#button_type' => 'primary',
      '#disabled' => $changed === 0,
      '#access' => $changed > 0,
    ];
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $project_id = (int) $form_state->get('project_id');
    $result = $this->calculator->calculate($this->activities($project_id));
    if ($result['errors']) {
      foreach ($result['errors'] as $error) {
        $this->messenger()->addError($error);
      }
      return;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $updated = 0;
    foreach ($result['activities'] as $id => $proposal) {
      if (!($proposal['changed'] ?? FALSE)) {
        continue;
      }
      $activity = $storage->load($id);
      if (!$activity instanceof NodeInterface || $activity->bundle() !== 'brebo_plan_activity'
        || !$activity->access('update', $this->currentUser())) {
        $this->messenger()->addWarning($this->t('Activiteit @activity is niet gewijzigd: onvoldoende toegang.', [
          '@activity' => $proposal['label'] ?? $id,
        ]));
        continue;
      }
      $activity->set('field_brebo_plan_start', $proposal['proposed_start']);
      $activity->set('field_brebo_plan_end', $proposal['proposed_end']);
      $activity->setNewRevision(TRUE);
      $activity->setRevisionLogMessage('Planning doorgerekend vanuit vastgelegde voorgangers en relaties.');
      $activity->save();
      $updated++;
    }
    $this->messenger()->addStatus($this->t('@count planningsactiviteit(en) opnieuw gedateerd.', ['@count' => $updated]));
    $form_state->setRedirect('brebo_office_core.project_planning', ['node' => $project_id]);
  }

  /**
   * Normalizes project activities for the pure calculator.
   */
  private function activities(int $project_id): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_plan_activity')
      ->condition('field_brebo_project_ref.target_id', $project_id)
      ->execute();
    $activities = [];
    foreach ($storage->loadMultiple($ids) as $activity) {
      if (!$activity instanceof NodeInterface) {
        continue;
      }
      $start = (string) ($activity->get('field_brebo_plan_start')->value ?? '');
      $end = (string) ($activity->get('field_brebo_plan_end')->value ?? '');
      if ($start === '' || $end === '') {
        continue;
      }
      $predecessors = array_map(
        static fn (NodeInterface $predecessor): int => (int) $predecessor->id(),
        array_filter(
          $activity->get('field_brebo_plan_predecessors')->referencedEntities(),
          static fn ($predecessor): bool => $predecessor instanceof NodeInterface
        )
      );
      $activities[(int) $activity->id()] = [
        'id' => (int) $activity->id(),
        'code' => (string) ($activity->get('field_brebo_plan_code')->value ?? '—'),
        'label' => (string) $activity->label(),
        'start' => $start,
        'end' => $end,
        'duration' => max(1, (int) ($activity->get('field_brebo_plan_duration')->value ?? 1)),
        'predecessors' => $predecessors,
        'relation' => (string) ($activity->get('field_brebo_plan_relation')->value ?? 'FS'),
        'lag' => (int) ($activity->get('field_brebo_plan_lag_days')->value ?? 0),
      ];
    }
    return $activities;
  }

}
