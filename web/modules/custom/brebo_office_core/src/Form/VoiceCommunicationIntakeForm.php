<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Receives a voice recording as immutable communication source evidence.
 */
final class VoiceCommunicationIntakeForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileUsageInterface $fileUsage,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('file.usage'),
    );
  }

  public function getFormId(): string {
    return 'brebo_office_voice_communication_intake';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['intro'] = [
      '#markup' => '<div class="messages messages--status"><strong>Voice recording als bronbewijs</strong><br>De opname wordt privé opgeslagen, voorzien van een SHA-256-controlewaarde en blijft eerst een niet-verwerkte bron. AI kan daarna een concept maken; alleen een bevoegde gebruiker kan dit formeel vaststellen.</div>',
    ];
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Onderwerp'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['project'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Project (optioneel)'),
      '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['brebo_project']],
    ];
    $form['context'] = [
      '#type' => 'select',
      '#title' => $this->t('Communicatiecontext'),
      '#options' => array_combine(
        ['Organisatieoverstijgend', 'Projectgericht', 'Scopegericht', 'Intern'],
        ['Organisatieoverstijgend', 'Projectgericht', 'Scopegericht', 'Intern'],
      ),
      '#default_value' => 'Projectgericht',
      '#required' => TRUE,
    ];
    $form['occurred_at'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Datum en tijd opname'),
      '#default_value' => new DrupalDateTime('now'),
      '#required' => TRUE,
    ];
    $form['recording'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Opnamebestand'),
      '#upload_location' => 'private://brebo-office/communication/source/' . date('Y-m'),
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'wav mp3 m4a ogg mp4 webm'],
        'FileSizeLimit' => ['fileLimit' => 104857600],
      ],
      '#description' => $this->t('Toegestaan: wav, mp3, m4a, ogg, mp4 of webm; maximaal 100 MB.'),
      '#required' => TRUE,
    ];
    $form['recording_consent'] = [
      '#type' => 'select',
      '#title' => $this->t('Opnamegrondslag'),
      '#options' => array_combine(
        ['Aangekondigd', 'Toestemming vastgelegd', 'Beoordeling vereist'],
        ['Aangekondigd', 'Toestemming vastgelegd', 'Beoordeling vereist'],
      ),
      '#default_value' => 'Beoordeling vereist',
      '#required' => TRUE,
    ];
    $form['source_note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Bronnotitie'),
      '#description' => $this->t('Wie sprak, waar vond het contact plaats en welke context is al zeker?'),
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Opname veilig registreren'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $fids = array_values(array_filter((array) $form_state->getValue('recording')));
    $file = $fids
      ? $this->entityTypeManager->getStorage('file')->load((int) reset($fids))
      : NULL;
    if (!$file instanceof FileInterface) {
      $this->messenger()->addError($this->t('Het opnamebestand kon niet worden geladen.'));
      return;
    }

    $file->setPermanent();
    $file->save();
    $realpath = $this->fileSystem->realpath($file->getFileUri());
    $hash = $realpath && is_readable($realpath) ? hash_file('sha256', $realpath) : '';

    $when = $form_state->getValue('occurred_at');
    if (!$when instanceof DrupalDateTime) {
      $when = new DrupalDateTime('now');
    }
    $when->setTimezone(new \DateTimeZone('UTC'));
    $subject = trim((string) $form_state->getValue('subject'));
    $project_id = (int) ($form_state->getValue('project') ?? 0);
    $summary = trim((string) $form_state->getValue('source_note'));
    $summary = $summary !== ''
      ? $summary
      : 'Voice recording ontvangen; transcriptie en inhoudelijke controle zijn nog niet uitgevoerd.';

    $values = [
      'type' => 'brebo_communication',
      'title' => 'Voice recording – ' . $subject,
      'uid' => $this->currentUser()->id(),
      'status' => 1,
      'field_brebo_comm_direction' => 'Intern vastgelegd',
      'field_brebo_comm_channel' => 'Voice recording',
      'field_brebo_comm_context' => $form_state->getValue('context'),
      'field_brebo_comm_type' => 'Informatie',
      'field_brebo_comm_datetime' => $when->format('Y-m-d\TH:i:s'),
      'field_brebo_comm_subject' => $subject,
      'field_brebo_comm_summary' => $summary,
      'field_brebo_comm_status' => 'Vastgelegd',
      'field_brebo_source_files' => [['target_id' => $file->id()]],
      'field_brebo_source_hash' => $hash,
      'field_brebo_ai_status' => 'Niet verwerkt',
      'field_brebo_recording_consent' => $form_state->getValue('recording_consent'),
      'field_brebo_formal_status' => 'Bron',
      'field_brebo_lens_domain' => 'Regie',
    ];
    if ($project_id > 0) {
      $values['field_brebo_project_ref'] = ['target_id' => $project_id];
    }

    $communication = $this->entityTypeManager->getStorage('node')->create($values);
    $communication->setNewRevision(TRUE);
    $communication->setRevisionLogMessage('Voice recording als oorspronkelijke bron geregistreerd.');
    $communication->save();
    $this->fileUsage->add($file, 'brebo_office_core', 'node', (string) $communication->id());

    $this->messenger()->addStatus($this->t(
      'Voice recording veilig geregistreerd als bron. Controlewaarde: @hash',
      ['@hash' => $hash !== '' ? $hash : $this->t('niet beschikbaar')],
    ));
    $form_state->setRedirect('brebo_office_core.communications');
  }

}
