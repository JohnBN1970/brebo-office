<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/** Central management UI for legal entities and document numbering. */
final class AdministrationSettingsForm extends ConfigFormBase {

  private const SERIES = [
    'project' => 'Project',
    'quotation' => 'Offerte',
    'sales_invoice' => 'Verkoopfactuur',
    'credit_invoice' => 'Creditfactuur',
    'purchase' => 'Inkoop',
    'contract' => 'Contract',
    'report' => 'Rapport',
    'inspection' => 'Inspectie',
    'document' => 'Algemeen document',
  ];

  protected function getEditableConfigNames(): array {
    return ['brebo_office_core.settings'];
  }

  public function getFormId(): string {
    return 'brebo_office_core_administration_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('brebo_office_core.settings');
    $administrations = $config->get('administrations');
    if (!is_array($administrations) || $administrations === []) {
      $administrations = ['primary' => $this->legacyAdministration($config)];
    }

    $form['intro'] = [
      '#markup' => '<p><strong>Administraties & entiteiten</strong><br>Iedere administratie heeft een eigen juridische identiteit, branding, bank- en integratiegegevens en eigen nummerreeksen. Uitgegeven nummers worden nooit hernummerd.</p>',
    ];
    $form['primary_administration'] = [
      '#type' => 'select',
      '#title' => $this->t('Primaire administratie'),
      '#options' => array_combine(array_keys($administrations), array_map(static fn(array $a): string => (string) ($a['trade_name'] ?? $a['legal_name'] ?? $a['code'] ?? 'Administratie'), $administrations)),
      '#default_value' => $config->get('primary_administration') ?? array_key_first($administrations),
      '#required' => TRUE,
    ];

    $form['administrations'] = ['#type' => 'vertical_tabs', '#tree' => TRUE];
    foreach ($administrations as $code => $administration) {
      $form['administrations'][$code] = [
        '#type' => 'details',
        '#title' => $administration['trade_name'] ?? $code,
        '#group' => 'administrations',
        '#tree' => TRUE,
      ];
      $a =& $form['administrations'][$code];
      $a['code'] = ['#type' => 'textfield', '#title' => $this->t('Administratiecode'), '#default_value' => $code, '#disabled' => TRUE];
      $a['active'] = ['#type' => 'checkbox', '#title' => $this->t('Actief'), '#default_value' => $administration['active'] ?? TRUE];
      $a['trade_name'] = ['#type' => 'textfield', '#title' => $this->t('Handelsnaam'), '#default_value' => $administration['trade_name'] ?? '', '#required' => TRUE];
      $a['legal_name'] = ['#type' => 'textfield', '#title' => $this->t('Statutaire naam'), '#default_value' => $administration['legal_name'] ?? ''];
      $a['registration_number'] = ['#type' => 'textfield', '#title' => $this->t('KVK / ondernemingsnummer'), '#default_value' => $administration['registration_number'] ?? ''];
      $a['vat_number'] = ['#type' => 'textfield', '#title' => $this->t('Btw-nummer'), '#default_value' => $administration['vat_number'] ?? ''];
      $a['address'] = ['#type' => 'textfield', '#title' => $this->t('Adres'), '#default_value' => $administration['address'] ?? ''];
      $a['postal_code'] = ['#type' => 'textfield', '#title' => $this->t('Postcode'), '#default_value' => $administration['postal_code'] ?? ''];
      $a['city'] = ['#type' => 'textfield', '#title' => $this->t('Plaats'), '#default_value' => $administration['city'] ?? ''];
      $a['country'] = ['#type' => 'textfield', '#title' => $this->t('Landcode'), '#default_value' => $administration['country'] ?? 'NL', '#maxlength' => 2];
      $a['general_email'] = ['#type' => 'email', '#title' => $this->t('Algemeen e-mailadres'), '#default_value' => $administration['general_email'] ?? ''];
      $a['general_phone'] = ['#type' => 'textfield', '#title' => $this->t('Telefoon'), '#default_value' => $administration['general_phone'] ?? ''];
      $a['website'] = ['#type' => 'url', '#title' => $this->t('Website'), '#default_value' => $administration['website'] ?? ''];
      $a['logo_uri'] = ['#type' => 'textfield', '#title' => $this->t('Logo bestand/URI'), '#default_value' => $administration['logo_uri'] ?? '', '#description' => $this->t('Publieke of private Drupal-bestands-URI. Geen API-sleutels.')];
      $a['logo_compact_uri'] = ['#type' => 'textfield', '#title' => $this->t('Compact logo bestand/URI'), '#default_value' => $administration['logo_compact_uri'] ?? ''];
      $a['currency'] = ['#type' => 'textfield', '#title' => $this->t('Valuta'), '#default_value' => $administration['currency'] ?? 'EUR', '#maxlength' => 3];
      $a['timezone'] = ['#type' => 'textfield', '#title' => $this->t('Tijdzone'), '#default_value' => $administration['timezone'] ?? 'Europe/Amsterdam'];
      $a['default_iban'] = ['#type' => 'textfield', '#title' => $this->t('Standaard IBAN'), '#default_value' => $administration['default_iban'] ?? ''];
      $a['bic'] = ['#type' => 'textfield', '#title' => $this->t('BIC'), '#default_value' => $administration['bic'] ?? ''];
      $a['moneybird_administration_id'] = ['#type' => 'textfield', '#title' => $this->t('Moneybird administratie-ID'), '#default_value' => $administration['moneybird_administration_id'] ?? ''];

      $a['numbering'] = ['#type' => 'details', '#title' => $this->t('Documentnummering'), '#open' => TRUE, '#tree' => TRUE];
      foreach (self::SERIES as $series => $label) {
        $current = is_array($administration['numbering'][$series] ?? NULL) ? $administration['numbering'][$series] : $this->defaultSeries($series);
        $a['numbering'][$series] = ['#type' => 'fieldset', '#title' => $this->t($label), '#tree' => TRUE];
        $s =& $a['numbering'][$series];
        $s['prefix'] = ['#type' => 'textfield', '#title' => $this->t('Prefix'), '#default_value' => $current['prefix'] ?? '', '#maxlength' => 16];
        $s['include_year'] = ['#type' => 'checkbox', '#title' => $this->t('Jaar opnemen'), '#default_value' => $current['include_year'] ?? TRUE];
        $s['separator'] = ['#type' => 'textfield', '#title' => $this->t('Scheidingsteken'), '#default_value' => $current['separator'] ?? '-', '#maxlength' => 4];
        $s['digits'] = ['#type' => 'number', '#title' => $this->t('Aantal cijfers'), '#default_value' => $current['digits'] ?? 4, '#min' => 1, '#max' => 10, '#required' => TRUE];
        $s['start_number'] = ['#type' => 'number', '#title' => $this->t('Startnummer'), '#default_value' => $current['start_number'] ?? 1, '#min' => 1, '#required' => TRUE];
        $s['reset_yearly'] = ['#type' => 'checkbox', '#title' => $this->t('Jaarlijks resetten'), '#default_value' => $current['reset_yearly'] ?? TRUE];
      }
      unset($a);
    }

    $form['new_administration'] = ['#type' => 'details', '#title' => $this->t('Extra administratie toevoegen')];
    $form['new_administration']['code'] = ['#type' => 'machine_name', '#title' => $this->t('Nieuwe administratiecode'), '#machine_name' => ['exists' => [$this, 'administrationCodeExists']], '#required' => FALSE];
    $form['new_administration']['trade_name'] = ['#type' => 'textfield', '#title' => $this->t('Handelsnaam nieuwe administratie')];

    return parent::buildForm($form, $form_state);
  }

  public function administrationCodeExists(string $code): bool {
    $administrations = $this->config('brebo_office_core.settings')->get('administrations');
    return is_array($administrations) && isset($administrations[$code]);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $new = (array) $form_state->getValue('new_administration');
    if (($new['code'] ?? '') !== '' && trim((string) ($new['trade_name'] ?? '')) === '') {
      $form_state->setErrorByName('new_administration][trade_name', $this->t('Vul een handelsnaam in voor de nieuwe administratie.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->configFactory->getEditable('brebo_office_core.settings');
    $administrations = (array) $form_state->getValue('administrations');
    foreach ($administrations as $code => &$administration) {
      $administration['code'] = $code;
      $administration['active'] = !empty($administration['active']);
    }
    unset($administration);

    $new = (array) $form_state->getValue('new_administration');
    $newCode = trim((string) ($new['code'] ?? ''));
    if ($newCode !== '') {
      $administrations[$newCode] = $this->blankAdministration($newCode, trim((string) $new['trade_name']));
    }

    $primary = (string) $form_state->getValue('primary_administration');
    if (!isset($administrations[$primary])) {
      $primary = (string) array_key_first($administrations);
    }
    $config->set('primary_administration', $primary)->set('administrations', $administrations)->save();
    parent::submitForm($form, $form_state);
    if ($newCode !== '') {
      $this->messenger()->addStatus($this->t('Administratie @code is toegevoegd. Open de instellingen opnieuw om de volledige gegevens en nummerreeksen in te vullen.', ['@code' => $newCode]));
    }
  }

  private function legacyAdministration($config): array {
    return [
      'code' => 'primary', 'active' => TRUE,
      'trade_name' => (string) ($config->get('organization.trade_name') ?? 'BREBO'),
      'legal_name' => (string) ($config->get('organization.legal_name') ?? 'BREBO Bouw en Advies B.V.'),
      'registration_number' => (string) ($config->get('organization.registration_number') ?? ''),
      'vat_number' => (string) ($config->get('organization.vat_number') ?? ''),
      'address' => (string) ($config->get('organization.address') ?? ''),
      'postal_code' => (string) ($config->get('organization.postal_code') ?? ''),
      'city' => (string) ($config->get('organization.city') ?? ''),
      'country' => (string) ($config->get('organization.country') ?? 'NL'),
      'general_email' => (string) ($config->get('organization.general_email') ?? ''),
      'general_phone' => (string) ($config->get('organization.general_phone') ?? ''),
      'website' => (string) ($config->get('organization.website') ?? ''),
      'logo_uri' => (string) ($config->get('organization.logo_uri') ?? ''),
      'logo_compact_uri' => (string) ($config->get('organization.logo_compact_uri') ?? ''),
      'currency' => (string) ($config->get('organization.currency') ?? 'EUR'),
      'timezone' => (string) ($config->get('project.timezone') ?? 'Europe/Amsterdam'),
      'default_iban' => (string) ($config->get('organization.default_iban') ?? ''),
      'bic' => (string) ($config->get('organization.bic') ?? ''),
      'moneybird_administration_id' => (string) ($config->get('organization.moneybird_administration_id') ?? ''),
      'numbering' => array_map(fn(string $series): array => $this->defaultSeries($series), array_keys(self::SERIES)),
    ];
  }

  private function blankAdministration(string $code, string $tradeName): array {
    $numbering = [];
    foreach (array_keys(self::SERIES) as $series) $numbering[$series] = $this->defaultSeries($series);
    return ['code' => $code, 'active' => TRUE, 'trade_name' => $tradeName, 'legal_name' => '', 'registration_number' => '', 'vat_number' => '', 'address' => '', 'postal_code' => '', 'city' => '', 'country' => 'NL', 'general_email' => '', 'general_phone' => '', 'website' => '', 'logo_uri' => '', 'logo_compact_uri' => '', 'currency' => 'EUR', 'timezone' => 'Europe/Amsterdam', 'default_iban' => '', 'bic' => '', 'moneybird_administration_id' => '', 'numbering' => $numbering];
  }

  private function defaultSeries(string $series): array {
    $prefixes = ['project' => 'BREBO', 'quotation' => 'OFF', 'sales_invoice' => 'VF', 'credit_invoice' => 'CR', 'purchase' => 'INK', 'contract' => 'CTR', 'report' => 'RAP', 'inspection' => 'INS', 'document' => 'DOC'];
    return ['prefix' => $prefixes[$series] ?? 'DOC', 'include_year' => TRUE, 'separator' => '-', 'digits' => 4, 'start_number' => 1, 'reset_yearly' => TRUE];
  }

}
