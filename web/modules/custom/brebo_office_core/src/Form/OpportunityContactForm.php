<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Records a communication moment against an opportunity.
 */
final class OpportunityContactForm extends FormBase {

  private ?NodeInterface $opportunity = NULL;

  public function getFormId(): string {
    return 'brebo_opportunity_contact_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_opportunity') {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }
    $this->opportunity = $node;
    $contact = $node->get('field_brebo_opp_contact_ref')->entity;

    $form['channel'] = [
      '#type' => 'select',
      '#title' => $this->t('Kanaal'),
      '#options' => array_combine(
        ['E-mail', 'WhatsApp', 'Zadarma', 'Mobiele telefoon', 'Fysiek gesprek', 'Vergadering', 'Videogesprek', 'Brief', 'Portaal', 'Overig'],
        ['E-mail', 'WhatsApp', 'Zadarma', 'Mobiele telefoon', 'Fysiek gesprek', 'Vergadering', 'Videogesprek', 'Brief', 'Portaal', 'Overig']
      ),
      '#required' => TRUE,
    ];
    $form['direction'] = [
      '#type' => 'select',
      '#title' => $this->t('Richting'),
      '#options' => ['Inkomend' => $this->t('Inkomend'), 'Uitgaand' => $this->t('Uitgaand'), 'Intern vastgelegd' => $this->t('Intern vastgelegd')],
      '#required' => TRUE,
    ];
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Soort contactmoment'),
      '#options' => array_combine(
        ['Vraag', 'Antwoord', 'Informatie', 'Instructie', 'Besluit', 'Goedkeuring', 'Wijziging', 'Afspraak', 'Klacht', 'Oplevering'],
        ['Vraag', 'Antwoord', 'Informatie', 'Instructie', 'Besluit', 'Goedkeuring', 'Wijziging', 'Afspraak', 'Klacht', 'Oplevering']
      ),
      '#required' => TRUE,
    ];
    $form['datetime'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Datum en tijd'),
      '#default_value' => new DrupalDateTime('now'),
      '#required' => TRUE,
    ];
    $form['contact'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Contactpersoon'),
      '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['brebo_contact']],
      '#default_value' => $contact instanceof NodeInterface ? $contact : NULL,
    ];
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Onderwerp'),
      '#maxlength' => 255,
      '#required' => TRUE,
    ];
    $form['summary'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Samenvatting'),
      '#required' => TRUE,
    ];
    $form['response_required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reactie of vervolgactie vereist'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Contactmoment vastleggen'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->opportunity instanceof NodeInterface) {
      return;
    }
    $organization = $this->opportunity->get('field_brebo_opp_org_ref')->entity;
    $datetime = $form_state->getValue('datetime');
    $dateValue = $datetime instanceof DrupalDateTime
      ? $datetime->format('Y-m-d\\TH:i:s')
      : gmdate('Y-m-d\\TH:i:s');
    $values = [
      'type' => 'brebo_communication',
      'title' => trim((string) $form_state->getValue('subject')),
      'field_brebo_comm_opp_ref' => ['target_id' => $this->opportunity->id()],
      'field_brebo_comm_channel' => (string) $form_state->getValue('channel'),
      'field_brebo_comm_direction' => (string) $form_state->getValue('direction'),
      'field_brebo_comm_type' => (string) $form_state->getValue('type'),
      'field_brebo_comm_datetime' => $dateValue,
      'field_brebo_comm_subject' => trim((string) $form_state->getValue('subject')),
      'field_brebo_comm_summary' => (string) $form_state->getValue('summary'),
      'field_brebo_comm_status' => 'Vastgelegd',
      'field_brebo_response_required' => $form_state->getValue('response_required') ? 1 : 0,
      'field_brebo_responsible_user' => ['target_id' => (int) $this->currentUser()->id()],
      'status' => 1,
    ];
    if ($organization instanceof NodeInterface) {
      $values['field_brebo_comm_org_ref'] = ['target_id' => $organization->id()];
    }
    $contactId = (int) $form_state->getValue('contact');
    if ($contactId > 0) {
      $values['field_brebo_comm_contact_ref'] = ['target_id' => $contactId];
    }
    \Drupal::entityTypeManager()->getStorage('node')->create($values)->save();

    $this->messenger()->addStatus($this->t('Het contactmoment is aan de commerciële kans gekoppeld.'));
    $form_state->setRedirect('brebo_office_core.opportunity_dashboard', ['node' => $this->opportunity->id()]);
  }

}
