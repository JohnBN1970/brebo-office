<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Form;

use Drupal\brebo_finance\Service\SalesInvoiceNumberManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Configures BREBO-owned sales invoice numbering. */
final class SalesSettingsForm extends ConfigFormBase {

  private const CONFIG_NAME = 'brebo_finance.sales';

  public function __construct(
    ConfigFactoryInterface $configFactory,
    private readonly SalesInvoiceNumberManager $numberManager,
  ) {
    parent::__construct($configFactory);
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      new SalesInvoiceNumberManager(
        $container->get('config.factory'),
        $container->get('keyvalue'),
        $container->get('lock'),
      ),
    );
  }

  public function getFormId(): string {
    return 'brebo_finance_sales_settings';
  }

  protected function getEditableConfigNames(): array {
    return [self::CONFIG_NAME];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::CONFIG_NAME);
    $form['intro'] = [
      '#markup' => '<p>BREBO Office is eigenaar van factuurnummer, definitieve factuur en verzending. Moneybird ontvangt daarna dezelfde definitieve factuur uitsluitend voor de financiële administratie.</p>',
    ];

    $form['numbering'] = [
      '#type' => 'details',
      '#title' => $this->t('Factuurnummering'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['numbering']['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix'),
      '#default_value' => (string) ($config->get('numbering.prefix') ?? 'VF-'),
      '#maxlength' => 16,
    ];
    $form['numbering']['include_year'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Jaar opnemen in factuurnummer'),
      '#default_value' => (bool) ($config->get('numbering.include_year') ?? TRUE),
    ];
    $form['numbering']['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Scheidingsteken'),
      '#default_value' => (string) ($config->get('numbering.separator') ?? '-'),
      '#maxlength' => 4,
    ];
    $form['numbering']['digits'] = [
      '#type' => 'number',
      '#title' => $this->t('Aantal cijfers volgnummer'),
      '#default_value' => (int) ($config->get('numbering.digits') ?? 4),
      '#min' => 1,
      '#max' => 10,
      '#required' => TRUE,
    ];
    $form['numbering']['start_number'] = [
      '#type' => 'number',
      '#title' => $this->t('Startnummer'),
      '#default_value' => (int) ($config->get('numbering.start_number') ?? 1),
      '#min' => 1,
      '#required' => TRUE,
      '#description' => $this->t('Wijzigt niet automatisch de actuele cursor van een reeds lopende reeks.'),
    ];
    $form['numbering']['reset_yearly'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Volgnummer jaarlijks opnieuw starten'),
      '#default_value' => (bool) ($config->get('numbering.reset_yearly') ?? TRUE),
    ];
    $form['numbering']['default_payment_term_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Standaard betaaltermijn in dagen'),
      '#default_value' => (int) ($config->get('numbering.default_payment_term_days') ?? 14),
      '#min' => 0,
      '#max' => 365,
      '#required' => TRUE,
      '#description' => $this->t('BREBO-standaard is 14 dagen. Klant, project, termijn of factuur kan deze waarde gecontroleerd overschrijven.'),
    ];

    $year = (int) date('Y');
    $form['example'] = [
      '#type' => 'item',
      '#title' => $this->t('Voorbeeld huidig formaat'),
      '#markup' => '<code>' . htmlspecialchars($this->numberManager->format(max(1, (int) ($config->get('numbering.start_number') ?? 1)), $year)) . '</code>',
    ];

    $form['manual_reservation'] = [
      '#type' => 'details',
      '#title' => $this->t('Factuurnummer vooruit reserveren'),
      '#open' => TRUE,
    ];
    $form['manual_reservation']['invoice_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Factuurnummer'),
      '#description' => $this->t('Reserveer bijvoorbeeld een later nummer voor een factuur met toekomstige factuurdatum. De automatische reeks blijft vanaf de bestaande cursor doorlopen en slaat dit nummer later automatisch over.'),
      '#maxlength' => 64,
    ];
    $form['manual_reservation']['reserve'] = [
      '#type' => 'submit',
      '#value' => $this->t('Nummer reserveren'),
      '#submit' => ['::reserveNumber'],
      '#limit_validation_errors' => [['manual_reservation', 'invoice_number']],
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = (array) $form_state->getValue('numbering');
    $this->configFactory->getEditable(self::CONFIG_NAME)
      ->set('numbering.prefix', trim((string) ($values['prefix'] ?? '')))
      ->set('numbering.include_year', (bool) ($values['include_year'] ?? FALSE))
      ->set('numbering.separator', (string) ($values['separator'] ?? '-'))
      ->set('numbering.digits', (int) ($values['digits'] ?? 4))
      ->set('numbering.start_number', (int) ($values['start_number'] ?? 1))
      ->set('numbering.reset_yearly', (bool) ($values['reset_yearly'] ?? FALSE))
      ->set('numbering.default_payment_term_days', (int) ($values['default_payment_term_days'] ?? 14))
      ->save();
    parent::submitForm($form, $form_state);
  }

  public function reserveNumber(array &$form, FormStateInterface $form_state): void {
    $values = (array) $form_state->getValue('manual_reservation');
    $number = trim((string) ($values['invoice_number'] ?? ''));
    if ($number === '') {
      $this->messenger()->addError($this->t('Vul een factuurnummer in om te reserveren.'));
      return;
    }
    try {
      $this->numberManager->reserveManual($number);
      $this->messenger()->addStatus($this->t('Factuurnummer @number is vooruit gereserveerd. De automatische reeks blijft ongewijzigd.', ['@number' => $number]));
    }
    catch (\Throwable $exception) {
      $this->messenger()->addError($exception->getMessage());
    }
    $form_state->setRebuild(TRUE);
  }

}
