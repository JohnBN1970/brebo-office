<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Form;

use Drupal\brebo_data_intake\Service\SourceNeutralIntakeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Manual upload adapter for the BREBO source-neutral intake pipeline. */
final class SourceNeutralUploadForm extends FormBase {

  public function __construct(private readonly SourceNeutralIntakeManager $intakeManager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_data_intake.source_neutral_intake_manager'));
  }

  public function getFormId(): string {
    return 'brebo_data_intake_source_neutral_upload';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['intro'] = [
      '#markup' => '<p><strong>Centrale BREBO-intake.</strong> Upload een bronbestand, geef aan wat het is en koppel het waar mogelijk. De intake routeert daarna naar de eigenaar van het bedrijfsproces.</p>',
    ];
    $form['classification'] = [
      '#type' => 'select',
      '#title' => $this->t('Wat is dit?'),
      '#required' => TRUE,
      '#options' => [
        'purchase_invoice' => $this->t('Inkoopfactuur'),
        'project_communication' => $this->t('Projectcommunicatie'),
        'document' => $this->t('Document'),
        'request' => $this->t('Aanvraag / offerteaanvraag'),
        'relationship_message' => $this->t('Relatiebericht'),
        'other' => $this->t('Overig / eerst beoordelen'),
      ],
    ];
    $form['source_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Bronbestand'),
      '#required' => TRUE,
      '#upload_location' => 'private://brebo-intake/',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'pdf png jpg jpeg webp doc docx xls xlsx csv txt eml msg'],
      ],
      '#description' => $this->t('Het originele bestand blijft de bron; vakmodules krijgen een verwijzing, geen losse kopie.'),
    ];
    $form['project_nid'] = [
      '#type' => 'number',
      '#title' => $this->t('Projectnummer (intern, optioneel)'),
      '#min' => 1,
    ];
    $form['supplier_ref'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Leverancier / relatie (optioneel)'),
      '#maxlength' => 255,
    ];
    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Toelichting'),
      '#rows' => 3,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Naar centrale intake'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $fids = array_values(array_filter((array) $form_state->getValue('source_file')));
    $file = $fids ? File::load((int) $fids[0]) : NULL;
    if (!$file) {
      $this->messenger()->addError($this->t('Het bronbestand kon niet worden geladen.'));
      return;
    }

    $file->setPermanent();
    $file->save();
    $uri = $file->getFileUri();
    $sourceId = hash('sha256', implode('|', ['upload', (string) $file->id(), $uri, (string) $file->getSize()]));
    $canonical = [];
    if ((int) $form_state->getValue('project_nid') > 0) {
      $canonical['project_nid'] = (int) $form_state->getValue('project_nid');
    }
    if (trim((string) $form_state->getValue('supplier_ref')) !== '') {
      $canonical['supplier_ref'] = trim((string) $form_state->getValue('supplier_ref'));
    }

    $result = $this->intakeManager->intake([
      'source' => 'upload',
      'source_record_id' => $sourceId,
      'classification' => (string) $form_state->getValue('classification'),
      'confidence' => 1.0,
      'canonical' => $canonical,
      'payload' => [
        'filename' => $file->getFilename(),
        'file_id' => (int) $file->id(),
        'uri' => $uri,
        'mime_type' => $file->getMimeType(),
        'size' => (int) $file->getSize(),
        'notes' => trim((string) $form_state->getValue('notes')),
      ],
      'attachments' => [[
        'file_id' => (int) $file->id(),
        'filename' => $file->getFilename(),
        'uri' => $uri,
        'mime_type' => $file->getMimeType(),
        'size' => (int) $file->getSize(),
      ]],
      'received_at' => time(),
      'actor_uid' => (int) $this->currentUser()->id(),
    ]);

    $state = (string) ($result['state'] ?? 'review_required');
    if ($state === 'review_required') {
      $this->messenger()->addWarning($this->t('Upload is veilig ontvangen en staat klaar voor beoordeling.'));
    }
    else {
      $this->messenger()->addStatus($this->t('Upload is via de centrale intake verwerkt: @state.', ['@state' => $state]));
    }
  }

}
