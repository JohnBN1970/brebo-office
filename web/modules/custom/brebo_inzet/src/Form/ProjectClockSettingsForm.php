<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Edits project-wide clocking policy for BREBO Inzet.
 */
final class ProjectClockSettingsForm extends FormBase {

  private ?NodeInterface $project = NULL;

  public function getFormId(): string {
    return 'brebo_inzet_project_clock_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $this->project = $node;

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Projectbrede instellingen voor de BREBO Inzet-klok. Kloklocaties en hun toegestane afstand beheer je apart onder Kloklocaties.') . '</p>',
    ];
    $form['workday_start'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Standaard werktijd vanaf'),
      '#required' => TRUE,
      '#maxlength' => 5,
      '#size' => 8,
      '#default_value' => (string) ($node->get('field_brebo_workday_start')->value ?: '07:00'),
      '#description' => $this->t('Gebruik HH:MM, bijvoorbeeld 07:00. Een dienst kan deze tijd later overschrijven.'),
    ];
    $form['workday_end'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Standaard werktijd tot'),
      '#required' => TRUE,
      '#maxlength' => 5,
      '#size' => 8,
      '#default_value' => (string) ($node->get('field_brebo_workday_end')->value ?: '16:00'),
      '#description' => $this->t('Gebruik HH:MM, bijvoorbeeld 16:00. Een dienst kan deze tijd later overschrijven.'),
    ];
    $form['pause_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Pauzeregistratie'),
      '#options' => [
        'off' => $this->t('Uit'),
        'optional' => $this->t('Optioneel'),
        'required' => $this->t('Verplicht'),
      ],
      '#default_value' => (string) ($node->get('field_brebo_pause_mode')->value ?: 'off'),
      '#description' => $this->t('Bepaalt of pauzeknoppen op het mobiele klokscherm verborgen, optioneel of verplicht zijn.'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Instellingen opslaan'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    foreach (['workday_start', 'workday_end'] as $key) {
      $value = trim((string) $form_state->getValue($key));
      if (!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $value)) {
        $form_state->setErrorByName($key, $this->t('Gebruik een geldige tijd in HH:MM-formaat.'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->project instanceof NodeInterface) {
      throw new \RuntimeException('Project context ontbreekt bij opslaan van klokinstellingen.');
    }

    $this->project->set('field_brebo_workday_start', trim((string) $form_state->getValue('workday_start')));
    $this->project->set('field_brebo_workday_end', trim((string) $form_state->getValue('workday_end')));
    $this->project->set('field_brebo_pause_mode', (string) $form_state->getValue('pause_mode'));
    $this->project->setNewRevision(TRUE);
    $this->project->setRevisionLogMessage('Projectbrede Inzet-klokinstellingen bijgewerkt.');
    $this->project->save();

    $this->messenger()->addStatus($this->t('Klokinstellingen opgeslagen.'));
    $form_state->setRedirect('brebo_inzet.project_clock_settings', ['node' => $this->project->id()]);
  }

}
