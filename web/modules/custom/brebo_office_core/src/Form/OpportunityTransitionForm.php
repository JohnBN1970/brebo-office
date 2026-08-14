<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Provides the guided opportunity stage transition.
 */
final class OpportunityTransitionForm extends FormBase {

  private ?NodeInterface $opportunity = NULL;

  public function getFormId(): string {
    return 'brebo_opportunity_transition_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_opportunity') {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }
    $this->opportunity = $node;
    $stages = [
      'Marketing lead', 'Lead', 'Kans', 'Afspraak', 'Calculatie/offerte',
      'Onderhandeling', 'Gewonnen', 'Verloren',
    ];
    $options = array_combine($stages, $stages);
    $current = (string) $node->get('field_brebo_opp_stage')->value;

    $form['current'] = [
      '#type' => 'item',
      '#title' => $this->t('Huidige fase'),
      '#markup' => $current,
    ];
    $form['stage'] = [
      '#type' => 'select',
      '#title' => $this->t('Nieuwe fase'),
      '#options' => $options,
      '#default_value' => $current,
      '#required' => TRUE,
    ];
    $form['probability'] = [
      '#type' => 'number',
      '#title' => $this->t('Scoringskans (%)'),
      '#default_value' => (int) $node->get('field_brebo_opp_probability')->value,
      '#min' => 0,
      '#max' => 100,
      '#required' => TRUE,
      '#description' => $this->t('Richtwaarden: 5, 10, 25, 40, 60, 80, 100 of 0 procent. Een bewuste afwijking is toegestaan.'),
    ];
    $form['next_action'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Volgende actie'),
      '#default_value' => (string) $node->get('field_brebo_opp_next_action')->value,
      '#maxlength' => 255,
    ];
    $form['next_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Volgende actiedatum'),
      '#default_value' => (string) $node->get('field_brebo_opp_next_date')->value,
    ];
    $form['loss_reason'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Verliesreden'),
      '#default_value' => $node->hasField('field_brebo_opp_loss_reason')
        ? (string) $node->get('field_brebo_opp_loss_reason')->value
        : '',
      '#maxlength' => 255,
      '#states' => ['visible' => [':input[name="stage"]' => ['value' => 'Verloren']]],
    ];
    $form['note'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Toelichting fasewijziging'),
      '#maxlength' => 255,
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Fasewijziging bevestigen'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->opportunity instanceof NodeInterface) {
      return;
    }
    $stage = (string) $form_state->getValue('stage');
    $requires_follow_up = in_array($stage, ['Lead', 'Kans', 'Afspraak', 'Calculatie/offerte', 'Onderhandeling'], TRUE);
    if ($requires_follow_up && trim((string) $form_state->getValue('next_action')) === '') {
      $form_state->setErrorByName('next_action', $this->t('Leg de volgende commerciële actie vast.'));
    }
    if ($requires_follow_up && trim((string) $form_state->getValue('next_date')) === '') {
      $form_state->setErrorByName('next_date', $this->t('Leg de volgende actiedatum vast.'));
    }
    if (in_array($stage, ['Kans', 'Afspraak', 'Calculatie/offerte', 'Onderhandeling', 'Gewonnen'], TRUE)) {
      if ($this->opportunity->get('field_brebo_opp_contact_ref')->isEmpty()) {
        $form_state->setErrorByName('stage', $this->t('Koppel eerst een primaire contactpersoon via Kans bewerken.'));
      }
      if ($this->opportunity->get('field_brebo_opp_value')->isEmpty() || (float) $this->opportunity->get('field_brebo_opp_value')->value <= 0) {
        $form_state->setErrorByName('stage', $this->t('Leg eerst een verwachte omzet groter dan nul vast.'));
      }
      if ($this->opportunity->get('field_brebo_opp_close_date')->isEmpty()) {
        $form_state->setErrorByName('stage', $this->t('Leg eerst de verwachte sluitdatum vast.'));
      }
      if ($this->opportunity->hasField('field_brebo_opp_requirement')
        && $this->opportunity->get('field_brebo_opp_requirement')->isEmpty()) {
        $form_state->setErrorByName('stage', $this->t('Leg eerst de klantbehoefte en scope vast via Kans bewerken.'));
      }
      if ($this->opportunity->hasField('field_brebo_opp_decision_maker')
        && $this->opportunity->get('field_brebo_opp_decision_maker')->isEmpty()) {
        $form_state->setErrorByName('stage', $this->t('Leg eerst de beslisser vast via Kans bewerken.'));
      }
      if ($this->opportunity->hasField('field_brebo_opp_decision_date')
        && $this->opportunity->get('field_brebo_opp_decision_date')->isEmpty()) {
        $form_state->setErrorByName('stage', $this->t('Leg eerst de beslis- of aanbestedingsdatum vast.'));
      }
    }
    if ($stage === 'Verloren' && trim((string) $form_state->getValue('loss_reason')) === '') {
      $form_state->setErrorByName('loss_reason', $this->t('Leg vast waarom deze kans verloren is.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->opportunity instanceof NodeInterface) {
      return;
    }
    $from = (string) $this->opportunity->get('field_brebo_opp_stage')->value;
    $to = (string) $form_state->getValue('stage');
    $probability = (int) $form_state->getValue('probability');
    if ($to === 'Gewonnen') {
      $probability = 100;
    }
    elseif ($to === 'Verloren') {
      $probability = 0;
    }

    $this->opportunity->set('field_brebo_opp_stage', $to);
    $this->opportunity->set('field_brebo_opp_probability', $probability);
    $this->opportunity->set('field_brebo_opp_next_action', trim((string) $form_state->getValue('next_action')));
    $this->opportunity->set('field_brebo_opp_next_date', (string) $form_state->getValue('next_date'));
    $this->opportunity->set('field_brebo_opp_active', in_array($to, ['Gewonnen', 'Verloren'], TRUE) ? 0 : 1);
    $this->opportunity->set('field_brebo_opp_loss_reason', $to === 'Verloren' ? trim((string) $form_state->getValue('loss_reason')) : '');
    $this->opportunity->set('field_brebo_opp_stage_note', trim((string) $form_state->getValue('note')));
    $this->opportunity->setNewRevision(TRUE);
    $this->opportunity->setRevisionLogMessage('Funnelfase gewijzigd van ' . $from . ' naar ' . $to . '.');
    $this->opportunity->save();

    \Drupal::entityTypeManager()->getStorage('node')->create([
      'type' => 'brebo_opportunity_event',
      'title' => $this->opportunity->label() . ': ' . $from . ' → ' . $to,
      'field_brebo_event_opp_ref' => ['target_id' => $this->opportunity->id()],
      'field_brebo_event_from_stage' => $from,
      'field_brebo_event_to_stage' => $to,
      'field_brebo_event_user' => ['target_id' => (int) $this->currentUser()->id()],
      'field_brebo_event_datetime' => gmdate('Y-m-d\\TH:i:s'),
      'field_brebo_event_note' => trim((string) $form_state->getValue('note')),
      'status' => 1,
    ])->save();

    $this->messenger()->addStatus($this->t('De funnel is bijgewerkt van @from naar @to.', ['@from' => $from, '@to' => $to]));
    $form_state->setRedirect('brebo_office_core.opportunity_dashboard', ['node' => $this->opportunity->id()]);
  }

}
