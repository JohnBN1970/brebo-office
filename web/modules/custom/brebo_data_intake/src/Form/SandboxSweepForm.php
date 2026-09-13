<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Safely removes website-intake test data from the Office sandbox. */
final class SandboxSweepForm extends FormBase {

  private const string SANDBOX_HOST = 'sboffice.brebobv.nl';

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RequestStack $requestStack,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
    );
  }

  public function getFormId(): string {
    return 'brebo_data_intake_sandbox_sweep';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    if (!$this->isSandbox()) {
      $form['blocked'] = [
        '#markup' => '<div class="messages messages--error"><strong>Sandbox sweep is geblokkeerd.</strong> Deze functie is uitsluitend beschikbaar op sboffice.brebobv.nl.</div>',
      ];
      return $form;
    }

    $preview = $this->inventory();
    $form['warning'] = [
      '#markup' => '<div class="messages messages--warning"><strong>Sandbox testdata opruimen.</strong> Alleen website/Europakozijn testintakes en de daardoor aangemaakte leads worden verwijderd. Configuratie, gebruikers, rollen, bronnen en classificaties blijven behouden.</div>',
    ];
    $form['preview'] = [
      '#type' => 'table',
      '#header' => ['Onderdeel', 'Aantal'],
      '#rows' => [
        ['Intake records', $preview['records']],
        ['Ingest runs', $preview['runs']],
        ['Review decisions', $preview['decisions']],
        ['Masterdata candidates', $preview['candidates']],
        ['Bestanden', $preview['files']],
        ['Website - Europakozijn leads', $preview['leads']],
      ],
    ];
    $form['confirmation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bevestiging'),
      '#description' => $this->t('Typ SWEEP om de hierboven getoonde sandboxdata definitief te verwijderen.'),
      '#required' => TRUE,
      '#maxlength' => 16,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['refresh'] = [
      '#type' => 'submit',
      '#value' => $this->t('Voorbeeld vernieuwen'),
      '#submit' => ['::refreshPreview'],
      '#limit_validation_errors' => [],
    ];
    $form['actions']['sweep'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sweep sandbox'),
      '#button_type' => 'danger',
    ];
    return $form;
  }

  public function refreshPreview(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild(TRUE);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->isSandbox()) {
      $form_state->setErrorByName('confirmation', $this->t('Deze actie is buiten de sandbox geblokkeerd.'));
      return;
    }
    if (strtoupper(trim((string) $form_state->getValue('confirmation'))) !== 'SWEEP') {
      $form_state->setErrorByName('confirmation', $this->t('Typ exact SWEEP om door te gaan.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->isSandbox()) {
      throw new \RuntimeException('Sandbox sweep refused outside sboffice.brebobv.nl.');
    }

    $targets = $this->targets();
    $transaction = $this->database->startTransaction();
    try {
      if ($targets['record_ids'] !== []) {
        foreach (['brebo_data_intake_decision', 'brebo_masterdata_candidate'] as $table) {
          if ($this->database->schema()->tableExists($table)) {
            $this->database->delete($table)
              ->condition('record_id', $targets['record_ids'], 'IN')
              ->execute();
          }
        }
        $this->database->delete('brebo_data_record')
          ->condition('id', $targets['record_ids'], 'IN')
          ->execute();
      }

      if ($targets['run_ids'] !== []) {
        $this->database->delete('brebo_data_ingest_run')
          ->condition('id', $targets['run_ids'], 'IN')
          ->execute();
      }

      if ($targets['lead_ids'] !== []) {
        $storage = $this->entityTypeManager->getStorage('node');
        $entities = $storage->loadMultiple($targets['lead_ids']);
        if ($entities !== []) {
          $storage->delete($entities);
        }
      }

      if ($targets['file_ids'] !== []) {
        $storage = $this->entityTypeManager->getStorage('file');
        $entities = $storage->loadMultiple($targets['file_ids']);
        if ($entities !== []) {
          $storage->delete($entities);
        }
      }
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }

    $this->messenger()->addStatus($this->t(
      'Sandbox sweep voltooid: @records intake records, @runs runs, @files bestanden en @leads leads verwijderd.',
      [
        '@records' => count($targets['record_ids']),
        '@runs' => count($targets['run_ids']),
        '@files' => count($targets['file_ids']),
        '@leads' => count($targets['lead_ids']),
      ],
    ));
    $form_state->setRebuild(TRUE);
  }

  /** @return array{records:int,runs:int,decisions:int,candidates:int,files:int,leads:int} */
  private function inventory(): array {
    $targets = $this->targets();
    return [
      'records' => count($targets['record_ids']),
      'runs' => count($targets['run_ids']),
      'decisions' => $this->countByRecordIds('brebo_data_intake_decision', $targets['record_ids']),
      'candidates' => $this->countByRecordIds('brebo_masterdata_candidate', $targets['record_ids']),
      'files' => count($targets['file_ids']),
      'leads' => count($targets['lead_ids']),
    ];
  }

  /** @return array{record_ids:list<int>,run_ids:list<int>,file_ids:list<int>,lead_ids:list<int>} */
  private function targets(): array {
    $recordIds = [];
    $runIds = [];
    $fileIds = [];

    $query = $this->database->select('brebo_data_record', 'r');
    $query->fields('r', ['id', 'run_id', 'payload']);
    $query->condition('r.record_type', 'source_neutral_intake');
    $query->condition('r.payload', '%"classification":"website_project_request"%', 'LIKE');
    $query->condition('r.payload', '%"source":"website"%', 'LIKE');
    foreach ($query->execute() as $row) {
      $recordIds[] = (int) $row->id;
      $runIds[] = (int) $row->run_id;
      $payload = json_decode((string) $row->payload, TRUE);
      $fileId = $payload['envelope']['payload']['file_id'] ?? NULL;
      if (is_numeric($fileId) && (int) $fileId > 0) {
        $fileIds[] = (int) $fileId;
      }
    }

    $leadIds = [];
    $storage = $this->entityTypeManager->getStorage('node');
    $fieldStorage = $this->entityTypeManager->getStorage('field_config');
    if ($fieldStorage->load('node.brebo_opportunity.field_brebo_opp_source') !== NULL) {
      $leadIds = array_map('intval', array_values($storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'brebo_opportunity')
        ->condition('field_brebo_opp_source', 'Website - Europakozijn')
        ->execute()));
    }

    return [
      'record_ids' => array_values(array_unique($recordIds)),
      'run_ids' => array_values(array_unique($runIds)),
      'file_ids' => array_values(array_unique($fileIds)),
      'lead_ids' => array_values(array_unique($leadIds)),
    ];
  }

  /** @param list<int> $recordIds */
  private function countByRecordIds(string $table, array $recordIds): int {
    if ($recordIds === [] || !$this->database->schema()->tableExists($table)) {
      return 0;
    }
    return (int) $this->database->select($table, 't')
      ->condition('record_id', $recordIds, 'IN')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  private function isSandbox(): bool {
    return strtolower((string) $this->requestStack->getCurrentRequest()?->getHost()) === self::SANDBOX_HOST;
  }

}
