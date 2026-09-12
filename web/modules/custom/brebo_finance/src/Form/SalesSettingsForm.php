<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Form;

use Drupal\brebo_finance\Service\SalesInvoiceNumberManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Configures BREBO-owned sales invoice settings. */
final class SalesSettingsForm extends ConfigFormBase {

  private const CONFIG_NAME = 'brebo_finance.sales';

  public function __construct(ConfigFactoryInterface $configFactory, private readonly SalesInvoiceNumberManager $numberManager) {
    parent::__construct($configFactory);
  }

  public static function create(ContainerInterface $container): static {
    return new static($container->get('config.factory'), new SalesInvoiceNumberManager($container->get('config.factory'), $container->get('keyvalue'), $container->get('lock')));
  }

  public function getFormId(): string { return 'brebo_finance_sales_settings'; }
  protected function getEditableConfigNames(): array { return [self::CONFIG_NAME]; }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::CONFIG_NAME);
    $form['intro'] = ['#markup' => '<p>BREBO Office is eigenaar van factuurnummer, btw-behandeling, betaalinstructies, definitieve factuur en verzending. Moneybird ontvangt daarna dezelfde definitieve factuur uitsluitend voor de financiële administratie.</p>'];

    $form['numbering'] = ['#type' => 'details', '#title' => $this->t('Factuurnummering'), '#open' => TRUE, '#tree' => TRUE];
    $form['numbering']['prefix'] = ['#type' => 'textfield', '#title' => $this->t('Prefix'), '#default_value' => (string) ($config->get('numbering.prefix') ?? 'VF-'), '#maxlength' => 16];
    $form['numbering']['include_year'] = ['#type' => 'checkbox', '#title' => $this->t('Jaar opnemen in factuurnummer'), '#default_value' => (bool) ($config->get('numbering.include_year') ?? TRUE)];
    $form['numbering']['separator'] = ['#type' => 'textfield', '#title' => $this->t('Scheidingsteken'), '#default_value' => (string) ($config->get('numbering.separator') ?? '-'), '#maxlength' => 4];
    $form['numbering']['digits'] = ['#type' => 'number', '#title' => $this->t('Aantal cijfers volgnummer'), '#default_value' => (int) ($config->get('numbering.digits') ?? 4), '#min' => 1, '#max' => 10, '#required' => TRUE];
    $form['numbering']['start_number'] = ['#type' => 'number', '#title' => $this->t('Startnummer'), '#default_value' => (int) ($config->get('numbering.start_number') ?? 1), '#min' => 1, '#required' => TRUE, '#description' => $this->t('Wijzigt niet automatisch de actuele cursor van een reeds lopende reeks.')];
    $form['numbering']['reset_yearly'] = ['#type' => 'checkbox', '#title' => $this->t('Volgnummer jaarlijks opnieuw starten'), '#default_value' => (bool) ($config->get('numbering.reset_yearly') ?? TRUE)];
    $form['numbering']['default_payment_term_days'] = ['#type' => 'number', '#title' => $this->t('Standaard betaaltermijn in dagen'), '#default_value' => (int) ($config->get('numbering.default_payment_term_days') ?? 14), '#min' => 0, '#max' => 365, '#required' => TRUE, '#description' => $this->t('BREBO-standaard is 14 dagen. Klant, project, termijn of factuur kan deze waarde gecontroleerd overschrijven.')];

    $rates = $config->get('vat_rates');
    if (!is_array($rates) || $rates === []) {
      $rates = ['NL_21' => ['label' => '21%', 'rate' => 21, 'treatment' => 'normal', 'active' => TRUE], 'NL_9' => ['label' => '9%', 'rate' => 9, 'treatment' => 'normal', 'active' => TRUE], 'NL_REVERSE' => ['label' => 'Btw verlegd', 'rate' => 0, 'treatment' => 'reverse_charge', 'active' => TRUE]];
    }
    $rows = [];
    foreach ($rates as $code => $rate) {
      if (!is_array($rate)) continue;
      $rows[] = implode('|', [(string) $code, (string) ($rate['label'] ?? $code), (string) ($rate['rate'] ?? 0), (string) ($rate['treatment'] ?? 'normal'), !empty($rate['active']) ? '1' : '0']);
    }
    $form['vat'] = ['#type' => 'details', '#title' => $this->t('Btw-tarieven verkoop'), '#open' => TRUE];
    $form['vat']['rates'] = ['#type' => 'textarea', '#title' => $this->t('Btw-codes'), '#default_value' => implode("\n", $rows), '#rows' => 6, '#required' => TRUE, '#description' => $this->t('Eén tarief per regel: CODE|Naam|Percentage|behandeling|actief. Voorbeeld: NL_21|21%|21|normal|1. Voor btw verlegd: NL_REVERSE|Btw verlegd|0|reverse_charge|1. Nieuwe wettelijke tarieven kunnen hier worden toegevoegd; historische definitieve facturen houden hun eigen snapshot.')];

    $form['g_account'] = ['#type' => 'details', '#title' => $this->t('G-rekening verkoop'), '#open' => TRUE, '#tree' => TRUE];
    $form['g_account']['enabled'] = ['#type' => 'checkbox', '#title' => $this->t('G-rekening beschikbaar maken op verkoopfacturen'), '#default_value' => (bool) ($config->get('g_account.enabled') ?? FALSE)];
    $form['g_account']['default_percentage'] = ['#type' => 'number', '#title' => $this->t('Standaard percentage naar G-rekening'), '#default_value' => (float) ($config->get('g_account.default_percentage') ?? 0), '#min' => 0, '#max' => 100, '#step' => 0.01, '#description' => $this->t('Dit is alleen een voorstel. Project, termijn en factuur mogen gecontroleerd afwijken.')];
    $form['g_account']['regular_iban'] = ['#type' => 'textfield', '#title' => $this->t('Regulier IBAN BREBO'), '#default_value' => (string) ($config->get('g_account.regular_iban') ?? ''), '#maxlength' => 64];
    $form['g_account']['g_iban'] = ['#type' => 'textfield', '#title' => $this->t('G-rekening IBAN BREBO'), '#default_value' => (string) ($config->get('g_account.g_iban') ?? ''), '#maxlength' => 64, '#description' => $this->t('Vrijgave van een factuur met G-rekening wordt geblokkeerd wanneer dit IBAN ontbreekt.')];

    $year = (int) date('Y');
    $form['example'] = ['#type' => 'item', '#title' => $this->t('Voorbeeld huidig formaat'), '#markup' => '<code>' . htmlspecialchars($this->numberManager->format(max(1, (int) ($config->get('numbering.start_number') ?? 1)), $year)) . '</code>'];
    $form['manual_reservation'] = ['#type' => 'details', '#title' => $this->t('Factuurnummer vooruit reserveren'), '#open' => TRUE];
    $form['manual_reservation']['invoice_number'] = ['#type' => 'textfield', '#title' => $this->t('Factuurnummer'), '#description' => $this->t('Reserveer bijvoorbeeld een later nummer voor een factuur met toekomstige factuurdatum. De automatische reeks blijft vanaf de bestaande cursor doorlopen en slaat dit nummer later automatisch over.'), '#maxlength' => 64];
    $form['manual_reservation']['reserve'] = ['#type' => 'submit', '#value' => $this->t('Nummer reserveren'), '#submit' => ['::reserveNumber'], '#limit_validation_errors' => [['manual_reservation', 'invoice_number']]];
    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    try { $this->parseVatRates((string) $form_state->getValue('rates')); }
    catch (\Throwable $e) { $form_state->setErrorByName('rates', $e->getMessage()); }
    $g = (array) $form_state->getValue('g_account');
    if (!empty($g['enabled']) && trim((string) ($g['g_iban'] ?? '')) === '') $form_state->setErrorByName('g_account][g_iban', $this->t('Vul het G-rekening IBAN in wanneer G-rekening verkoop actief is.'));
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = (array) $form_state->getValue('numbering');
    $g = (array) $form_state->getValue('g_account');
    $this->configFactory->getEditable(self::CONFIG_NAME)
      ->set('numbering.prefix', trim((string) ($values['prefix'] ?? '')))
      ->set('numbering.include_year', (bool) ($values['include_year'] ?? FALSE))
      ->set('numbering.separator', (string) ($values['separator'] ?? '-'))
      ->set('numbering.digits', (int) ($values['digits'] ?? 4))
      ->set('numbering.start_number', (int) ($values['start_number'] ?? 1))
      ->set('numbering.reset_yearly', (bool) ($values['reset_yearly'] ?? FALSE))
      ->set('numbering.default_payment_term_days', (int) ($values['default_payment_term_days'] ?? 14))
      ->set('vat_rates', $this->parseVatRates((string) $form_state->getValue('rates')))
      ->set('g_account.enabled', (bool) ($g['enabled'] ?? FALSE))
      ->set('g_account.default_percentage', (float) ($g['default_percentage'] ?? 0))
      ->set('g_account.regular_iban', strtoupper(str_replace(' ', '', trim((string) ($g['regular_iban'] ?? '')))))
      ->set('g_account.g_iban', strtoupper(str_replace(' ', '', trim((string) ($g['g_iban'] ?? '')))))
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

  public function reserveNumber(array &$form, FormStateInterface $form_state): void {
    $values = (array) $form_state->getValue('manual_reservation');
    $number = trim((string) ($values['invoice_number'] ?? ''));
    if ($number === '') { $this->messenger()->addError($this->t('Vul een factuurnummer in om te reserveren.')); return; }
    try { $this->numberManager->reserveManual($number); $this->messenger()->addStatus($this->t('Factuurnummer @number is vooruit gereserveerd. De automatische reeks blijft ongewijzigd.', ['@number' => $number])); }
    catch (\Throwable $exception) { $this->messenger()->addError($exception->getMessage()); }
    $form_state->setRebuild(TRUE);
  }
}
