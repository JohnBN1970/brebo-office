<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\brebo_office_core\Service\IntegrationApiClientInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides the controlled communication processing workbench.
 */
final class CommunicationProcessingForm extends FormBase {

  public function __construct(
    private readonly IntegrationApiClientInterface $integrationApiClient,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_office_core.integration_api_client'),
    );
  }

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
      '#description' => $this->t('Maakt uitsluitend een AI-concept. Menselijke controle en formele vaststelling blijven verplicht.'),
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
    if ($trigger === 'start_ai' && trim((string) $form_state->getValue('transcript')) === '') {
      $form_state->setErrorByName('transcript', $this->t('Een transcriptie of berichttekst is verplicht voor AI-verwerking.'));
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
      $project = $node->get('field_brebo_project_ref')->entity;
      $result = $this->integrationApiClient->analyzeCommunication([
        'communication_id' => (int) $node->id(),
        'project_id' => $project instanceof NodeInterface ? (int) $project->id() : NULL,
        'channel' => $this->value($node, 'field_brebo_comm_channel', 'overig'),
        'subject' => $node->label(),
        'message' => (string) $form_state->getValue('transcript'),
      ]);
      if (($result['state'] ?? NULL) !== 'completed' || !is_array($result['analysis'] ?? NULL)) {
        $this->messenger()->addWarning($this->analysisStateMessage((string) ($result['state'] ?? 'unknown')));
        return;
      }

      $analysis = $result['analysis'];
      $node->set('field_brebo_transcript', (string) $form_state->getValue('transcript'));
      $node->set('field_brebo_ai_summary', $this->analysisText($analysis, ['summary', 'samenvatting']));
      $node->set('field_brebo_ai_decisions', $this->analysisText($analysis, ['decisions', 'commitments', 'besluiten']));
      $node->set('field_brebo_ai_actions', $this->analysisText($analysis, ['suggested_actions', 'actions', 'vervolgacties']));
      $node->set('field_brebo_ai_risks', $this->analysisText($analysis, ['risks', 'risicos']));
      if (isset($analysis['confidence']) && is_numeric($analysis['confidence'])) {
        $node->set('field_brebo_ai_confidence', max(0, min(100, (float) $analysis['confidence'])));
      }
      $node->set('field_brebo_process_log', sprintf(
        'Integration API-analyse %s voltooid; HTTP %s; %s ms. Uitvoer vereist menselijke controle.',
        (string) ($result['checked_at'] ?? gmdate('c')),
        (string) ($result['http_status'] ?? '—'),
        (string) ($result['response_time_ms'] ?? '—'),
      ));
      $node->set('field_brebo_ai_status', 'Controle vereist');
      $node->set('field_brebo_formal_status', 'AI-concept');
      $node->set('field_brebo_processed_at', gmdate('Y-m-d\TH:i:s'));
      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage('AI-concept via de centrale BREBO Integration API aangemaakt; menselijke controle vereist.');
      $node->save();
      $this->messenger()->addStatus($this->t('AI-concept aangemaakt en gereedgezet voor menselijke controle.'));
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

  /**
   * Normalizes a scalar or list from the Integration API analysis.
   */
  private function analysisText(array $analysis, array $keys): string {
    foreach ($keys as $key) {
      if (!array_key_exists($key, $analysis)) {
        continue;
      }
      $value = $analysis[$key];
      if (is_array($value)) {
        $lines = [];
        foreach ($value as $item) {
          if (is_scalar($item)) {
            $lines[] = '- ' . trim((string) $item);
          }
          elseif (is_array($item)) {
            $encoded = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded !== FALSE) {
              $lines[] = '- ' . $encoded;
            }
          }
        }
        return implode("\n", $lines);
      }
      if (is_scalar($value)) {
        return trim((string) $value);
      }
    }
    return '';
  }

  private function analysisStateMessage(string $state): string {
    return match ($state) {
      'not_configured' => (string) $this->t('De Integration API is nog niet geconfigureerd.'),
      'invalid_input' => (string) $this->t('De communicatie is leeg, ongeldig of te lang.'),
      'rejected' => (string) $this->t('De Integration API heeft de analyse geweigerd.'),
      'invalid_response' => (string) $this->t('De Integration API gaf geen veilige, controleerbare respons.'),
      'unreachable' => (string) $this->t('De Integration API is momenteel niet bereikbaar. De communicatie blijft ongewijzigd.'),
      default => (string) $this->t('De AI-analyse kon niet worden voltooid. De communicatie blijft ongewijzigd.'),
    };
  }

}
