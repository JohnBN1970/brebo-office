<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Adds a timestamped internal note to an opportunity dossier.
 */
final class OpportunityNoteForm extends FormBase {

  private ?NodeInterface $opportunity = NULL;

  public function getFormId(): string {
    return 'brebo_opportunity_note_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_opportunity') {
      throw new NotFoundHttpException();
    }
    $this->opportunity = $node;

    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Onderwerp'),
      '#maxlength' => 255,
      '#required' => TRUE,
    ];
    $form['note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Interne notitie'),
      '#description' => $this->t('Deze tekst is interne dossierinformatie en wordt niet als klantcontact beschouwd.'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Notitie toevoegen'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->opportunity instanceof NodeInterface) {
      return;
    }
    $organization = $this->opportunity->get('field_brebo_opp_org_ref')->entity;
    $values = [
      'type' => 'brebo_communication',
      'title' => trim((string) $form_state->getValue('subject')),
      'field_brebo_comm_opp_ref' => ['target_id' => $this->opportunity->id()],
      'field_brebo_comm_channel' => 'Interne notitie',
      'field_brebo_comm_direction' => 'Intern vastgelegd',
      'field_brebo_comm_type' => 'Informatie',
      'field_brebo_comm_datetime' => gmdate('Y-m-d\\TH:i:s'),
      'field_brebo_comm_subject' => trim((string) $form_state->getValue('subject')),
      'field_brebo_comm_summary' => (string) $form_state->getValue('note'),
      'field_brebo_comm_status' => 'Vastgelegd',
      'field_brebo_responsible_user' => ['target_id' => (int) $this->currentUser()->id()],
      'status' => 1,
    ];
    if ($organization instanceof NodeInterface) {
      $values['field_brebo_comm_org_ref'] = ['target_id' => $organization->id()];
    }
    \Drupal::entityTypeManager()->getStorage('node')->create($values)->save();

    $this->messenger()->addStatus($this->t('De interne notitie is aan de kans toegevoegd.'));
    $form_state->setRedirect('brebo_office_core.opportunity_dashboard', ['node' => $this->opportunity->id()]);
  }

}
