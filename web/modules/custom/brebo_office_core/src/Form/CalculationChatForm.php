<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Adds a short collaboration message to a calculation.
 */
final class CalculationChatForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'brebo_calculation_chat_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation') {
      return [];
    }

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Nieuw bericht'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Schrijf een bericht bij deze calculatie…'),
      '#required' => TRUE,
      '#rows' => 3,
      '#maxlength' => 4000,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Bericht plaatsen'),
      '#button_type' => 'primary',
    ];
    $form_state->set('calculation_id', (int) $node->id());

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $message = trim((string) $form_state->getValue('message'));
    if ($message === '') {
      $form_state->setErrorByName('message', $this->t('Voer een bericht in.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $calculation_id = (int) $form_state->get('calculation_id');
    $calculation = \Drupal::entityTypeManager()->getStorage('node')->load($calculation_id);
    if (!$calculation instanceof NodeInterface || !$calculation->access('update')) {
      $this->messenger()->addError($this->t('U mag bij deze calculatie geen bericht plaatsen.'));
      return;
    }

    \Drupal::database()->insert('brebo_calculation_chat')
      ->fields([
        'calculation_id' => $calculation_id,
        'uid' => (int) $this->currentUser()->id(),
        'message' => trim((string) $form_state->getValue('message')),
        'created' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    $this->messenger()->addStatus($this->t('Bericht geplaatst.'));
    $form_state->setRedirect('brebo_office_core.calculation_dashboard', ['node' => $calculation_id]);
  }

}
