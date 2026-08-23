<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Builds a controlled sales-invoice draft from approved project sources. */
final class ProjectSalesInvoiceDraftForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function getFormId(): string {
    return 'brebo_project_cockpit_sales_invoice_draft_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if ($node === NULL || $node->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('BREBO project required.');
    }
    if (!$this->database->schema()->tableExists('brebo_finance_sales_invoice_draft')) {
      $form['warning'] = ['#markup' => '<p><strong>' . $this->t('Factuurconcept-opslag ontbreekt. Voer eerst database-updates uit.') . '</strong></p>'];
      return $form;
    }

    $projectId = (int) $node->id();
    $sources = $this->sourceOptions($projectId);
    $today = date('Y-m-d');
    $due = date('Y-m-d', strtotime('+30 days'));

    $form['intro'] = ['#markup' => '<p>' . $this->t('Maak een factuurconcept op basis van factureerbare termijnen, goedgekeurd meerwerk en goedgekeurde stelpostverrekening. Dit maakt nog geen officiële Moneybird-factuur.') . '</p>'];
    $form['invoice_date'] = ['#type' => 'date', '#title' => $this->t('Factuurdatum'), '#required' => TRUE, '#default_value' => $today];
    $form['due_date'] = ['#type' => 'date', '#title' => $this->t('Vervaldatum'), '#required' => TRUE, '#default_value' => $due];
    $form['description'] = ['#type' => 'textfield', '#title' => $this->t('Omschrijving'), '#maxlength' => 255, '#default_value' => $this->t('Projectfactuur @project', ['@project' => $node->label()])];
    $form['sources'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Opnemen in factuurconcept'),
      '#options' => $sources,
      '#required' => TRUE,
      '#description' => $this->t('Alleen bronnen die op dit moment contractueel factureerbaar zijn worden aangeboden.'),
    ];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Factuurconcept maken'), '#button_type' => 'primary'];
    $form['actions']['cancel'] = ['#type' => 'link', '#title' => $this->t('Annuleren'), '#url' => Url::fromRoute('brebo_project_cockpit.invoices', ['node' => $projectId]), '#attributes' => ['class' => ['button']]];
    $form_state->set('project_id', $projectId);
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $selected = array_values(array_filter($form_state->getValue('sources') ?? []));
    if ($selected === []) {
      $form_state->setErrorByName('sources', $this->t('Selecteer minimaal één factureerbare bron.'));
    }
    if ((string) $form_state->getValue('due_date') < (string) $form_state->getValue('invoice_date')) {
      $form_state->setErrorByName('due_date', $this->t('De vervaldatum kan niet vóór de factuurdatum liggen.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    $selected = array_values(array_filter($form_state->getValue('sources') ?? []));
    $actor = (int) $this->currentUser()->id();
    $lines = [];

    foreach ($selected as $sourceKey) {
      [$type, $id] = array_pad(explode(':', (string) $sourceKey, 2), 2, NULL);
      if ($type === NULL || $id === NULL) {
        continue;
      }
      $lines = array_merge($lines, $this->sourceLines($projectId, $type, (int) $id));
    }
    if ($lines === []) {
      throw new \RuntimeException('No invoiceable lines could be built from the selected sources.');
    }

    $totals = ['ex' => 0.0, 'vat' => 0.0, 'inc' => 0.0];
    foreach ($lines as $line) {
      $totals['ex'] += (float) $line['amount_ex_vat'];
      $totals['vat'] += (float) $line['vat_amount'];
      $totals['inc'] += (float) $line['amount_inc_vat'];
    }
    $now = time();
    $draftNumber = 'CON-' . date('Ymd-His') . '-' . $projectId;
    $transaction = $this->database->startTransaction();
    try {
      $draftId = (int) $this->database->insert('brebo_finance_sales_invoice_draft')->fields([
        'project_nid' => $projectId,
        'draft_number' => $draftNumber,
        'status' => 'draft',
        'invoice_date' => (string) $form_state->getValue('invoice_date'),
        'due_date' => (string) $form_state->getValue('due_date'),
        'description' => trim((string) $form_state->getValue('description')),
        'amount_ex_vat' => number_format($totals['ex'], 4, '.', ''),
        'vat_amount' => number_format($totals['vat'], 4, '.', ''),
        'amount_inc_vat' => number_format($totals['inc'], 4, '.', ''),
        'created' => $now,
        'created_by' => $actor,
        'changed' => $now,
        'changed_by' => $actor,
      ])->execute();

      foreach ($lines as $delta => $line) {
        $this->database->insert('brebo_finance_sales_invoice_draft_line')->fields([
          'draft_id' => $draftId,
          'project_nid' => $projectId,
          'line_number' => $delta + 1,
          'source_type' => $line['source_type'],
          'source_id' => $line['source_id'],
          'description' => $line['description'],
          'amount_ex_vat' => $line['amount_ex_vat'],
          'vat_code' => $line['vat_code'],
          'vat_rate' => $line['vat_rate'],
          'vat_amount' => $line['vat_amount'],
          'amount_inc_vat' => $line['amount_inc_vat'],
          'created' => $now,
          'created_by' => $actor,
        ])->execute();
      }
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }

    $this->messenger()->addStatus($this->t('Factuurconcept @number is aangemaakt. Er is nog geen Moneybird-factuur gemaakt.', ['@number' => $draftNumber]));
    $form_state->setRedirect('brebo_project_cockpit.invoices', ['node' => $projectId]);
  }

  /** @return array<string, string> */
  private function sourceOptions(int $projectId): array {
    $options = [];
    if ($this->database->schema()->tableExists('brebo_finance_billing_instalment')) {
      $rows = $this->database->select('brebo_finance_billing_instalment', 'i')->fields('i')->condition('project_nid', $projectId)->condition('status', 'billable')->execute()->fetchAll(\PDO::FETCH_ASSOC);
      foreach ($rows as $row) {
        $options['instalment:' . $row['id']] = $this->t('Termijn @nr · @desc · € @amount excl.', ['@nr' => $row['instalment_number'], '@desc' => $row['description'], '@amount' => number_format((float) $row['amount_ex_vat'], 2, ',', '.')]);
      }
    }
    if ($this->database->schema()->tableExists('brebo_finance_change_order')) {
      $rows = $this->database->select('brebo_finance_change_order', 'c')->fields('c')->condition('project_nid', $projectId)->condition('status', ['client_approved', 'executed'], 'IN')->isNull('invoice_ref')->execute()->fetchAll(\PDO::FETCH_ASSOC);
      foreach ($rows as $row) {
        $sign = ($row['change_type'] ?? '') === 'omission' ? '-' : '+';
        $options['change:' . $row['id']] = $this->t('@type @nr · @title · @sign€ @amount excl.', ['@type' => ($row['change_type'] ?? '') === 'omission' ? 'Minderwerk' : 'Meerwerk', '@nr' => $row['change_number'], '@title' => $row['title'], '@sign' => $sign, '@amount' => number_format((float) $row['sales_amount_ex_vat'], 2, ',', '.')]);
      }
    }
    if ($this->database->schema()->tableExists('brebo_finance_provisional_sum')) {
      $rows = $this->database->select('brebo_finance_provisional_sum', 'p')->fields('p')->condition('project_nid', $projectId)->where('approved_settlement_ex_vat <> invoiced_settlement_ex_vat')->execute()->fetchAll(\PDO::FETCH_ASSOC);
      foreach ($rows as $row) {
        $outstanding = (float) $row['approved_settlement_ex_vat'] - (float) $row['invoiced_settlement_ex_vat'];
        $options['provisional:' . $row['id']] = $this->t('Stelpost @nr · @title · verrekening € @amount excl.', ['@nr' => $row['provisional_sum_number'], '@title' => $row['title'], '@amount' => number_format($outstanding, 2, ',', '.')]);
      }
    }
    return $options;
  }

  /** @return list<array<string, mixed>> */
  private function sourceLines(int $projectId, string $type, int $id): array {
    if ($type === 'instalment') {
      $row = $this->database->select('brebo_finance_billing_instalment', 'i')->fields('i')->condition('id', $id)->condition('project_nid', $projectId)->condition('status', 'billable')->execute()->fetchAssoc();
      if ($row === FALSE) return [];
      if ($this->database->schema()->tableExists('brebo_finance_billing_instalment_line')) {
        $stored = $this->database->select('brebo_finance_billing_instalment_line', 'l')->fields('l')->condition('instalment_id', $id)->orderBy('line_number')->execute()->fetchAll(\PDO::FETCH_ASSOC);
        if ($stored !== []) {
          return array_map(static fn(array $line): array => ['source_type' => 'instalment', 'source_id' => $id, 'description' => $line['description'], 'amount_ex_vat' => $line['amount_ex_vat'], 'vat_code' => $line['vat_code'], 'vat_rate' => $line['vat_rate'], 'vat_amount' => $line['vat_amount'], 'amount_inc_vat' => $line['amount_inc_vat']], $stored);
        }
      }
      return [['source_type' => 'instalment', 'source_id' => $id, 'description' => $row['description'], 'amount_ex_vat' => $row['amount_ex_vat'], 'vat_code' => $row['vat_code'], 'vat_rate' => $row['vat_rate'], 'vat_amount' => $row['vat_amount'], 'amount_inc_vat' => $row['amount_inc_vat']]];
    }

    if ($type === 'change') {
      $row = $this->database->select('brebo_finance_change_order', 'c')->fields('c')->condition('id', $id)->condition('project_nid', $projectId)->condition('status', ['client_approved', 'executed'], 'IN')->execute()->fetchAssoc();
      if ($row === FALSE) return [];
      $amount = (float) $row['sales_amount_ex_vat'];
      if (($row['change_type'] ?? '') === 'omission') $amount *= -1;
      $vatRate = (float) $row['vat_rate'];
      $vat = round($amount * ($vatRate / 100), 4);
      return [['source_type' => 'change', 'source_id' => $id, 'description' => $row['title'], 'amount_ex_vat' => number_format($amount, 4, '.', ''), 'vat_code' => $row['vat_code'], 'vat_rate' => $row['vat_rate'], 'vat_amount' => number_format($vat, 4, '.', ''), 'amount_inc_vat' => number_format($amount + $vat, 4, '.', '')]];
    }

    if ($type === 'provisional') {
      $row = $this->database->select('brebo_finance_provisional_sum', 'p')->fields('p')->condition('id', $id)->condition('project_nid', $projectId)->execute()->fetchAssoc();
      if ($row === FALSE) return [];
      $amount = (float) $row['approved_settlement_ex_vat'] - (float) $row['invoiced_settlement_ex_vat'];
      if (abs($amount) < 0.0001) return [];
      $vatRate = (float) $row['vat_rate'];
      $vat = round($amount * ($vatRate / 100), 4);
      return [['source_type' => 'provisional', 'source_id' => $id, 'description' => 'Stelpostverrekening ' . $row['title'], 'amount_ex_vat' => number_format($amount, 4, '.', ''), 'vat_code' => $row['vat_code'], 'vat_rate' => $row['vat_rate'], 'vat_amount' => number_format($vat, 4, '.', ''), 'amount_inc_vat' => number_format($amount + $vat, 4, '.', '')]];
    }

    return [];
  }

}
