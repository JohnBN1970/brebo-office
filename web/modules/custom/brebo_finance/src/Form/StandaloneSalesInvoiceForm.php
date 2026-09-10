<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Creates a non-project sales invoice draft in the existing sales draft flow. */
final class StandaloneSalesInvoiceForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly KeyValueFactoryInterface $keyValueFactory,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('keyvalue'));
  }

  public function getFormId(): string {
    return 'brebo_finance_standalone_sales_invoice_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    foreach (['brebo_finance_sales_invoice_draft', 'brebo_finance_sales_invoice_draft_line'] as $table) {
      if (!$this->database->schema()->tableExists($table)) {
        $form['warning'] = ['#markup' => '<p><strong>' . $this->t('Factuurconcept-opslag ontbreekt. Voer eerst database-updates uit.') . '</strong></p>'];
        return $form;
      }
    }

    $today = date('Y-m-d');
    $due = date('Y-m-d', strtotime('+30 days'));
    $form['intro'] = ['#markup' => '<p>' . $this->t('Gebruik dit alleen wanneer de verkoopfactuur niet bij een project hoort. Er wordt nu alleen een concept gemaakt; het definitieve factuurnummer ontstaat pas bij verzenden.') . '</p>'];
    $form['customer'] = ['#type' => 'fieldset', '#title' => $this->t('Debiteur')];
    $form['customer']['customer_name'] = ['#type' => 'textfield', '#title' => $this->t('Organisatie / debiteur'), '#required' => TRUE, '#maxlength' => 255];
    $form['customer']['customer_ref'] = ['#type' => 'textfield', '#title' => $this->t('Relatie- of klantreferentie'), '#maxlength' => 255, '#description' => $this->t('Optioneel. Later koppelen we dit rechtstreeks aan de centrale Relaties-administratie.')];

    $form['invoice'] = ['#type' => 'fieldset', '#title' => $this->t('Factuur')];
    $form['invoice']['invoice_date'] = ['#type' => 'date', '#title' => $this->t('Factuurdatum'), '#required' => TRUE, '#default_value' => $today];
    $form['invoice']['due_date'] = ['#type' => 'date', '#title' => $this->t('Vervaldatum'), '#required' => TRUE, '#default_value' => $due];
    $form['invoice']['description'] = ['#type' => 'textfield', '#title' => $this->t('Omschrijving'), '#maxlength' => 255, '#required' => TRUE];

    $form['lines'] = ['#type' => 'fieldset', '#title' => $this->t('Factuurregels')];
    $form['lines']['help'] = ['#markup' => '<p>' . $this->t('Vul minimaal één regel in. Bedragen worden bij opslaan berekend en als concept vastgelegd.') . '</p>'];
    for ($i = 1; $i <= 5; $i++) {
      $form['lines']['line_' . $i] = ['#type' => 'details', '#title' => $this->t('Regel @n', ['@n' => $i]), '#open' => $i === 1];
      $form['lines']['line_' . $i]['description_' . $i] = ['#type' => 'textfield', '#title' => $this->t('Omschrijving'), '#maxlength' => 255];
      $form['lines']['line_' . $i]['quantity_' . $i] = ['#type' => 'number', '#title' => $this->t('Aantal'), '#step' => 0.0001, '#default_value' => 1];
      $form['lines']['line_' . $i]['unit_' . $i] = ['#type' => 'textfield', '#title' => $this->t('Eenheid'), '#maxlength' => 32];
      $form['lines']['line_' . $i]['unit_price_' . $i] = ['#type' => 'number', '#title' => $this->t('Prijs excl. btw'), '#step' => 0.01];
      $form['lines']['line_' . $i]['vat_rate_' . $i] = ['#type' => 'select', '#title' => $this->t('Btw'), '#options' => ['21' => '21%', '9' => '9%', '0' => '0%'], '#default_value' => '21'];
    }

    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Concept opslaan'), '#button_type' => 'primary'];
    $form['actions']['cancel'] = ['#type' => 'link', '#title' => $this->t('Annuleren'), '#url' => Url::fromRoute('brebo_finance.sales_workspace'), '#attributes' => ['class' => ['button']]];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ((string) $form_state->getValue('due_date') < (string) $form_state->getValue('invoice_date')) {
      $form_state->setErrorByName('due_date', $this->t('De vervaldatum kan niet vóór de factuurdatum liggen.'));
    }
    $hasLine = FALSE;
    for ($i = 1; $i <= 5; $i++) {
      $description = trim((string) $form_state->getValue('description_' . $i));
      $price = (float) ($form_state->getValue('unit_price_' . $i) ?? 0);
      if ($description !== '' && abs($price) > 0.00001) {
        $hasLine = TRUE;
        break;
      }
    }
    if (!$hasLine) {
      $form_state->setErrorByName('lines', $this->t('Vul minimaal één factuurregel met omschrijving en prijs in.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $lines = [];
    $totals = ['ex' => 0.0, 'vat' => 0.0, 'inc' => 0.0];
    for ($i = 1; $i <= 5; $i++) {
      $description = trim((string) $form_state->getValue('description_' . $i));
      $unitPrice = (float) ($form_state->getValue('unit_price_' . $i) ?? 0);
      if ($description === '' || abs($unitPrice) < 0.00001) {
        continue;
      }
      $quantity = (float) ($form_state->getValue('quantity_' . $i) ?? 1);
      $vatRate = (float) ($form_state->getValue('vat_rate_' . $i) ?? 21);
      $amountEx = round($quantity * $unitPrice, 4);
      $vat = round($amountEx * ($vatRate / 100), 4);
      $amountInc = round($amountEx + $vat, 4);
      $totals['ex'] += $amountEx;
      $totals['vat'] += $vat;
      $totals['inc'] += $amountInc;
      $lines[] = [
        'description' => $description,
        'quantity' => $quantity,
        'unit' => trim((string) $form_state->getValue('unit_' . $i)),
        'unit_price_ex_vat' => $unitPrice,
        'amount_ex_vat' => $amountEx,
        'vat_rate' => $vatRate,
        'vat_amount' => $vat,
        'amount_inc_vat' => $amountInc,
      ];
    }

    $actor = (int) $this->currentUser()->id();
    $now = time();
    $draftNumber = 'CON-LOS-' . date('Ymd-His');
    $transaction = $this->database->startTransaction();
    try {
      $draftId = (int) $this->database->insert('brebo_finance_sales_invoice_draft')->fields([
        'project_nid' => 0,
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
          'project_nid' => 0,
          'line_number' => $delta + 1,
          'source_type' => 'standalone',
          'source_id' => 0,
          'description' => $line['description'],
          'amount_ex_vat' => number_format($line['amount_ex_vat'], 4, '.', ''),
          'vat_code' => 'NL_' . (string) (int) $line['vat_rate'],
          'vat_rate' => number_format($line['vat_rate'], 4, '.', ''),
          'vat_amount' => number_format($line['vat_amount'], 4, '.', ''),
          'amount_inc_vat' => number_format($line['amount_inc_vat'], 4, '.', ''),
          'created' => $now,
          'created_by' => $actor,
        ])->execute();
      }

      $this->keyValueFactory->get('brebo_finance.sales_invoice_draft_context')->set((string) $draftId, [
        'origin' => 'standalone',
        'customer_name' => trim((string) $form_state->getValue('customer_name')),
        'customer_ref' => trim((string) $form_state->getValue('customer_ref')),
        'lines' => $lines,
      ]);
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }

    $this->messenger()->addStatus($this->t('Losse factuur @number is als concept opgeslagen. Er is nog geen definitief factuurnummer.', ['@number' => $draftNumber]));
    $form_state->setRedirect('brebo_finance.sales_workspace');
  }

}
