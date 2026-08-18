<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\brebo_office_core\Service\WorkforceTimeEntryControl;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Reviews, corrects and approves one BREBO Inzet time entry.
 */
final class WorkforceTimeReviewForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly WorkforceTimeEntryControl $control,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('brebo_office_core.workforce_time_entry_control'),
    );
  }

  public function getFormId(): string {
    return 'brebo_office_core_workforce_time_review_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node || $node->bundle() !== 'brebo_time_entry') {
      throw new \InvalidArgumentException('Deze beoordeling vereist een BREBO urenregistratie.');
    }
    $form_state->set('entry_id', (int) $node->id());
    $assessment = $this->assessment($node);
    $shift = $node->get('field_brebo_time_shift')->entity;
    $budget = $node->get('field_brebo_time_budget')->entity;
    $person = $node->get('field_brebo_time_contact')->entity ?? $node->get('field_brebo_time_user')->entity;

    $form['summary'] = [
      '#type' => 'table',
      '#header' => [$this->t('Controle'), $this->t('Persoon'), $this->t('Dienst'), $this->t('Gepland'), $this->t('Werkelijk'), $this->t('Verschil'), $this->t('Werkbegroting')],
      '#rows' => [[
        $assessment['status'],
        $person?->label() ?? '—',
        $shift?->label() ?? '—',
        number_format($assessment['planned_hours'], 2, ',', '.'),
        number_format($assessment['actual_hours'], 2, ',', '.'),
        number_format($assessment['delta_hours'], 2, ',', '.'),
        $budget?->label() ?? '—',
      ]],
    ];

    $signals = array_merge($assessment['blocking'], $assessment['deviations']);
    $form['signals'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Controlebevindingen'),
      '#items' => $signals ?: [$this->t('Geen afwijkingen aangetroffen.')],
    ];

    $form['start'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Werkelijke start'),
      '#default_value' => $this->dateValue($node->get('field_brebo_time_start')->value),
      '#required' => TRUE,
    ];
    $form['end'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Werkelijk einde'),
      '#default_value' => $this->dateValue($node->get('field_brebo_time_end')->value),
      '#required' => TRUE,
    ];
    $form['break_minutes'] = [
      '#type' => 'number',
      '#title' => $this->t('Onbetaalde pauze (minuten)'),
      '#default_value' => (int) ($node->get('field_brebo_time_break_min')->value ?? 0),
      '#min' => 0,
      '#step' => 1,
      '#required' => TRUE,
    ];
    $form['reason'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Beoordeling / correctiereden'),
      '#description' => $this->t('Verplicht bij corrigeren of afwijzen. Bij goedkeuring van een afwijking is een motivering eveneens verplicht.'),
      '#rows' => 4,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['approve'] = [
      '#type' => 'submit',
      '#value' => $this->t('Goedkeuren'),
      '#submit' => ['::approve'],
      '#button_type' => 'primary',
      '#disabled' => (bool) $assessment['blocking'],
    ];
    $form['actions']['correct'] = [
      '#type' => 'submit',
      '#value' => $this->t('Correctie opslaan'),
      '#submit' => ['::correct'],
    ];
    $form['actions']['reject'] = [
      '#type' => 'submit',
      '#value' => $this->t('Afwijzen'),
      '#submit' => ['::reject'],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Explicit action handlers are used.
  }

  public function approve(array &$form, FormStateInterface $form_state): void {
    $entry = $this->loadEntry($form_state);
    if (!$entry) {
      return;
    }
    $assessment = $this->assessment($entry);
    if ($assessment['blocking']) {
      $form_state->setErrorByName('', $this->t('Goedkeuren is geblokkeerd: los eerst de kritieke controlebevindingen op.'));
      return;
    }
    $reason = trim((string) $form_state->getValue('reason'));
    if ($assessment['deviations'] && $reason === '') {
      $form_state->setErrorByName('reason', $this->t('Motiveer waarom deze afwijkende uren toch worden goedgekeurd.'));
      return;
    }

    $entry->set('field_brebo_time_status', 'Goedgekeurd');
    $entry->set('field_brebo_time_approved_by', ['target_id' => $this->currentUser()->id()]);
    $entry->set('field_brebo_time_approved_at', (new DrupalDateTime('now', 'UTC'))->format('Y-m-d\TH:i:s'));
    $this->saveRevision($entry, 'Uren goedgekeurd. ' . $reason);
    $this->messenger()->addStatus($this->t('Uren zijn goedgekeurd en vrijgegeven voor nacalculatie.'));
    $form_state->setRedirect('brebo_office_core.inzet_time_approval');
  }

  public function correct(array &$form, FormStateInterface $form_state): void {
    $entry = $this->loadEntry($form_state);
    if (!$entry) {
      return;
    }
    $reason = trim((string) $form_state->getValue('reason'));
    if ($reason === '') {
      $form_state->setErrorByName('reason', $this->t('Leg vast waarom de klokuren worden gecorrigeerd.'));
      return;
    }
    $start = $form_state->getValue('start');
    $end = $form_state->getValue('end');
    if (!$start instanceof DrupalDateTime || !$end instanceof DrupalDateTime || $end <= $start) {
      $form_state->setErrorByName('end', $this->t('Eindtijd moet na de starttijd liggen.'));
      return;
    }
    $break = max(0, (int) $form_state->getValue('break_minutes'));
    $hours = round(max(0, $end->getTimestamp() - $start->getTimestamp() - ($break * 60)) / 3600, 2);
    $entry->set('field_brebo_time_start', $start->format('Y-m-d\TH:i:s'));
    $entry->set('field_brebo_time_end', $end->format('Y-m-d\TH:i:s'));
    $entry->set('field_brebo_time_break_min', $break);
    $entry->set('field_brebo_time_hours', $hours);
    $entry->set('field_brebo_time_status', 'Gecorrigeerd');
    $this->saveRevision($entry, 'Uren gecorrigeerd door beoordelaar: ' . $reason);
    $this->messenger()->addWarning($this->t('Correctie opgeslagen. De oorspronkelijke klokregistraties blijven ongewijzigd als auditbewijs.'));
    $form_state->setRedirect('brebo_office_core.inzet_time_review', ['node' => $entry->id()]);
  }

  public function reject(array &$form, FormStateInterface $form_state): void {
    $entry = $this->loadEntry($form_state);
    if (!$entry) {
      return;
    }
    $reason = trim((string) $form_state->getValue('reason'));
    if ($reason === '') {
      $form_state->setErrorByName('reason', $this->t('Geef de reden van afwijzing op.'));
      return;
    }
    $entry->set('field_brebo_time_status', 'Afgewezen');
    $this->saveRevision($entry, 'Uren afgewezen: ' . $reason);
    $this->messenger()->addWarning($this->t('Urenregistratie is afgewezen en niet vrijgegeven voor nacalculatie.'));
    $form_state->setRedirect('brebo_office_core.inzet_time_approval');
  }

  private function saveRevision(NodeInterface $entry, string $message): void {
    $entry->setNewRevision(TRUE);
    $entry->setRevisionUserId((int) $this->currentUser()->id());
    $entry->setRevisionCreationTime(time());
    $entry->setRevisionLogMessage($message);
    $entry->save();
  }

  private function loadEntry(FormStateInterface $form_state): ?NodeInterface {
    $entry = $this->entityTypeManager->getStorage('node')->load((int) $form_state->get('entry_id'));
    if (!$entry instanceof NodeInterface || $entry->bundle() !== 'brebo_time_entry') {
      $form_state->setErrorByName('', $this->t('De urenregistratie kon niet worden geladen.'));
      return NULL;
    }
    return $entry;
  }

  private function dateValue(?string $value): ?DrupalDateTime {
    return $value ? new DrupalDateTime($value, 'UTC') : NULL;
  }

  private function assessment(NodeInterface $entry): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $shift = $entry->get('field_brebo_time_shift')->entity;
    $clockTypes = [];
    $geoStatuses = [];
    if ($shift instanceof NodeInterface) {
      $ids = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'brebo_clock_event')->condition('field_brebo_clock_shift', $shift->id())->execute();
      foreach ($storage->loadMultiple($ids) as $clock) {
        if ($clock instanceof NodeInterface) {
          $clockTypes[] = (string) $clock->get('field_brebo_clock_type')->value;
          $geoStatuses[] = (string) $clock->get('field_brebo_clock_geo_status')->value;
        }
      }
    }
    $budget = $entry->get('field_brebo_time_budget')->entity;
    $budgetHours = $budget instanceof NodeInterface && $budget->hasField('field_brebo_budget_hours') ? (float) ($budget->get('field_brebo_budget_hours')->value ?? 0) : 0.0;
    $approvedHours = 0.0;
    if ($budget instanceof NodeInterface) {
      $ids = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'brebo_time_entry')->condition('field_brebo_time_budget', $budget->id())->condition('field_brebo_time_status', 'Goedgekeurd')->condition('nid', $entry->id(), '<>')->execute();
      foreach ($storage->loadMultiple($ids) as $approved) {
        if ($approved instanceof NodeInterface) {
          $approvedHours += (float) ($approved->get('field_brebo_time_hours')->value ?? 0);
        }
      }
    }
    return $this->control->assess([
      'planned_start' => $shift instanceof NodeInterface ? $shift->get('field_brebo_shift_start')->value : NULL,
      'planned_end' => $shift instanceof NodeInterface ? $shift->get('field_brebo_shift_end')->value : NULL,
      'actual_start' => $entry->get('field_brebo_time_start')->value,
      'actual_end' => $entry->get('field_brebo_time_end')->value,
      'break_minutes' => (int) ($entry->get('field_brebo_time_break_min')->value ?? 0),
      'actual_hours' => (float) ($entry->get('field_brebo_time_hours')->value ?? 0),
      'clock_types' => $clockTypes,
      'geo_statuses' => $geoStatuses,
      'budget_hours' => $budgetHours,
      'approved_budget_hours' => $approvedHours,
    ]);
  }

}
