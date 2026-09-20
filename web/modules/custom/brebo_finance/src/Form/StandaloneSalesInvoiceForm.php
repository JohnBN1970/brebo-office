<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Form;

use Drupal\brebo_finance\Service\SalesTaxSettings;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Creates or edits a non-project sales invoice draft. */
final class StandaloneSalesInvoiceForm extends FormBase {

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

  public function getFormId(): string { return 'brebo_finance_standalone_sales_invoice_form'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $draft = NULL): array {
    foreach (['brebo_finance_sales_invoice_draft', 'brebo_finance_sales_invoice_draft_line'] as $table) {
      if (!$this->database->schema()->tableExists($table)) {
        $form['warning'] = ['#markup' => '<p><strong>' . $this->t('Factuurconcept-opslag ontbreekt. Voer eerst database-updates uit.') . '</strong></p>'];
        return $form;
      }
    }

    $draftId = (int) ($draft ?? $form_state->get('draft_id') ?? 0);
    $existing = NULL; $context = []; $storedLines = [];
    if ($draftId > 0) {
      $existing = $this->database->select('brebo_finance_sales_invoice_draft', 'd')->fields('d')->condition('id', $draftId)->condition('project_nid', 0)->condition('status', 'draft')->execute()->fetchAssoc();
      if ($existing === FALSE) throw new \InvalidArgumentException('Editable standalone invoice draft not found.');
      $context = $this->keyValueFactory->get('brebo_finance.sales_invoice_draft_context')->get((string) $draftId, []);
      $storedLines = is_array($context['lines'] ?? NULL) ? array_values($context['lines']) : [];
      $form_state->set('draft_id', $draftId);
    }

    $lineIndexes = $form_state->get('line_indexes');
    if (!is_array($lineIndexes) || $lineIndexes === []) {
      $count = max(1, count($storedLines)); $lineIndexes = range(1, $count);
      $form_state->set('line_indexes', $lineIndexes); $form_state->set('next_line_index', $count + 1);
    }

    $today = date('Y-m-d');
    $storedDays = isset($context['payment_term_days']) && is_numeric($context['payment_term_days']) ? max(0, (int) $context['payment_term_days']) : NULL;
    $storedChoice = $storedDays !== NULL && in_array($storedDays, [0, 5, 8, 14, 30], TRUE) ? (string) $storedDays : ($storedDays !== NULL ? 'custom' : 'inherit');
    $vatOptions = $this->taxSettings->activeVatOptions();
    if ($vatOptions === []) throw new \RuntimeException('Er is geen actief btw-tarief voor verkoop geconfigureerd.');
    $defaultVat = array_key_exists('NL_21', $vatOptions) ? 'NL_21' : (string) array_key_first($vatOptions);
    $gConfig = $this->taxSettings->gAccount();

    $form['intro'] = ['#markup' => '<p>' . $this->t($draftId > 0 ? 'Bewerk dit losse factuurconcept. Zolang het concept niet is vrijgegeven blijft het wijzigbaar en heeft het nog geen definitief factuurnummer.' : 'Gebruik dit alleen wanneer de verkoopfactuur niet bij een project hoort. Er wordt nu alleen een concept gemaakt; het definitieve factuurnummer ontstaat pas bij verzenden.') . '</p>'];
    $form['customer'] = ['#type' => 'fieldset', '#title' => $this->t('Debiteur')];
    $form['customer']['customer_organization'] = ['#type' => 'entity_autocomplete', '#title' => $this->t('Organisatie / debiteur'), '#target_type' => 'node', '#selection_settings' => ['target_bundles' => ['brebo_organization']], '#required' => TRUE, '#default_value' => !empty($context['customer_organization_nid']) ? $this->entityTypeManager->getStorage('node')->load((int) $context['customer_organization_nid']) : NULL, '#description' => $this->t('Kies de centrale BREBO-relatie. Bij Overnemen gebruikt Office de betaaltermijn van deze klant en anders de BREBO-standaard van 14 dagen.')];
    $form['customer']['customer_ref'] = ['#type' => 'textfield', '#title' => $this->t('Klantreferentie / inkooporder'), '#maxlength' => 255, '#default_value' => (string) ($context['customer_ref'] ?? '')];

    $form['invoice'] = ['#type' => 'fieldset', '#title' => $this->t('Factuur')];
    $form['invoice']['invoice_date'] = ['#type' => 'date', '#title' => $this->t('Factuurdatum'), '#required' => TRUE, '#default_value' => (string) ($existing['invoice_date'] ?? $today)];
    $form['invoice']['payment_term'] = ['#type' => 'select', '#title' => $this->t('Betaaltermijn'), '#options' => ['inherit' => $this->t('Overnemen van klant / BREBO-standaard'), '0' => $this->t('Per omgaande'), '5' => $this->t('5 dagen'), '8' => $this->t('8 dagen'), '14' => $this->t('14 dagen'), '30' => $this->t('30 dagen'), 'custom' => $this->t('Afwijkend aantal dagen')], '#default_value' => $storedChoice];
    $form['invoice']['custom_payment_term_days'] = ['#type' => 'number', '#title' => $this->t('Afwijkend aantal dagen'), '#min' => 0, '#max' => 365, '#default_value' => $storedChoice === 'custom' ? $storedDays : NULL, '#states' => ['visible' => [':input[name="payment_term"]' => ['value' => 'custom']]]];
    $form['invoice']['due_date_info'] = ['#type' => 'item', '#title' => $this->t('Vervaldatum'), '#markup' => $this->t('Wordt automatisch berekend vanaf de factuurdatum en de gekozen betaaltermijn.')];
    $form['invoice']['description'] = ['#type' => 'textfield', '#title' => $this->t('Omschrijving'), '#maxlength' => 255, '#required' => TRUE, '#default_value' => (string) ($existing['description'] ?? '')];

    $form['lines'] = ['#type' => 'fieldset', '#title' => $this->t('Factuurregels'), '#prefix' => '<div id="standalone-sales-invoice-lines">', '#suffix' => '</div>'];
    $form['lines']['help'] = ['#markup' => '<p>' . $this->t('Btw wordt per regel gekozen. Actieve tarieven komen uit Verkoopinstellingen; wijzigingen daar veranderen nooit een reeds definitief gemaakte factuur.') . '</p>'];
    foreach ($lineIndexes as $position => $i) {
      $stored = $storedLines[$position] ?? [];
      $storedCode = (string) ($stored['vat_code'] ?? '');
      if ($storedCode === '' && isset($stored['vat_rate'])) $storedCode = 'NL_' . (string) (int) $stored['vat_rate'];
      if (!isset($vatOptions[$storedCode])) $storedCode = $defaultVat;
      $form['lines']['line_' . $i] = ['#type' => 'container', '#attributes' => ['class' => ['brebo-invoice-line']]];
      $form['lines']['line_' . $i]['description_' . $i] = ['#type' => 'textfield', '#title' => $this->t('Omschrijving'), '#maxlength' => 255, '#default_value' => (string) ($stored['description'] ?? '')];
      $form['lines']['line_' . $i]['quantity_' . $i] = ['#type' => 'number', '#title' => $this->t('Aantal'), '#step' => 0.0001, '#default_value' => $stored['quantity'] ?? 1];
      $form['lines']['line_' . $i]['unit_' . $i] = ['#type' => 'textfield', '#title' => $this->t('Eenheid'), '#maxlength' => 32, '#default_value' => (string) ($stored['unit'] ?? '')];
      $form['lines']['line_' . $i]['unit_price_' . $i] = ['#type' => 'number', '#title' => $this->t('Prijs excl. btw'), '#step' => 0.01, '#default_value' => $stored['unit_price_ex_vat'] ?? NULL];
      $form['lines']['line_' . $i]['vat_code_' . $i] = ['#type' => 'select', '#title' => $this->t('Btw'), '#options' => $vatOptions, '#default_value' => $storedCode];
      if (count($lineIndexes) > 1) $form['lines']['line_' . $i]['remove_' . $i] = ['#type' => 'submit', '#value' => $this->t('Verwijderen'), '#submit' => ['::removeLine'], '#limit_validation_errors' => [], '#line_index' => $i, '#ajax' => ['callback' => '::linesAjax', 'wrapper' => 'standalone-sales-invoice-lines']];
    }
    $form['lines']['add_line'] = ['#type' => 'submit', '#value' => $this->t('+ Regel toevoegen'), '#submit' => ['::addLine'], '#limit_validation_errors' => [], '#ajax' => ['callback' => '::linesAjax', 'wrapper' => 'standalone-sales-invoice-lines'], '#attributes' => ['class' => ['brebo-invoice-lines__add']]];

    if ($gConfig['enabled']) {
      $form['g_account'] = ['#type' => 'fieldset', '#title' => $this->t('G-rekening')];
      $form['g_account']['g_account_on'] = ['#type' => 'checkbox', '#title' => $this->t('Deze factuur deels via G-rekening laten betalen'), '#default_value' => (bool) ($context['g_account_on'] ?? FALSE)];
      $form['g_account']['g_account_percentage'] = ['#type' => 'number', '#title' => $this->t('Percentage naar G-rekening'), '#min' => 0, '#max' => 100, '#step' => 0.01, '#default_value' => (float) ($context['g_account_percentage'] ?? $gConfig['default_percentage']), '#states' => ['visible' => [':input[name="g_account_on"]' => ['checked' => TRUE]]]];
      $form['g_account']['info'] = ['#markup' => '<p>' . $this->t('Office berekent de verdeling pas nadat btw is berekend. Bij definitief vrijgeven worden regulier IBAN en G-rekening IBAN als betaalinstructies vastgezet.') . '</p>'];
    }

    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t($draftId > 0 ? 'Concept bijwerken' : 'Concept opslaan'), '#button_type' => 'primary'];
    $form['actions']['cancel'] = ['#type' => 'link', '#title' => $this->t('Annuleren'), '#url' => Url::fromRoute('brebo_finance.sales_workspace'), '#attributes' => ['class' => ['button']]];
    $form['#attached']['library'][] = 'brebo_finance/standalone_sales_invoice';
    return $form;
  }

  public function linesAjax(array &$form, FormStateInterface $form_state): array { return $form['lines']; }
  public function addLine(array &$form, FormStateInterface $form_state): void { $indexes = $form_state->get('line_indexes') ?? [1]; $next = (int) ($form_state->get('next_line_index') ?? 2); $indexes[] = $next; $form_state->set('line_indexes', $indexes); $form_state->set('next_line_index', $next + 1); $form_state->setRebuild(TRUE); }
  public function removeLine(array &$form, FormStateInterface $form_state): void { $trigger = $form_state->getTriggeringElement(); $remove = (int) ($trigger['#line_index'] ?? 0); $indexes = array_values(array_filter($form_state->get('line_indexes') ?? [1], static fn($index): bool => (int) $index !== $remove)); $form_state->set('line_indexes', $indexes === [] ? [1] : $indexes); $form_state->setRebuild(TRUE); }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ((string) $form_state->getValue('payment_term') === 'custom' && $form_state->getValue('custom_payment_term_days') === '') $form_state->setErrorByName('custom_payment_term_days', $this->t('Vul het afwijkende aantal betalingsdagen in.'));
    $organizationId = (int) ($form_state->getValue('customer_organization') ?? 0); $organization = $organizationId > 0 ? $this->entityTypeManager->getStorage('node')->load($organizationId) : NULL;
    if (!$organization instanceof NodeInterface || $organization->bundle() !== 'brebo_organization') $form_state->setErrorByName('customer_organization', $this->t('Kies een geldige organisatie uit de centrale Relaties-administratie.'));
    if ((bool) $form_state->getValue('g_account_on') && ((float) $form_state->getValue('g_account_percentage') <= 0 || (float) $form_state->getValue('g_account_percentage') > 100)) $form_state->setErrorByName('g_account_percentage', $this->t('Kies een G-rekeningpercentage groter dan 0 en maximaal 100.'));
    $hasLine = FALSE;
    foreach ($form_state->get('line_indexes') ?? [1] as $i) {
      if (trim((string) $form_state->getValue('description_' . $i)) !== '' && abs((float) ($form_state->getValue('unit_price_' . $i) ?? 0)) > 0.00001) { $hasLine = TRUE; break; }
    }
    if (!$hasLine) $form_state->setErrorByName('lines', $this->t('Vul minimaal één factuurregel met omschrijving en prijs in.'));
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $lines = []; $totals = ['ex' => 0.0, 'vat' => 0.0, 'inc' => 0.0];
    foreach ($form_state->get('line_indexes') ?? [1] as $i) {
      $description = trim((string) $form_state->getValue('description_' . $i)); $unitPrice = (float) ($form_state->getValue('unit_price_' . $i) ?? 0);
      if ($description === '' || abs($unitPrice) < 0.00001) continue;
      $quantity = (float) ($form_state->getValue('quantity_' . $i) ?? 1); $vat = $this->taxSettings->vat((string) $form_state->getValue('vat_code_' . $i));
      $amountEx = round($quantity * $unitPrice, 4); $vatAmount = $vat['treatment'] === 'normal' ? round($amountEx * (((float) $vat['rate']) / 100), 4) : 0.0; $amountInc = round($amountEx + $vatAmount, 4);
      $totals['ex'] += $amountEx; $totals['vat'] += $vatAmount; $totals['inc'] += $amountInc;
      $lines[] = ['description' => $description, 'quantity' => $quantity, 'unit' => trim((string) $form_state->getValue('unit_' . $i)), 'unit_price_ex_vat' => $unitPrice, 'amount_ex_vat' => $amountEx, 'vat_code' => $vat['code'], 'vat_label' => $vat['label'], 'vat_rate' => (float) $vat['rate'], 'vat_treatment' => $vat['treatment'], 'vat_amount' => $vatAmount, 'amount_inc_vat' => $amountInc];
    }

    $organizationId = (int) $form_state->getValue('customer_organization'); $organization = $this->entityTypeManager->getStorage('node')->load($organizationId);
    if (!$organization instanceof NodeInterface || $organization->bundle() !== 'brebo_organization') throw new \RuntimeException('Canonical debtor organisation is unavailable.');
    [$paymentDays, $paymentSource] = $this->resolvePaymentTerm($organization, (string) $form_state->getValue('payment_term'), $form_state->getValue('custom_payment_term_days'));
    $invoiceDate = (string) $form_state->getValue('invoice_date'); $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $invoiceDate); if ($date === FALSE) throw new \RuntimeException('Ongeldige factuurdatum.');
    $dueDate = $date->modify('+' . $paymentDays . ' days')->format('Y-m-d');

    $gOn = (bool) $form_state->getValue('g_account_on'); $gPercentage = $gOn ? (float) $form_state->getValue('g_account_percentage') : 0.0;
    $split = $this->taxSettings->split(round($totals['inc'], 2), $gPercentage); $gConfig = $this->taxSettings->gAccount();
    if ($gOn && (!$gConfig['enabled'] || $gConfig['g_iban'] === '')) throw new \RuntimeException('G-rekening is niet volledig geconfigureerd in Verkoopinstellingen.');

    $actor = (int) $this->currentUser()->id(); $now = time(); $draftId = (int) ($form_state->get('draft_id') ?? 0);
    $draftNumber = $draftId > 0 ? (string) $this->database->select('brebo_finance_sales_invoice_draft', 'd')->fields('d', ['draft_number'])->condition('id', $draftId)->condition('project_nid', 0)->condition('status', 'draft')->execute()->fetchField() : 'CON-LOS-' . date('Ymd-His');
    $transaction = $this->database->startTransaction();
    try {
      $draftFields = ['project_nid' => 0, 'status' => 'draft', 'invoice_date' => $invoiceDate, 'due_date' => $dueDate, 'description' => trim((string) $form_state->getValue('description')), 'amount_ex_vat' => number_format($totals['ex'], 4, '.', ''), 'vat_amount' => number_format($totals['vat'], 4, '.', ''), 'amount_inc_vat' => number_format($totals['inc'], 4, '.', ''), 'changed' => $now, 'changed_by' => $actor];
      if ($draftId > 0) { $this->database->update('brebo_finance_sales_invoice_draft')->fields($draftFields)->condition('id', $draftId)->condition('project_nid', 0)->condition('status', 'draft')->execute(); $this->database->delete('brebo_finance_sales_invoice_draft_line')->condition('draft_id', $draftId)->execute(); }
      else { $draftFields['draft_number'] = $draftNumber; $draftFields['created'] = $now; $draftFields['created_by'] = $actor; $draftId = (int) $this->database->insert('brebo_finance_sales_invoice_draft')->fields($draftFields)->execute(); }
      foreach ($lines as $delta => $line) {
        $this->database->insert('brebo_finance_sales_invoice_draft_line')->fields(['draft_id' => $draftId, 'project_nid' => 0, 'line_number' => $delta + 1, 'source_type' => 'standalone', 'source_id' => 0, 'description' => $line['description'], 'amount_ex_vat' => number_format($line['amount_ex_vat'], 4, '.', ''), 'vat_code' => $line['vat_code'], 'vat_rate' => number_format($line['vat_rate'], 4, '.', ''), 'vat_amount' => number_format($line['vat_amount'], 4, '.', ''), 'amount_inc_vat' => number_format($line['amount_inc_vat'], 4, '.', ''), 'created' => $now, 'created_by' => $actor])->execute();
      }
      $this->keyValueFactory->get('brebo_finance.sales_invoice_draft_context')->set((string) $draftId, ['origin' => 'standalone', 'customer_organization_nid' => $organizationId, 'customer_name' => $organization->label(), 'customer_ref' => trim((string) $form_state->getValue('customer_ref')), 'payment_term_days' => $paymentDays, 'payment_term_source' => $paymentSource, 'due_date_calculated' => TRUE, 'vat_snapshot' => array_values(array_map(static fn(array $line): array => ['code' => $line['vat_code'], 'label' => $line['vat_label'], 'rate' => $line['vat_rate'], 'treatment' => $line['vat_treatment']], $lines)), 'g_account_on' => $gOn, 'g_account_percentage' => $split['percentage'], 'g_account_amount' => $split['g_amount'], 'regular_account_amount' => $split['regular_amount'], 'regular_iban' => $gConfig['regular_iban'], 'g_account_iban' => $gOn ? $gConfig['g_iban'] : '', 'lines' => $lines]);
    }
    catch (\Throwable $exception) { $transaction->rollBack(); throw $exception; }

    $this->messenger()->addStatus($this->t('Losse factuur @number is als concept opgeslagen. Btw en eventuele G-rekeningverdeling zijn vastgelegd; definitief nummer volgt pas bij vrijgave.', ['@number' => $draftNumber]));
    $form_state->setRedirect('brebo_finance.sales_workspace');
  }

  /** @return array{0:int,1:string} */
  private function resolvePaymentTerm(NodeInterface $organization, string $choice, mixed $custom): array {
    if ($choice === 'custom') return [max(0, (int) $custom), 'invoice'];
    if ($choice !== 'inherit' && is_numeric($choice)) return [max(0, (int) $choice), 'invoice'];
    if ($organization->hasField('field_brebo_payment_term_days')) { $value = $organization->get('field_brebo_payment_term_days')->value; if (is_numeric($value)) return [max(0, (int) $value), 'customer']; }
    $value = \Drupal::config('brebo_finance.sales')->get('numbering.default_payment_term_days'); return [is_numeric($value) ? max(0, (int) $value) : 14, 'brebo_default'];
  }
}
