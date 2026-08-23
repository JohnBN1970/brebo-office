<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Creates a controlled provisional sum for a project. */
final class ProjectProvisionalSumForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function getFormId(): string {
    return 'brebo_project_cockpit_provisional_sum_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if ($node === NULL || $node->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('BREBO project required.');
    }

    $projectId = (int) $node->id();
    if (!$this->database->schema()->tableExists('brebo_finance_provisional_sum')) {
      $form['warning'] = ['#markup' => '<p><strong>' . $this->t('De stelpost-opslag is nog niet geinstalleerd. Voer eerst de database-updates uit.') . '</strong></p>'];
      return $form;
    }

    $contract = $this->database->select('brebo_finance_project_contract', 'c')
      ->fields('c', ['id'])
      ->condition('project_nid', $projectId)
      ->execute()
      ->fetchAssoc();

    $form['project'] = ['#markup' => '<p><strong>' . $this->t('Project:') . '</strong> ' . $node->label() . '</p>'];
    $form['number'] = ['#type' => 'textfield', '#title' => $this->t('Stelpostnummer'), '#required' => TRUE, '#maxlength' => 64, '#placeholder' => 'SP-001'];
    $form['title'] = ['#type' => 'textfield', '#title' => $this->t('Stelpost'), '#required' => TRUE, '#maxlength' => 255];
    $form['description'] = ['#type' => 'textarea', '#title' => $this->t('Omschrijving')];
    $form['contract_amount'] = ['#type' => 'number', '#title' => $this->t('Contractbedrag excl. btw'), '#required' => TRUE, '#step' => '0.01', '#min' => 0];
    $form['forecast_amount'] = ['#type' => 'number', '#title' => $this->t('Actuele prognose excl. btw'), '#required' => TRUE, '#step' => '0.01', '#min' => 0, '#description' => $this->t('De huidige verwachting. Deze kan later worden vervangen door de werkelijke waarde.')];
    $form['vat_rate'] = ['#type' => 'select', '#title' => $this->t('BTW'), '#options' => ['21' => '21%', '9' => '9%', '0' => '0%'], '#default_value' => '21'];
    $form['source_ref'] = ['#type' => 'textfield', '#title' => $this->t('Bron / contractreferentie'), '#maxlength' => 255];
    $form['notes'] = ['#type' => 'textarea', '#title' => $this->t('Notitie')];

    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Stelpost toevoegen'), '#button_type' => 'primary'];
    $form['actions']['cancel'] = ['#type' => 'link', '#title' => $this->t('Annuleren'), '#url' => Url::fromRoute('brebo_project_cockpit.invoices', ['node' => $projectId]), '#attributes' => ['class' => ['button']]];

    $form_state->set('project_id', $projectId);
    $form_state->set('contract_id', $contract === FALSE ? NULL : (int) $contract['id']);
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    $number = trim((string) $form_state->getValue('number'));
    if ($number === '') {
      return;
    }
    $exists = (int) $this->database->select('brebo_finance_provisional_sum', 's')
      ->condition('project_nid', $projectId)
      ->condition('provisional_sum_number', $number)
      ->countQuery()
      ->execute()
      ->fetchField();
    if ($exists > 0) {
      $form_state->setErrorByName('number', $this->t('Dit stelpostnummer bestaat al binnen het project.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    $contractId = $form_state->get('contract_id');
    $contractAmount = round((float) $form_state->getValue('contract_amount'), 4);
    $forecastAmount = round((float) $form_state->getValue('forecast_amount'), 4);
    $settlement = round($forecastAmount - $contractAmount, 4);
    $vatRate = (string) $form_state->getValue('vat_rate');
    $actor = (int) $this->currentUser()->id();
    $now = time();

    $this->database->insert('brebo_finance_provisional_sum')->fields([
      'project_nid' => $projectId,
      'contract_id' => $contractId,
      'provisional_sum_number' => trim((string) $form_state->getValue('number')),
      'title' => trim((string) $form_state->getValue('title')),
      'description' => trim((string) $form_state->getValue('description')) ?: NULL,
      'status' => 'open',
      'contract_amount_ex_vat' => number_format($contractAmount, 4, '.', ''),
      'forecast_amount_ex_vat' => number_format($forecastAmount, 4, '.', ''),
      'actual_amount_ex_vat' => '0.0000',
      'settlement_amount_ex_vat' => number_format($settlement, 4, '.', ''),
      'approved_settlement_ex_vat' => '0.0000',
      'invoiced_settlement_ex_vat' => '0.0000',
      'paid_settlement_inc_vat' => '0.0000',
      'vat_code' => 'NL_' . $vatRate,
      'vat_rate' => $vatRate,
      'source_ref' => trim((string) $form_state->getValue('source_ref')) ?: NULL,
      'notes' => trim((string) $form_state->getValue('notes')) ?: NULL,
      'created' => $now,
      'created_by' => $actor,
      'changed' => $now,
      'changed_by' => $actor,
    ])->execute();

    $this->messenger()->addStatus($this->t('Stelpost @number is toegevoegd en wordt vanaf nu financieel bewaakt.', ['@number' => $form_state->getValue('number')]));
    $form_state->setRedirect('brebo_project_cockpit.invoices', ['node' => $projectId]);
  }

}
