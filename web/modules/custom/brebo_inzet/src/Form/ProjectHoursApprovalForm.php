<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Form;

use Drupal\brebo_finance\Service\LabourProductivityManager;
use Drupal\brebo_inzet\Service\PersonnelActualHoursManager;
use Drupal\brebo_inzet\Service\PersonnelAssignmentComparison;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Reviews clocked hours before they become Finance actuals. */
final class ProjectHoursApprovalForm extends FormBase {
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PersonnelAssignmentComparison $comparison,
    private readonly PersonnelActualHoursManager $actualHours,
    private readonly LabourProductivityManager $labourProductivity,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('brebo_inzet.personnel_assignment_comparison'),
      $container->get('brebo_inzet.personnel_actual_hours_manager'),
      $container->get('brebo_finance.labour_productivity_manager'),
    );
  }

  public function getFormId(): string { return 'brebo_inzet_project_hours_approval'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(TRUE)
      ->condition('type', 'brebo_personnel_assignment')
      ->condition('field_brebo_project_ref', (int) $node->id())
      ->condition('field_brebo_assignment_status', 'cancelled', '<>')
      ->sort('field_brebo_plan_date', 'DESC')->execute();

    $options = [];
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $assignment) {
      if (!$assignment instanceof NodeInterface || !$assignment->access('view')) continue;
      $actual = $this->comparison->compare($assignment);
      if ((float) $actual['clocked_hours'] <= 0 || (bool) $actual['open_session']) continue;
      $person = $assignment->get('field_brebo_plan_user')->entity;
      $id = (int) $assignment->id();
      $reviewStatus = (string) ($assignment->get('field_brebo_actual_status')->value ?? 'open');
      $options[$id] = '';
      $rows[$id] = [
        'person' => ['#plain_text' => $person?->label() ?? 'Onbekende medewerker'],
        'date' => ['#plain_text' => (string) ($assignment->get('field_brebo_plan_date')->value ?? '')],
        'planned' => ['#plain_text' => number_format((float) $actual['planned_hours'], 2, ',', '.') . ' u'],
        'clocked' => ['#plain_text' => number_format((float) $actual['clocked_hours'], 2, ',', '.') . ' u'],
        'delta' => ['#plain_text' => number_format((float) $actual['delta_hours'], 2, ',', '.') . ' u'],
        'state' => ['#plain_text' => (string) $actual['state']],
        'review' => ['#plain_text' => match ($reviewStatus) {
          'approved' => 'Goedgekeurd',
          'worked' => 'Ingediend',
          default => 'Nog te beoordelen',
        }],
      ];
    }

    $form['intro'] = ['#markup' => '<div class="brebo-page-header__main"><p class="brebo-page-header__eyebrow">BREBO PERSONEEL</p><h1>Uren beoordelen</h1><p>Controleer en keur de werkelijke uren operationeel goed. Finance wordt automatisch gekoppeld zodra een arbeidsbudget beschikbaar is.</p></div>'];
    $form['assignments'] = [
      '#type' => 'tableselect', '#header' => [
        'person' => $this->t('Medewerker'), 'date' => $this->t('Datum'),
        'planned' => $this->t('Gepland'), 'clocked' => $this->t('Geklokt'),
        'delta' => $this->t('Verschil'), 'state' => $this->t('Controle'),
        'review' => $this->t('Status'),
      ], '#options' => $rows, '#empty' => $this->t('Geen afgesloten klokuren beschikbaar om te beoordelen.'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit_hours'] = ['#type' => 'submit', '#value' => $this->t('Selectie indienen'), '#submit' => ['::submitHours']];
    $form['actions']['approve_hours'] = ['#type' => 'submit', '#value' => $this->t('Selectie goedkeuren'), '#button_type' => 'primary', '#submit' => ['::approveHours']];
    $form_state->set('project_id', (int) $node->id());
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  public function submitHours(array &$form, FormStateInterface $form_state): void { $this->process($form_state, FALSE); }
  public function approveHours(array &$form, FormStateInterface $form_state): void { $this->process($form_state, TRUE); }

  private function process(FormStateInterface $form_state, bool $approve): void {
    $ids = array_values(array_filter(array_map('intval', (array) $form_state->getValue('assignments'))));
    if ($ids === []) { $this->messenger()->addWarning($this->t('Selecteer minimaal één urenregel.')); return; }
    $done = 0;
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($ids) as $assignment) {
      if (!$assignment instanceof NodeInterface) continue;
      $projectId = (int) $form_state->get('project_id');
      if ((int) ($assignment->get('field_brebo_project_ref')->target_id ?? 0) !== $projectId || !$assignment->access('view')) {
        $this->messenger()->addError($this->t('Urenregel @id hoort niet bij dit project of is niet toegankelijk.', ['@id' => $assignment->id()]));
        continue;
      }
      try {
        $reviewStatus = (string) ($assignment->get('field_brebo_actual_status')->value ?? 'open');
        if ($approve && $reviewStatus !== 'worked') {
          throw new \UnexpectedValueException('Alleen ingediende uren kunnen worden goedgekeurd.');
        }
        if (!$approve && $reviewStatus === 'approved') {
          throw new \UnexpectedValueException('Goedgekeurde uren kunnen niet opnieuw worden ingediend.');
        }
        $approve ? $this->actualHours->approve($assignment, (int) $this->currentUser()->id()) : $this->actualHours->submit($assignment, (int) $this->currentUser()->id());
        $done++;
      }
      catch (\Throwable $e) {
        $this->messenger()->addError($this->t('Urenregel @id kon niet worden verwerkt: @message', ['@id' => $assignment->id(), '@message' => $e->getMessage()]));
      }
    }
    $this->messenger()->addStatus($this->t('@count urenregel(s) verwerkt.', ['@count' => $done]));
    $form_state->setRedirect('brebo_inzet.project_hours_control', ['node' => (int) $form_state->get('project_id')]);
  }
}
