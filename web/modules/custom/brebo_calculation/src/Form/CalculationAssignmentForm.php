<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Service\CalculationWorkAssignmentManager;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Assigns a calculation to a BREBO Office user and personal work queue. */
final class CalculationAssignmentForm extends FormBase {

  public function __construct(private readonly CalculationWorkAssignmentManager $assignmentManager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_calculation.work_assignment_manager'));
  }

  public function getFormId(): string {
    return 'brebo_calculation_assignment_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation') {
      throw new \InvalidArgumentException('Calculation expected.');
    }
    $form_state->set('calculation_id', (int) $node->id());
    $form['intro'] = ['#markup' => '<p>Wijs deze calculatie toe. De actie verschijnt direct in Mijn Werkbak van de verantwoordelijke.</p>'];
    $form['owner_uid'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Verantwoordelijke calculator'),
      '#target_type' => 'user',
      '#selection_settings' => ['include_anonymous' => FALSE],
      '#required' => TRUE,
    ];
    $form['due_date'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Deadline'),
      '#date_time_element' => 'none',
      '#required' => FALSE,
    ];
    $form['action'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Actie'),
      '#default_value' => 'Calculatie afronden: ' . $node->label(),
      '#maxlength' => 500,
      '#required' => TRUE,
    ];
    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => ['open' => $this->t('Open'), 'in_progress' => $this->t('In behandeling'), 'waiting' => $this->t('Wacht op informatie'), 'ready' => $this->t('Gereed voor controle')],
      '#default_value' => 'open',
      '#required' => TRUE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Toewijzen aan werkbak'), '#button_type' => 'primary'];
    $form['actions']['cancel'] = ['#type' => 'link', '#title' => $this->t('Terug naar werkbank'), '#url' => \Drupal\Core\Url::fromRoute('brebo_calculation.workbench', ['node' => $node->id()])];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $owner = $form_state->getValue('owner_uid');
    $ownerUid = $owner instanceof UserInterface ? (int) $owner->id() : (int) $owner;
    $due = $form_state->getValue('due_date');
    $dueTimestamp = $due instanceof DrupalDateTime ? $due->getTimestamp() : NULL;
    $calculationId = (int) $form_state->get('calculation_id');
    $this->assignmentManager->save($calculationId, $ownerUid, $dueTimestamp, (string) $form_state->getValue('status'), (string) $form_state->getValue('action'), (int) $this->currentUser()->id());
    $this->messenger()->addStatus($this->t('Calculatie is toegewezen en staat in Mijn Werkbak van de verantwoordelijke.'));
    $form_state->setRedirect('brebo_calculation.workbench', ['node' => $calculationId]);
  }
}
