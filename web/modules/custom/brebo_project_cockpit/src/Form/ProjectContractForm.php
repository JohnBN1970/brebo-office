<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Registers the project contract before approval. */
final class ProjectContractForm extends FormBase {

  public function __construct(private readonly Connection $database) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function getFormId(): string {
    return 'brebo_project_contract_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('BREBO project required.');
    }
    $projectId = (int) $node->id();
    $contract = $this->loadContract($projectId);
    if (($contract['status'] ?? '') === 'approved') {
      $form['locked'] = ['#markup' => '<p><strong>' . $this->t('Dit projectcontract is goedgekeurd en daarom vergrendeld.') . '</strong></p>'];
      $form['back'] = ['#type' => 'link', '#title' => $this->t('Terug naar Contracten'), '#url' => Url::fromRoute('brebo_project_cockpit.contracts', ['node' => $projectId]), '#attributes' => ['class' => ['button']]];
      return $form;
    }

    $form['intro'] = ['#markup' => '<p>' . $this->t('Registreer hier de contractwaarheid. Goedkeuring gebeurt apart en bevriest ook het commerciële termijnschema.') . '</p>'];
    $form['contract_number'] = ['#type' => 'textfield', '#title' => $this->t('Contractnummer'), '#required' => TRUE, '#maxlength' => 64, '#default_value' => (string) ($contract['contract_number'] ?? '')];
    $form['client_ref'] = ['#type' => 'textfield', '#title' => $this->t('Referentie opdrachtgever'), '#maxlength' => 255, '#default_value' => (string) ($contract['client_ref'] ?? '')];
    $form['contract_date'] = ['#type' => 'date', '#title' => $this->t('Contractdatum'), '#required' => TRUE, '#default_value' => (string) ($contract['contract_date'] ?? date('Y-m-d'))];
    $form['amount_ex_vat'] = ['#type' => 'number', '#title' => $this->t('Contractsom excl. btw'), '#required' => TRUE, '#step' => '0.01', '#min' => 0, '#default_value' => $contract['amount_ex_vat'] ?? '0.00'];
    $form['vat_rate'] = ['#type' => 'select', '#title' => $this->t('Btw'), '#options' => ['21' => '21%', '9' => '9%', '0' => '0%'], '#default_value' => $this->vatRate($contract)];
    $form['payment_term_days'] = ['#type' => 'number', '#title' => $this->t('Betaaltermijn'), '#field_suffix' => $this->t('dagen'), '#required' => TRUE, '#min' => 0, '#max' => 365, '#default_value' => isset($contract['payment_term_days']) ? (int) $contract['payment_term_days'] : 14];
    $form['g_account_applicable'] = ['#type' => 'checkbox', '#title' => $this->t('G-rekening van toepassing'), '#default_value' => !empty($contract['g_account_applicable'])];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Conceptcontract opslaan'), '#button_type' => 'primary'];
    $form['actions']['cancel'] = ['#type' => 'link', '#title' => $this->t('Annuleren'), '#url' => Url::fromRoute('brebo_project_cockpit.contracts', ['node' => $projectId]), '#attributes' => ['class' => ['button']]];
    $form_state->set('project_id', $projectId);
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $number = trim((string) $form_state->getValue('contract_number'));
    $projectId = (int) $form_state->get('project_id');
    $query = $this->database->select('brebo_finance_project_contract', 'c')->condition('contract_number', $number)->condition('project_nid', $projectId, '<>');
    if ((bool) $query->countQuery()->execute()->fetchField()) {
      $form_state->setErrorByName('contract_number', $this->t('Dit contractnummer wordt al voor een ander project gebruikt.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    $current = $this->loadContract($projectId);
    if (($current['status'] ?? '') === 'approved') {
      $this->messenger()->addError($this->t('Het projectcontract is inmiddels goedgekeurd en is niet gewijzigd.'));
      $form_state->setRedirect('brebo_project_cockpit.contracts', ['node' => $projectId]);
      return;
    }

    $amountEx = round((float) $form_state->getValue('amount_ex_vat'), 4);
    $vatRate = (float) $form_state->getValue('vat_rate');
    $vat = round($amountEx * ($vatRate / 100), 4);
    $now = time();
    $uid = (int) $this->currentUser()->id();
    $values = [
      'contract_number' => trim((string) $form_state->getValue('contract_number')),
      'client_ref' => trim((string) $form_state->getValue('client_ref')) ?: NULL,
      'status' => 'draft',
      'amount_ex_vat' => number_format($amountEx, 4, '.', ''),
      'vat_amount' => number_format($vat, 4, '.', ''),
      'amount_inc_vat' => number_format($amountEx + $vat, 4, '.', ''),
      'currency' => 'EUR',
      'g_account_applicable' => (int) (bool) $form_state->getValue('g_account_applicable'),
      'payment_term_days' => max(0, (int) $form_state->getValue('payment_term_days')),
      'contract_date' => (string) $form_state->getValue('contract_date'),
      'content_hash' => NULL,
      'approved' => NULL,
      'approved_by' => NULL,
      'changed' => $now,
      'changed_by' => $uid,
    ];
    if ($current !== []) {
      $this->database->update('brebo_finance_project_contract')->fields($values)->condition('project_nid', $projectId)->execute();
    }
    else {
      $this->database->insert('brebo_finance_project_contract')->fields($values + ['project_nid' => $projectId, 'created' => $now, 'created_by' => $uid])->execute();
    }
    $this->messenger()->addStatus($this->t('Conceptprojectcontract opgeslagen. Goedkeuring bevriest de contractwaarheid.'));
    $form_state->setRedirect('brebo_project_cockpit.contracts', ['node' => $projectId]);
  }

  private function loadContract(int $projectId): array {
    $row = $this->database->select('brebo_finance_project_contract', 'c')->fields('c')->condition('project_nid', $projectId)->execute()->fetchAssoc();
    return is_array($row) ? $row : [];
  }

  private function vatRate(array $contract): string {
    $ex = (float) ($contract['amount_ex_vat'] ?? 0);
    $vat = (float) ($contract['vat_amount'] ?? 0);
    if ($ex <= 0) return '21';
    $rate = round(($vat / $ex) * 100);
    return in_array($rate, [0, 9, 21], TRUE) ? (string) $rate : '21';
  }

}
