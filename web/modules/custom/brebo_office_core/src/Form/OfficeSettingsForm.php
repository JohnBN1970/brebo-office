<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/** Manages visible BREBO Office policy and operating defaults. */
final class OfficeSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames(): array {
    return ['brebo_office_core.settings', 'brebo_finance.sales'];
  }

  public function getFormId(): string {
    return 'brebo_office_core_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('brebo_office_core.settings');
    $sales = $this->config('brebo_finance.sales');

    $form['intro'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['messages', 'messages--status']],
      'text' => [
        '#markup' => $this->t('<strong>BREBO blijft de baas.</strong> Dit is de centrale instellingenpagina van BREBO Office. Onderliggende modules gebruiken hun eigen configuratie, maar beheer gebeurt hier op één plek. Wachtwoorden en API-sleutels worden hier nooit opgeslagen.'),
      ],
    ];

    $form['organization'] = [
      '#type' => 'details',
      '#title' => $this->t('Bedrijfsidentiteit'),
      '#open' => TRUE,
    ];
    $form['organization']['trade_name'] = ['#type' => 'textfield', '#title' => $this->t('Handelsnaam'), '#default_value' => $config->get('organization.trade_name') ?? 'BREBO', '#required' => TRUE];
    $form['organization']['legal_name'] = ['#type' => 'textfield', '#title' => $this->t('Statutaire naam'), '#default_value' => $config->get('organization.legal_name') ?? 'BREBO Bouw en Advies B.V.'];
    $form['organization']['registration_number'] = ['#type' => 'textfield', '#title' => $this->t('KVK-nummer'), '#default_value' => $config->get('organization.registration_number') ?? ''];
    $form['organization']['general_email'] = ['#type' => 'email', '#title' => $this->t('Algemeen e-mailadres'), '#default_value' => $config->get('organization.general_email') ?? ''];
    $form['organization']['general_phone'] = ['#type' => 'textfield', '#title' => $this->t('Algemeen telefoonnummer'), '#default_value' => $config->get('organization.general_phone') ?? ''];
    $form['organization']['address'] = ['#type' => 'textfield', '#title' => $this->t('Vestigingsadres'), '#default_value' => $config->get('organization.address') ?? ''];

    $form['project'] = ['#type' => 'details', '#title' => $this->t('Project- en communicatiestandaarden'), '#open' => TRUE];
    $form['project']['default_project_status'] = ['#type' => 'select', '#title' => $this->t('Standaardstatus nieuw project'), '#options' => ['Concept' => $this->t('Concept'), 'Intake' => $this->t('Intake'), 'Voorbereiding' => $this->t('Voorbereiding')], '#default_value' => $config->get('project.default_project_status') ?? 'Concept'];
    $form['project']['response_days'] = ['#type' => 'number', '#title' => $this->t('Standaard reactietermijn'), '#field_suffix' => $this->t('kalenderdagen'), '#default_value' => $config->get('project.response_days') ?? 5, '#min' => 1, '#max' => 90, '#required' => TRUE];
    $form['project']['warning_days'] = ['#type' => 'number', '#title' => $this->t('Oranje waarschuwing vóór termijn'), '#field_suffix' => $this->t('dagen'), '#default_value' => $config->get('project.warning_days') ?? 2, '#min' => 0, '#max' => 30, '#required' => TRUE];
    $form['project']['project_code_prefix'] = ['#type' => 'textfield', '#title' => $this->t('Standaard projectcodeprefix'), '#default_value' => $config->get('project.project_code_prefix') ?? 'BREBO', '#maxlength' => 16];
    $form['project']['timezone'] = ['#type' => 'select', '#title' => $this->t('Bedrijfstijdzone'), '#options' => ['Europe/Amsterdam' => 'Europe/Amsterdam', 'Europe/Brussels' => 'Europe/Brussels'], '#default_value' => $config->get('project.timezone') ?? 'Europe/Amsterdam'];

    $form['finance'] = ['#type' => 'details', '#title' => $this->t('Financiële beleidsgrenzen')];
    $form['finance']['default_margin_percent'] = ['#type' => 'number', '#title' => $this->t('Standaard beoogde marge'), '#field_suffix' => '%', '#default_value' => $config->get('finance.default_margin_percent') ?? 30, '#min' => 0, '#max' => 100, '#step' => 0.01];
    $form['finance']['management_warning_amount'] = ['#type' => 'number', '#title' => $this->t('Directiesignaal vanaf financiële impact'), '#field_prefix' => '€ ', '#default_value' => $config->get('finance.management_warning_amount') ?? 5000, '#min' => 0, '#step' => 100];

    $rates = $sales->get('vat_rates');
    if (!is_array($rates) || $rates === []) {
      $rates = [
        'NL_21' => ['label' => '21%', 'rate' => 21, 'treatment' => 'normal', 'active' => TRUE],
        'NL_9' => ['label' => '9%', 'rate' => 9, 'treatment' => 'normal', 'active' => TRUE],
        'NL_REVERSE' => ['label' => 'Btw verlegd', 'rate' => 0, 'treatment' => 'reverse_charge', 'active' => TRUE],
      ];
    }
    $vatRows = [];
    foreach ($rates as $code => $rate) {
      if (!is_array($rate)) continue;
      $vatRows[] = implode('|', [(string) $code, (string) ($rate['label'] ?? $code), (string) ($rate['rate'] ?? 0), (string) ($rate['treatment'] ?? 'normal'), !empty($rate['active']) ? '1' : '0']);
    }

    $form['sales'] = [
      '#type' => 'details',
      '#title' => $this->t('Verkoop & facturatie'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#attributes' => ['id' => 'sales-settings'],
      '#description' => $this->t('Canonieke verkoopinstellingen. De Verkoopmodule verwijst voortaan naar deze centrale sectie.'),
    ];
    $form['sales']['prefix'] = ['#type' => 'textfield', '#title' => $this->t('Factuurnummer prefix'), '#default_value' => (string) ($sales->get('numbering.prefix') ?? 'VF-'), '#maxlength' => 16];
    $form['sales']['include_year'] = ['#type' => 'checkbox', '#title' => $this->t('Jaar opnemen in factuurnummer'), '#default_value' => (bool) ($sales->get('numbering.include_year') ?? TRUE)];
    $form['sales']['separator'] = ['#type' => 'textfield', '#title' => $this->t('Scheidingsteken'), '#default_value' => (string) ($sales->get('numbering.separator') ?? '-'), '#maxlength' => 4];
    $form['sales']['digits'] = ['#type' => 'number', '#title' => $this->t('Aantal cijfers volgnummer'), '#default_value' => (int) ($sales->get('numbering.digits') ?? 4), '#min' => 1, '#max' => 10, '#required' => TRUE];
    $form['sales']['start_number'] = ['#type' => 'number', '#title' => $this->t('Startnummer'), '#default_value' => (int) ($sales->get('numbering.start_number') ?? 1), '#min' => 1, '#required' => TRUE, '#description' => $this->t('Wijzigt niet automatisch de actuele cursor van een reeds lopende reeks.')];
    $form['sales']['reset_yearly'] = ['#type' => 'checkbox', '#title' => $this->t('Volgnummer jaarlijks opnieuw starten'), '#default_value' => (bool) ($sales->get('numbering.reset_yearly') ?? TRUE)];
    $form['sales']['default_payment_term_days'] = ['#type' => 'number', '#title' => $this->t('Standaard betaaltermijn'), '#field_suffix' => $this->t('dagen'), '#default_value' => (int) ($sales->get('numbering.default_payment_term_days') ?? 14), '#min' => 0, '#max' => 365, '#required' => TRUE, '#description' => $this->t('BREBO-standaard is 14 dagen. Klant, project, termijn of factuur kan gecontroleerd afwijken.')];
    $form['sales']['vat_rates'] = ['#type' => 'textarea', '#title' => $this->t('Btw-codes'), '#default_value' => implode("\n", $vatRows), '#rows' => 6, '#required' => TRUE, '#description' => $this->t('Eén tarief per regel: CODE|Naam|Percentage|behandeling|actief. Bijvoorbeeld NL_21|21%|21|normal|1. Historische facturen behouden hun btw-snapshot.')];
    $form['sales']['g_enabled'] = ['#type' => 'checkbox', '#title' => $this->t('G-rekening beschikbaar maken op verkoopfacturen'), '#default_value' => (bool) ($sales->get('g_account.enabled') ?? FALSE)];
    $form['sales']['g_default_percentage'] = ['#type' => 'number', '#title' => $this->t('Standaard percentage naar G-rekening'), '#default_value' => (float) ($sales->get('g_account.default_percentage') ?? 0), '#min' => 0, '#max' => 100, '#step' => 0.01];
    $form['sales']['regular_iban'] = ['#type' => 'textfield', '#title' => $this->t('Regulier IBAN BREBO'), '#default_value' => (string) ($sales->get('g_account.regular_iban') ?? ''), '#maxlength' => 64];
    $form['sales']['g_iban'] = ['#type' => 'textfield', '#title' => $this->t('G-rekening IBAN BREBO'), '#default_value' => (string) ($sales->get('g_account.g_iban') ?? ''), '#maxlength' => 64];

    $form['mail'] = ['#type' => 'details', '#title' => $this->t('Mailinrichting')];
    $form['mail']['domain'] = ['#type' => 'textfield', '#title' => $this->t('Maildomein'), '#default_value' => $config->get('mail.domain') ?? 'brebobv.nl'];
    $form['mail']['sender_name'] = ['#type' => 'textfield', '#title' => $this->t('Standaard afzendernaam'), '#default_value' => $config->get('mail.sender_name') ?? 'BREBO Office'];
    $form['mail']['sender_address'] = ['#type' => 'email', '#title' => $this->t('Standaard afzenderadres'), '#default_value' => $config->get('mail.sender_address') ?? ''];
    $form['mail']['project_inbox'] = ['#type' => 'email', '#title' => $this->t('Centrale projectinbox'), '#default_value' => $config->get('mail.project_inbox') ?? ''];
    $form['mail']['migration_status'] = ['#type' => 'select', '#title' => $this->t('Migratiestatus'), '#options' => ['zoho_active' => $this->t('Zoho actief'), 'transition' => $this->t('Parallelle overgang'), 'brebo_active' => $this->t('BREBO-mail actief')], '#default_value' => $config->get('mail.migration_status') ?? 'zoho_active'];
    $form['mail']['automatic_dossier_linking'] = ['#type' => 'checkbox', '#title' => $this->t('Inkomende projectmail automatisch voor dossierkoppeling aanbieden'), '#default_value' => $config->get('mail.automatic_dossier_linking') ?? TRUE];

    $form['ai'] = ['#type' => 'details', '#title' => $this->t('AI-mandaat en menselijke controle'), '#open' => TRUE];
    $form['ai']['operating_mode'] = ['#type' => 'select', '#title' => $this->t('Standaard AI-werkwijze'), '#options' => ['proposal_only' => $this->t('Alleen voorstellen'), 'within_mandate' => $this->t('Zelfstandig binnen vastgesteld mandaat')], '#default_value' => $config->get('ai.operating_mode') ?? 'within_mandate'];
    $form['ai']['financial_limit'] = ['#type' => 'number', '#title' => $this->t('Maximale financiële impact zonder voorafgaand akkoord'), '#field_prefix' => '€ ', '#default_value' => $config->get('ai.financial_limit') ?? 0, '#min' => 0, '#step' => 100, '#description' => $this->t('0 betekent dat iedere financieel bindende handeling vooraf akkoord vereist.')];
    $form['ai']['create_internal_actions'] = ['#type' => 'checkbox', '#title' => $this->t('Interne acties automatisch laten aanmaken'), '#default_value' => $config->get('ai.create_internal_actions') ?? TRUE];
    $form['ai']['send_external_messages'] = ['#type' => 'checkbox', '#title' => $this->t('Externe berichten zelfstandig laten verzenden'), '#default_value' => $config->get('ai.send_external_messages') ?? FALSE, '#description' => $this->t('Standaard uit: externe communicatie blijft concept totdat een bevoegde gebruiker akkoord geeft.')];
    $form['ai']['change_canonical_rules'] = ['#type' => 'checkbox', '#title' => $this->t('Canonieke bedrijfsregels zelfstandig laten wijzigen'), '#default_value' => FALSE, '#disabled' => TRUE, '#description' => $this->t('Altijd uit. Vastgestelde bedrijfsregels vereisen expliciet menselijk besluit.')];
    $form['ai']['audit_retention_days'] = ['#type' => 'number', '#title' => $this->t('Bewaartermijn AI-audittrail'), '#field_suffix' => $this->t('dagen'), '#default_value' => $config->get('ai.audit_retention_days') ?? 2555, '#min' => 365, '#max' => 3650];

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ((int) $form_state->getValue('warning_days') >= (int) $form_state->getValue('response_days')) {
      $form_state->setErrorByName('warning_days', $this->t('De waarschuwing moet vóór het einde van de reactietermijn vallen.'));
    }
    $sales = (array) $form_state->getValue('sales');
    try {
      $this->parseVatRates((string) ($sales['vat_rates'] ?? ''));
    }
    catch (\Throwable $e) {
      $form_state->setErrorByName('sales][vat_rates', $e->getMessage());
    }
    if (!empty($sales['g_enabled']) && trim((string) ($sales['g_iban'] ?? '')) === '') {
      $form_state->setErrorByName('sales][g_iban', $this->t('Vul het G-rekening IBAN in wanneer G-rekening verkoop actief is.'));
    }
    parent::validateForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('brebo_office_core.settings');
    foreach ([
      'organization' => ['trade_name', 'legal_name', 'registration_number', 'general_email', 'general_phone', 'address'],
      'project' => ['default_project_status', 'response_days', 'warning_days', 'project_code_prefix', 'timezone'],
      'finance' => ['default_margin_percent', 'management_warning_amount'],
      'mail' => ['domain', 'sender_name', 'sender_address', 'project_inbox', 'migration_status', 'automatic_dossier_linking'],
      'ai' => ['operating_mode', 'financial_limit', 'create_internal_actions', 'send_external_messages', 'audit_retention_days'],
    ] as $section => $keys) {
      foreach ($keys as $key) {
        $config->set("$section.$key", $form_state->getValue($key));
      }
    }
    $config->set('ai.change_canonical_rules', FALSE)->save();

    $salesValues = (array) $form_state->getValue('sales');
    $this->configFactory->getEditable('brebo_finance.sales')
      ->set('numbering.prefix', trim((string) ($salesValues['prefix'] ?? 'VF-')))
      ->set('numbering.include_year', (bool) ($salesValues['include_year'] ?? TRUE))
      ->set('numbering.separator', (string) ($salesValues['separator'] ?? '-'))
      ->set('numbering.digits', (int) ($salesValues['digits'] ?? 4))
      ->set('numbering.start_number', (int) ($salesValues['start_number'] ?? 1))
      ->set('numbering.reset_yearly', (bool) ($salesValues['reset_yearly'] ?? TRUE))
      ->set('numbering.default_payment_term_days', (int) ($salesValues['default_payment_term_days'] ?? 14))
      ->set('vat_rates', $this->parseVatRates((string) ($salesValues['vat_rates'] ?? '')))
      ->set('g_account.enabled', (bool) ($salesValues['g_enabled'] ?? FALSE))
      ->set('g_account.default_percentage', (float) ($salesValues['g_default_percentage'] ?? 0))
      ->set('g_account.regular_iban', strtoupper(str_replace(' ', '', trim((string) ($salesValues['regular_iban'] ?? '')))))
      ->set('g_account.g_iban', strtoupper(str_replace(' ', '', trim((string) ($salesValues['g_iban'] ?? '')))))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /** @return array<string,array{label:string,rate:float,treatment:string,active:bool}> */
  private function parseVatRates(string $raw): array {
    $result = [];
    foreach (preg_split('/\R/', trim($raw)) ?: [] as $lineNo => $line) {
      if (trim($line) === '') continue;
      $parts = array_map('trim', explode('|', $line));
      if (count($parts) !== 5) throw new \InvalidArgumentException('Btw-regel ' . ($lineNo + 1) . ' moet exact vijf velden bevatten.');
      [$code, $label, $rate, $treatment, $active] = $parts;
      if ($code === '' || $label === '' || !is_numeric($rate)) throw new \InvalidArgumentException('Ongeldige btw-regel ' . ($lineNo + 1) . '.');
      $rateFloat = (float) $rate;
      if ($rateFloat < 0 || $rateFloat > 100) throw new \InvalidArgumentException('Btw-percentage moet tussen 0 en 100 liggen.');
      if (!in_array($treatment, ['normal', 'reverse_charge', 'exempt'], TRUE)) throw new \InvalidArgumentException('Btw-behandeling moet normal, reverse_charge of exempt zijn.');
      if (isset($result[$code])) throw new \InvalidArgumentException('Dubbele btw-code: ' . $code);
      $result[$code] = ['label' => $label, 'rate' => $rateFloat, 'treatment' => $treatment, 'active' => in_array(strtolower($active), ['1', 'true', 'yes', 'ja'], TRUE)];
    }
    if ($result === []) throw new \InvalidArgumentException('Minimaal één btw-code is vereist.');
    return $result;
  }

}
