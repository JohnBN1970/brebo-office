<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Form;

use Drupal\brebo_finance\Service\BusinessHealthIntegrationClient;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Classifies Moneybird bank accounts for controlled liquidity forecasting. */
final class BankAccountRolesForm extends ConfigFormBase {

  private const CONFIG_NAME = 'brebo_finance.business_health';
  private const ROLES = ['regular', 'g_account', 'excluded'];

  public function __construct(
    ConfigFactoryInterface $configFactory,
    private readonly BusinessHealthIntegrationClient $integrationClient,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {
    parent::__construct($configFactory);
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      new BusinessHealthIntegrationClient($container->get('http_client')),
      $container->get('cache_tags.invalidator'),
    );
  }

  public function getFormId(): string {
    return 'brebo_finance_bank_account_roles';
  }

  protected function getEditableConfigNames(): array {
    return [self::CONFIG_NAME];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $configured = $this->config(self::CONFIG_NAME)->get('bank_account_roles');
    $configured = is_array($configured) ? $configured : [];

    try {
      $source = $this->integrationClient->fetch();
      $accounts = is_array($source['bank']['accounts'] ?? NULL) ? $source['bank']['accounts'] : [];
    }
    catch (\Throwable) {
      $accounts = [];
    }

    $form['intro'] = [
      '#markup' => '<p>Ken iedere Moneybird-bankrekening expliciet een rol toe. Alleen rekeningen met rol <strong>Regulier</strong> of <strong>G-rekening</strong> tellen mee in het 30/60/90-liquiditeitsbeeld. Er wordt nooit op rekeningnaam of IBAN gegokt.</p>',
    ];

    if ($accounts === []) {
      $form['unavailable'] = [
        '#type' => 'item',
        '#title' => $this->t('Moneybird bankrekeningen'),
        '#markup' => $this->t('Bankrekeningen konden nu niet uit Moneybird worden gelezen. Bestaande rolconfiguratie is niet gewijzigd.'),
      ];
      return $form;
    }

    $form['accounts'] = ['#type' => 'container', '#tree' => TRUE];
    $seen = [];
    foreach ($accounts as $account) {
      if (!is_array($account)) {
        continue;
      }
      $id = trim((string) ($account['id'] ?? ''));
      if ($id === '' || isset($seen[$id])) {
        continue;
      }
      $seen[$id] = TRUE;
      $name = trim((string) ($account['name'] ?? 'Bankrekening'));
      $identifier = trim((string) ($account['identifier'] ?? ''));
      $currency = trim((string) ($account['currency'] ?? 'EUR'));
      $balance = is_numeric($account['closing_balance'] ?? NULL)
        ? number_format((float) $account['closing_balance'], 2, ',', '.')
        : 'onbekend';
      $label = $name . ($identifier !== '' ? ' · ' . $identifier : '') . ' · ' . $currency . ' · € ' . $balance;

      $form['accounts'][$id] = [
        '#type' => 'radios',
        '#title' => $label,
        '#options' => [
          'regular' => $this->t('Reguliere liquiditeit'),
          'g_account' => $this->t('G-rekening'),
          'excluded' => $this->t('Niet meenemen in liquiditeitsprognose'),
        ],
        '#default_value' => in_array((string) ($configured[$id] ?? ''), self::ROLES, TRUE) ? (string) $configured[$id] : NULL,
        '#required' => TRUE,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $accounts = (array) $form_state->getValue('accounts');
    $roles = [];
    foreach ($accounts as $accountId => $role) {
      $id = trim((string) $accountId);
      $value = trim((string) $role);
      if ($id !== '' && in_array($value, self::ROLES, TRUE)) {
        $roles[$id] = $value;
      }
    }
    ksort($roles, SORT_NATURAL);

    $this->configFactory->getEditable(self::CONFIG_NAME)
      ->set('bank_account_roles', $roles)
      ->save();
    $this->cacheTagsInvalidator->invalidateTags(['brebo_finance_business_health']);
    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('Bankrekeningrollen zijn opgeslagen. Het liquiditeitsbeeld gebruikt deze rollen direct.'));
  }

}
