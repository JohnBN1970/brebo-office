<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Form;

use Drupal\brebo_finance\Service\SalesTaxSettings;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Builds a controlled sales-invoice draft from approved project sources. */
final class ProjectSalesInvoiceDraftForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SalesTaxSettings $taxSettings,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('keyvalue'),
      $container->get('entity_type.manager'),
      new SalesTaxSettings($container->get('config.factory')),
    );
  }

  public function getFormId(): string { return 'brebo_project_cockpit_sales_invoice_draft_form'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if ($node === NULL || $node->bundle() !== 'brebo_project') throw new \InvalidArgumentException('BREBO project required.');
    if (!$this->database->schema()->tableExists('brebo_finance_sales_invoice_draft')) {
      $form['warning'] = ['#markup' => '<p><strong>' . $this->t('Factuurconcept-opslag ontbreekt. Voer eerst database-updates uit.') . '</strong></p>'];
      return $form;
    }

    $projectId = (int) $node->id();
    $sources = $this->sourceOptions($projectId);
    $projectDays = $this->projectPaymentTermDays($projectId);
    $gConfig = $this->taxSettings->gAccount();

    $form['intro'] = ['#markup' => '<p>' . $this->t('Maak een factuurconcept op basis van factureerbare projectbronnen. Btw, G-rekening, factuurnummer, PDF en verzending volgen dezelfde centrale BREBO-verkoopmotor als losse verkoopfacturen.') . '</p>'];
    $form['customer'] = ['#type' => 'fieldset', '#title' => $this->t('Debiteur')];
    $form['customer']['customer_organization'] = ['#type' => 'entity_autocomplete', '#title' => $this->t('Organisatie / debiteur'), '#target_type' => 'node', '#selection_settings' => ['target_bundles' => ['brebo_organization']], '#required' => TRUE, '#description' => $this->t('Kies de centrale BREBO-relatie. Betaaltermijn erft van termijn, projectcontract, klantprofiel en tenslotte BREBO-standaard.')];
    $form['customer']['customer_ref'] = ['#type' => 'textfield', '#title' => $this->t('Klantreferentie / inkooporder'), '#maxlength' => 255, '#default_value' => $this->projectClientReference($projectId)];
    $form['invoice'] = ['#type' => 'fieldset', '#title' => $this->t('Factuur')];
    $form['invoice']['invoice_date'] = ['#type' => 'date', '#title' => $this->t('Factuurdatum'), '#required' => TRUE, '#default_value' => date('Y-m-d')];
    $form['invoice']['payment_term'] = ['#type' => 'select', '#title' => $this->t('Betaaltermijn'), '#options' => ['inherit' => $this->t('Overnemen uit termijn / project / klant'), '0' => $this->t('Per omgaande'), '5' => $this->t('5 dagen'), '8' => $this->t('8 dagen'), '14' => $this->t('14 dagen'), '30' => $this->t('30 dagen'), 'custom' => $this->t('Afwijkend aantal dagen')], '#default_value' => 'inherit', '#description' => $this->t('Projectstandaard: @days dagen.', ['@days' => $projectDays])];
    $form['invoice']['custom_payment_term_days'] = ['#type' => 'number', '#title' => $this->t('Afwijkend aantal dagen'), '#min' => 0, '#max' => 365, '#states' => ['visible' => [':input[name="payment_term"]' => ['value' => 'custom']]]];
    $form['invoice']['due_date_info'] = ['#type' => 'item', '#title' => $this->t('Vervaldatum'), '#markup' => $this->t('Wordt automatisch berekend vanaf factuurdatum en betaaltermijn.')];
    $form['invoice']['description'] = ['#type' => 'textfield', '#title' => $this->t('Omschrijving'), '#maxlength' => 255, '#default_value' => $this->t('Projectfactuur @project', ['@project' => $node->label()])];
    $form['sources'] = ['#type' => 'checkboxes', '#title' => $this->t('Opnemen in factuurconcept'), '#options' => $sources, '#required' => TRUE, '#description' => $this->t('Alleen contractueel factureerbare bronnen worden aangeboden. De btw-code van iedere bron wordt bij conceptvorming opnieuw door de centrale verkoopinstellingen gevalideerd en berekend.')];

    if ($gConfig['enabled']) {
      $form['g_account'] = ['#type' => 'fieldset', '#title' => $this->t('G-rekening')];
      $form['g_account']['g_account_on'] = ['#type' => 'checkbox', '#title' => $this->t('Deze factuur deels via G-rekening laten betalen'), '#default_value' => FALSE];
      $form['g_account']['g_account_percentage'] = ['#type' => 'number', '#title' => $this->t('Percentage naar G-rekening'), '#min' => 0, '#max' => 100, '#step' => 0.01, '#default_value' => (float) $gConfig['default_percentage'], '#states' => ['visible' => [':input[name="g_account_on"]' => ['checked' => TRUE]]]];
      $form['g_account']['info'] = ['#markup' => '<p>' . $this->t('De G-rekeningverdeling wordt berekend over het factuurtotaal inclusief btw, nadat alle btw-regels centraal zijn herberekend.') . '</p>'];
    }

    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Factuurconcept maken'), '#button_type' => 'primary'];
    $form['actions']['cancel'] = ['#type' => 'link', '#title' => $this->t('Annuleren'), '#url' => Url::fromRoute('brebo_project_cockpit.invoices', ['node' => $projectId]), '#attributes' => ['class' => ['button']]];
    $form_state->set('project_id', $projectId);
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (array_values(array_filter($form_state->getValue('sources') ?? [])) === []) $form_state->setErrorByName('sources', $this->t('Selecteer minimaal één factureerbare bron.'));
    if ((string) $form_state->getValue('payment_term') === 'custom' && $form_state->getValue('custom_payment_term_days') === '') $form_state->setErrorByName('custom_payment_term_days', $this->t('Vul het afwijkende aantal betalingsdagen in.'));
    $organizationId = (int) ($form_state->getValue('customer_organization') ?? 0);
    $organization = $organizationId > 0 ? $this->entityTypeManager->getStorage('node')->load($organizationId) : NULL;
    if (!$organization instanceof NodeInterface || $organization->bundle() !== 'brebo_organization') $form_state->setErrorByName('customer_organization', $this->t('Kies een geldige organisatie uit de centrale Relaties-administratie.'));
    if ((bool) $form_state->getValue('g_account_on') && ((float) $form_state->getValue('g_account_percentage') <= 0 || (float) $form_state->getValue('g_account_percentage') > 100)) $form_state->setErrorByName('g_account_percentage', $this->t('Kies een G-rekeningpercentage groter dan 0 en maximaal 100.'));
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    $selected = array_values(array_filter($form_state->getValue('sources') ?? []));
    $rawLines = [];
    foreach ($selected as $sourceKey) {
      [$type, $id] = array_pad(explode(':', (string) $sourceKey, 2), 2, NULL);
      if ($type !== NULL && $id !== NULL) $rawLines = array_merge($rawLines, $this->sourceLines($projectId, $type, (int) $id));
    }
    if ($rawLines === []) throw new \RuntimeException('No invoiceable lines could be built from the selected sources.');

    $organizationId = (int) $form_state->getValue('customer_organization');
    $organization = $this->entityTypeManager->getStorage('node')->load($organizationId);
    if (!$organization instanceof NodeInterface || $organization->bundle() !== 'brebo_organization') throw new \RuntimeException('Canonical debtor organisation is unavailable.');

    [$paymentDays, $paymentSource] = $this->resolvePaymentTerm($projectId, $organization, $selected, (string) $form_state->getValue('payment_term'), $form_state->getValue('custom_payment_term_days'));
    $invoiceDate = (string) $form_state->getValue('invoice_date');
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $invoiceDate);
    if ($date === FALSE) throw new \RuntimeException('Ongeldige factuurdatum.');
    $dueDate = $date->modify('+' . $paymentDays . ' days')->format('Y-m-d');

    $lines = []; $totals = ['ex' => 0.0, 'vat' => 0.0, 'inc' => 0.0];
    foreach ($rawLines as $raw) {
      $vat = $this->taxSettings->vat((string) ($raw['vat_code'] ?? ''));
      $amountEx = round((float) ($raw['amount_ex_vat'] ?? 0), 4);
      $vatAmount = $vat['treatment'] === 'normal' ? round($amountEx * ((float) $vat['rate'] / 100), 4) : 0.0;
      $amountInc = round($amountEx + $vatAmount, 4);
      $line = ['source_type' => (string) $raw['source_type'], 'source_id' => (int) $raw['source_id'], 'description' => (string) $raw['description'], 'quantity' => 1, 'unit' => '', 'unit_price_ex_vat' => $amountEx, 'amount_ex_vat' => $amountEx, 'vat_code' => $vat['code'], 'vat_label' => $vat['label'], 'vat_rate' => (float) $vat['rate'], 'vat_treatment' => $vat['treatment'], 'vat_amount' => $vatAmount, 'amount_inc_vat' => $amountInc];
      $lines[] = $line; $totals['ex'] += $amountEx; $totals['vat'] += $vatAmount; $totals['inc'] += $amountInc;
    }

    $gOn = (bool) $form_state->getValue('g_account_on');
    $gPercentage = $gOn ? (float) $form_state->getValue('g_account_percentage') : 0.0;
    $gConfig = $this->taxSettings->gAccount();
    if ($gOn && (!$gConfig['enabled'] || $gConfig['g_iban'] === '')) throw new \RuntimeException('G-rekening is niet volledig geconfigureerd in Verkoopinstellingen.');
    $split = $this->taxSettings->split(round($totals['inc'], 2), $gPercentage);

    $actor = (int) $this->currentUser()->id(); $now = time(); $draftNumber = 'CON-' . date('Ymd-His') . '-' . $projectId;
    $transaction = $this->database->startTransaction();
    try {
      $draftId = (int) $this->database->insert('brebo_finance_sales_invoice_draft')->fields(['project_nid' => $projectId, 'draft_number' => $draftNumber, 'status' => 'draft', 'invoice_date' => $invoiceDate, 'due_date' => $dueDate, 'description' => trim((string) $form_state->getValue('description')), 'amount_ex_vat' => number_format($totals['ex'], 4, '.', ''), 'vat_amount' => number_format($totals['vat'], 4, '.', ''), 'amount_inc_vat' => number_format($totals['inc'], 4, '.', ''), 'created' => $now, 'created_by' => $actor, 'changed' => $now, 'changed_by' => $actor])->execute();
      foreach ($lines as $delta => $line) {
        $this->database->insert('brebo_finance_sales_invoice_draft_line')->fields(['draft_id' => $draftId, 'project_nid' => $projectId, 'line_number' => $delta + 1, 'source_type' => $line['source_type'], 'source_id' => $line['source_id'], 'description' => $line['description'], 'amount_ex_vat' => number_format($line['amount_ex_vat'], 4, '.', ''), 'vat_code' => $line['vat_code'], 'vat_rate' => number_format($line['vat_rate'], 4, '.', ''), 'vat_amount' => number_format($line['vat_amount'], 4, '.', ''), 'amount_inc_vat' => number_format($line['amount_inc_vat'], 4, '.', ''), 'created' => $now, 'created_by' => $actor])->execute();
      }
      $this->keyValueFactory->get('brebo_finance.sales_invoice_draft_context')->set((string) $draftId, ['origin' => 'project', 'project_nid' => $projectId, 'customer_organization_nid' => $organizationId, 'customer_name' => (string) $organization->label(), 'customer_ref' => trim((string) $form_state->getValue('customer_ref')), 'payment_term_days' => $paymentDays, 'payment_term_source' => $paymentSource, 'due_date_calculated' => TRUE, 'vat_snapshot' => array_values(array_map(static fn(array $line): array => ['code' => $line['vat_code'], 'label' => $line['vat_label'], 'rate' => $line['vat_rate'], 'treatment' => $line['vat_treatment']], $lines)), 'g_account_on' => $gOn, 'g_account_percentage' => $split['percentage'], 'g_account_amount' => $split['g_amount'], 'regular_account_amount' => $split['regular_amount'], 'regular_iban' => $gConfig['regular_iban'], 'g_account_iban' => $gOn ? $gConfig['g_iban'] : '', 'lines' => $lines]);
    }
    catch (\Throwable $exception) { $transaction->rollBack(); throw $exception; }

    $this->messenger()->addStatus($this->t('Factuurconcept @number is aangemaakt voor @customer. Btw en eventuele G-rekeningverdeling zijn centraal berekend en vastgelegd; definitief nummer volgt pas bij vrijgave.', ['@number' => $draftNumber, '@customer' => $organization->label()]));
    $form_state->setRedirect('brebo_project_cockpit.invoices', ['node' => $projectId]);
  }

  private function projectPaymentTermDays(int $projectId): int {
    if ($this->database->schema()->tableExists('brebo_finance_project_contract')) { $value = $this->database->select('brebo_finance_project_contract', 'c')->fields('c', ['payment_term_days'])->condition('project_nid', $projectId)->execute()->fetchField(); if (is_numeric($value)) return max(0, (int) $value); }
    return $this->globalPaymentTermDays();
  }
  private function globalPaymentTermDays(): int { $value = \Drupal::config('brebo_finance.sales')->get('numbering.default_payment_term_days'); return is_numeric($value) ? max(0, (int) $value) : 14; }

  /** @return array{0:int,1:string} */
  private function resolvePaymentTerm(int $projectId, NodeInterface $organization, array $selected, string $choice, mixed $custom): array {
    if ($choice === 'custom') return [max(0, (int) $custom), 'invoice'];
    if ($choice !== 'inherit' && is_numeric($choice)) return [max(0, (int) $choice), 'invoice'];
    $instalmentTerms = [];
    foreach ($selected as $sourceKey) {
      [$type, $id] = array_pad(explode(':', (string) $sourceKey, 2), 2, NULL);
      if ($type !== 'instalment' || !is_numeric($id)) continue;
      $payload = $this->database->select('brebo_finance_billing_instalment', 'i')->fields('i', ['evidence_payload'])->condition('id', (int) $id)->condition('project_nid', $projectId)->execute()->fetchField();
      $decoded = is_string($payload) && $payload !== '' ? json_decode($payload, TRUE) : NULL;
      if (is_array($decoded) && isset($decoded['payment_term_days']) && is_numeric($decoded['payment_term_days'])) $instalmentTerms[(int) $decoded['payment_term_days']] = TRUE;
    }
    if (count($instalmentTerms) === 1) return [(int) array_key_first($instalmentTerms), 'instalment'];
    if ($this->database->schema()->tableExists('brebo_finance_project_contract')) { $value = $this->database->select('brebo_finance_project_contract', 'c')->fields('c', ['payment_term_days'])->condition('project_nid', $projectId)->execute()->fetchField(); if (is_numeric($value)) return [max(0, (int) $value), 'project_contract']; }
    if ($organization->hasField('field_brebo_payment_term_days')) { $value = $organization->get('field_brebo_payment_term_days')->value; if (is_numeric($value)) return [max(0, (int) $value), 'customer']; }
    return [$this->globalPaymentTermDays(), 'brebo_default'];
  }

  private function projectClientReference(int $projectId): string {
    if (!$this->database->schema()->tableExists('brebo_finance_project_contract')) return '';
    return trim((string) ($this->database->select('brebo_finance_project_contract', 'c')->fields('c', ['client_ref'])->condition('project_nid', $projectId)->execute()->fetchField() ?: ''));
  }

  /** @return array<string,string> */
  private function sourceOptions(int $projectId): array {
    $options = [];
    if ($this->database->schema()->tableExists('brebo_finance_billing_instalment')) foreach ($this->database->select('brebo_finance_billing_instalment', 'i')->fields('i')->condition('project_nid', $projectId)->condition('status', 'billable')->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) { $term = $this->instalmentPaymentTerm($row); $options['instalment:' . $row['id']] = $this->t('Termijn @nr · @desc · € @amount excl. · @days', ['@nr' => $row['instalment_number'], '@desc' => $row['description'], '@amount' => number_format((float) $row['amount_ex_vat'], 2, ',', '.'), '@days' => $term === 0 ? 'per omgaande' : $term . ' dagen']); }
    if ($this->database->schema()->tableExists('brebo_finance_change_order')) foreach ($this->database->select('brebo_finance_change_order', 'c')->fields('c')->condition('project_nid', $projectId)->condition('status', ['client_approved', 'executed'], 'IN')->isNull('invoice_ref')->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) { $sign = ($row['change_type'] ?? '') === 'omission' ? '-' : '+'; $options['change:' . $row['id']] = $this->t('@type @nr · @title · @sign€ @amount excl.', ['@type' => ($row['change_type'] ?? '') === 'omission' ? 'Minderwerk' : 'Meerwerk', '@nr' => $row['change_number'], '@title' => $row['title'], '@sign' => $sign, '@amount' => number_format((float) $row['sales_amount_ex_vat'], 2, ',', '.')]); }
    if ($this->database->schema()->tableExists('brebo_finance_provisional_sum')) foreach ($this->database->select('brebo_finance_provisional_sum', 'p')->fields('p')->condition('project_nid', $projectId)->where('approved_settlement_ex_vat <> invoiced_settlement_ex_vat')->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) { $outstanding = (float) $row['approved_settlement_ex_vat'] - (float) $row['invoiced_settlement_ex_vat']; $options['provisional:' . $row['id']] = $this->t('Stelpost @nr · @title · verrekening € @amount excl.', ['@nr' => $row['provisional_sum_number'], '@title' => $row['title'], '@amount' => number_format($outstanding, 2, ',', '.')]); }
    return $options;
  }

  private function instalmentPaymentTerm(array $row): int { $payload = json_decode((string) ($row['evidence_payload'] ?? ''), TRUE); if (is_array($payload) && isset($payload['payment_term_days']) && is_numeric($payload['payment_term_days'])) return max(0, (int) $payload['payment_term_days']); return $this->projectPaymentTermDays((int) ($row['project_nid'] ?? 0)); }

  /** @return list<array<string,mixed>> */
  private function sourceLines(int $projectId, string $type, int $id): array {
    if ($type === 'instalment') {
      $row = $this->database->select('brebo_finance_billing_instalment', 'i')->fields('i')->condition('id', $id)->condition('project_nid', $projectId)->condition('status', 'billable')->execute()->fetchAssoc(); if ($row === FALSE) return [];
      if ($this->database->schema()->tableExists('brebo_finance_billing_instalment_line')) { $stored = $this->database->select('brebo_finance_billing_instalment_line', 'l')->fields('l')->condition('instalment_id', $id)->orderBy('line_number')->execute()->fetchAll(\PDO::FETCH_ASSOC); if ($stored !== []) return array_map(static fn(array $line): array => ['source_type' => 'instalment', 'source_id' => $id, 'description' => $line['description'], 'amount_ex_vat' => $line['amount_ex_vat'], 'vat_code' => $line['vat_code']], $stored); }
      return [['source_type' => 'instalment', 'source_id' => $id, 'description' => $row['description'], 'amount_ex_vat' => $row['amount_ex_vat'], 'vat_code' => $row['vat_code']]];
    }
    if ($type === 'change') { $row = $this->database->select('brebo_finance_change_order', 'c')->fields('c')->condition('id', $id)->condition('project_nid', $projectId)->condition('status', ['client_approved', 'executed'], 'IN')->execute()->fetchAssoc(); if ($row === FALSE) return []; $amount = (float) $row['sales_amount_ex_vat']; if (($row['change_type'] ?? '') === 'omission') $amount *= -1; return [['source_type' => 'change', 'source_id' => $id, 'description' => $row['title'], 'amount_ex_vat' => number_format($amount, 4, '.', ''), 'vat_code' => $row['vat_code']]]; }
    if ($type === 'provisional') { $row = $this->database->select('brebo_finance_provisional_sum', 'p')->fields('p')->condition('id', $id)->condition('project_nid', $projectId)->execute()->fetchAssoc(); if ($row === FALSE) return []; $amount = (float) $row['approved_settlement_ex_vat'] - (float) $row['invoiced_settlement_ex_vat']; if (abs($amount) < 0.0001) return []; return [['source_type' => 'provisional', 'source_id' => $id, 'description' => 'Stelpostverrekening ' . $row['title'], 'amount_ex_vat' => number_format($amount, 4, '.', ''), 'vat_code' => $row['vat_code']]]; }
    return [];
  }
}
