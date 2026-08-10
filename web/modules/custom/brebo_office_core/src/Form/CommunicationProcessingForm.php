<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides the controlled communication processing workbench.
 */
final class CommunicationProcessingForm extends FormBase {

  /**
   * Communication currently being processed.
   */
  private ?NodeInterface $communication = NULL;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'brebo_office_communication_processing';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if ($node instanceof NodeInterface && $node->bundle() === 'brebo_communication') {
      $form_state->set('communication_nid', (int) $node->id());
    }
    else {
      $node = $this->restoreCommunication($form_state);
    }
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_communication') {
      throw new NotFoundHttpException();
    }
    if (!$node->access('update')) {
      throw new AccessDeniedHttpException();
    }
    $this->communication = $node;
    $form['#attributes']['class'][] = 'brebo-processing-workbench';

    $project = $node->get('field_brebo_project_ref')->entity;
    $source_files = [];
    if ($node->hasField('field_brebo_source_files')) {
      foreach ($node->get('field_brebo_source_files')->referencedEntities() as $file) {
        $source_files[] = $file->getFilename();
      }
    }

    $form['context'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-processing-context', 'brebo-processing-workbench__wide']],
      'title' => ['#markup' => '<h2>' . $node->label() . '</h2>'],
      'meta' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Project: @project', ['@project' => $project ? $project->label() : '—']),
          $this->t('Kanaal: @channel', ['@channel' => $this->value($node, 'field_brebo_comm_channel')]),
          $this->t('Formele status: @status', ['@status' => $this->value($node, 'field_brebo_formal_status')]),
          $this->t('AI-status: @status', ['@status' => $this->value($node, 'field_brebo_ai_status')]),
        ],
      ],
    ];

    $form['source'] = [
      '#type' => 'details',
      '#title' => $this->t('Bron en integriteit'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['brebo-processing-workbench__wide']],
      'files' => [
        '#theme' => 'item_list',
        '#title' => $this->t('Bronbestanden'),
        '#items' => $source_files ?: [$this->t('Geen bronbestand gekoppeld.')],
      ],
      'hash' => [
        '#type' => 'item',
        '#title' => $this->t('SHA-256'),
        '#markup' => $this->value($node, 'field_brebo_source_hash'),
      ],
      'consent' => [
        '#type' => 'item',
        '#title' => $this->t('Opnamegrondslag'),
        '#markup' => $this->value($node, 'field_brebo_recording_consent'),
      ],
    ];

    $form['transcript'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Transcriptie'),
      '#default_value' => $this->value($node, 'field_brebo_transcript', ''),
      '#rows' => 14,
      '#wrapper_attributes' => ['class' => ['brebo-processing-workbench__wide']],
      '#description' => $this->t('De volledige controleerbare weergave van het gesprek of overleg.'),
    ];
    $form['summary'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Samenvatting'),
      '#default_value' => $this->value($node, 'field_brebo_ai_summary', ''),
      '#rows' => 5,
      '#wrapper_attributes' => ['class' => ['brebo-processing-card']],
    ];
    $form['decisions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Besluiten en toezeggingen'),
      '#default_value' => $this->value($node, 'field_brebo_ai_decisions', ''),
      '#rows' => 5,
      '#wrapper_attributes' => ['class' => ['brebo-processing-card']],
    ];
    $form['actions_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Acties, eigenaars en termijnen'),
      '#default_value' => $this->value($node, 'field_brebo_ai_actions', ''),
      '#rows' => 5,
      '#wrapper_attributes' => ['class' => ['brebo-processing-card']],
    ];
    $form['risks'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Risico’s en onzekerheden'),
      '#default_value' => $this->value($node, 'field_brebo_ai_risks', ''),
      '#rows' => 5,
      '#wrapper_attributes' => ['class' => ['brebo-processing-card']],
    ];
    $form['confidence'] = [
      '#type' => 'number',
      '#title' => $this->t('AI-betrouwbaarheid'),
      '#default_value' => $this->value($node, 'field_brebo_ai_confidence', ''),
      '#min' => 0,
      '#max' => 100,
      '#step' => 0.01,
      '#description' => $this->t('Technische indicatie; menselijke controle blijft verplicht.'),
      '#wrapper_attributes' => ['class' => ['brebo-processing-card']],
    ];
    $form['process_log'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Verwerkings- en controlenotitie'),
      '#default_value' => $this->value($node, 'field_brebo_process_log', ''),
      '#rows' => 4,
      '#wrapper_attributes' => ['class' => ['brebo-processing-card']],
    ];
    $form['reviewer_note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Controleverklaring'),
      '#default_value' => $this->value($node, 'field_brebo_reviewer_note', ''),
      '#rows' => 4,
      '#description' => $this->t('Leg correcties, onzekerheden en de motivering voor vaststelling vast.'),
      '#wrapper_attributes' => ['class' => ['brebo-processing-workbench__wide']],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['brebo-processing-workbench__wide']],
    ];
    $form['actions']['start_ai'] = [
      '#type' => 'submit',
      '#name' => 'start_ai',
      '#value' => $this->t('AI-verwerking starten'),
      '#button_type' => 'primary',
      '#disabled' => TRUE,
      '#description' => $this->t('Tijdelijk geblokkeerd: AI-verwerking moet via de centrale BREBO Integration API lopen.'),
    ];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#name' => 'save_concept',
      '#value' => $this->t('Concept opslaan'),
      '#button_type' => 'secondary',
    ];
    $form['actions']['review'] = [
      '#type' => 'submit',
      '#name' => 'mark_review',
      '#value' => $this->t('Gereedzetten voor controle'),
    ];
    $form['actions']['establish'] = [
      '#type' => 'submit',
      '#name' => 'formally_establish',
      '#value' => $this->t('Controleren en formeel vaststellen'),
      '#button_type' => 'primary',
    ];

    $form['#tree'] = TRUE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $this->restoreCommunication($form_state);
    $trigger = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
    if ($trigger === 'start_ai') {
      $form_state->setErrorByName('start_ai', $this->t('AI-verwerking is geblokkeerd totdat de centrale BREBO Integration API beschikbaar is.'));
      return;
    }
    if ($trigger === 'formally_establish'
      && trim((string) $form_state->getValue('reviewer_note')) === '') {
      $form_state->setErrorByName('reviewer_note', $this->t('Een controleverklaring is verplicht bij formele vaststelling.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->restoreCommunication($form_state);
    if (!$this->communication instanceof NodeInterface) {
      return;
    }
    $node = $this->communication;
    $trigger = (string) ($form_state->getTriggeringElement()['#name'] ?? 'save_concept');

    if ($trigger === 'start_ai') {
      $has_source = $node->hasField('field_brebo_source_files') && !$node->get('field_brebo_source_files')->isEmpty();
      $has_transcript = trim((string) $form_state->getValue('transcript')) !== '';
      if (!$has_source && !$has_transcript) {
        $this->messenger()->addError($this->t('Voeg eerst een opnamebestand of transcriptie toe.'));
        return;
      }
      if ($has_transcript) {
        $node->set('field_brebo_transcript', (string) $form_state->getValue('transcript'));
      }
      $node->set('field_brebo_ai_status', 'Wachtrij');
      $node->set('field_brebo_formal_status', 'Bron');
      $node->set('field_brebo_process_log', 'In wachtrij geplaatst voor automatische transcriptie en BREBO-extractie.');
      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage('Communicatie in de gecontroleerde AI-verwerkingswachtrij geplaatst.');
      $node->save();
      \Drupal::queue('brebo_communication_ai')->createItem(['nid' => (int) $node->id()]);
      $this->messenger()->addStatus($this->t('Communicatie staat in de AI-wachtrij. De uitkomst wordt altijd ter menselijke controle aangeboden.'));
      $form_state->setRebuild(TRUE);
      return;
    }
    $node->set('field_brebo_transcript', (string) $form_state->getValue('transcript'));
    $node->set('field_brebo_ai_summary', (string) $form_state->getValue('summary'));
    $node->set('field_brebo_ai_decisions', (string) $form_state->getValue('decisions'));
    $node->set('field_brebo_ai_actions', (string) $form_state->getValue('actions_text'));
    $node->set('field_brebo_ai_risks', (string) $form_state->getValue('risks'));
    $node->set('field_brebo_ai_confidence', $form_state->getValue('confidence'));
    $node->set('field_brebo_process_log', (string) $form_state->getValue('process_log'));
    $node->set('field_brebo_reviewer_note', (string) $form_state->getValue('reviewer_note'));
    $node->set('field_brebo_processed_at', gmdate('Y-m-d\TH:i:s'));

    if ($trigger === 'mark_review') {
      $node->set('field_brebo_ai_status', 'Controle vereist');
      $node->set('field_brebo_formal_status', 'AI-concept');
    }
    elseif ($trigger === 'formally_establish') {
      $node->set('field_brebo_ai_status', 'Bevestigd');
      $node->set('field_brebo_formal_status', 'Vastgesteld');
      $node->set('field_brebo_reviewed_by', $this->currentUser()->id());
      $node->set('field_brebo_reviewed_at', gmdate('Y-m-d\TH:i:s'));
    }
    else {
      $node->set('field_brebo_ai_status', 'Concept gemaakt');
      if ($this->value($node, 'field_brebo_formal_status') !== 'Vastgesteld') {
        $node->set('field_brebo_formal_status', 'AI-concept');
      }
    }

    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Communicatie inhoudelijk verwerkt via de BREBO-verwerkingswerkbank.');
    $node->save();

    $this->messenger()->addStatus($trigger === 'formally_establish'
      ? $this->t('Communicatie gecontroleerd en formeel vastgesteld.')
      : $this->t('Communicatieverwerking opgeslagen.'));

    $project = $node->get('field_brebo_project_ref')->entity;
    if ($project instanceof NodeInterface) {
      $form_state->setRedirect(
        'brebo_office_core.project_dashboard',
        ['node' => $project->id()],
        ['query' => ['comm_view' => $trigger === 'formally_establish' ? 'contacts' : 'review'], 'fragment' => 'tab-communication']
      );
    }
    else {
      $form_state->setRedirect('brebo_office_core.communication_dashboard');
    }
  }

  /**
   * Restores the communication context after a form rebuild.
   */
  private function restoreCommunication(FormStateInterface $form_state): ?NodeInterface {
    if ($this->communication instanceof NodeInterface) {
      return $this->communication;
    }
    $nid = (int) $form_state->get('communication_nid');
    if ($nid <= 0) {
      return NULL;
    }
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    if ($node instanceof NodeInterface && $node->bundle() === 'brebo_communication') {
      $this->communication = $node;
      return $node;
    }
    return NULL;
  }

  /**
   * Returns a scalar field value with a fallback.
   */
  private function value(NodeInterface $node, string $field_name, string $fallback = '—'): string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return $fallback;
    }
    return (string) ($node->get($field_name)->value ?? $fallback);
  }

}
