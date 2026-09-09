<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Form;

use Drupal\brebo_finance\Service\BusinessHealthIntegrationClient;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Makes fixed-cost and liquidity policy configuration operable in Office. */
final class BusinessHealthSettingsForm extends ConfigFormBase {

  private const CONFIG_NAME = 'brebo_finance.business_health';

  public function __construct(
    ConfigFactoryInterface $configFactory,
    private readonly BusinessHealthIntegrationClient $integrationClient,
    private readonly CacheBackendInterface $cache,
  ) {
    parent::__construct($configFactory);
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      new BusinessHealthIntegrationClient($container->get('http_client')),
      $container->get('cache.default'),
    );
  }

  public function getFormId(): string {
    return 'brebo_finance_business_health_settings';
  }

  protected function getEditableConfigNames(): array {
    return [self::CONFIG_NAME];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::CONFIG_NAME);
    $categories = $config->get('fixed_cost_categories');
    if (!is_array($categories) || $categories === []) {
      $categories = $this->defaultCategories();
    }

    [$ledgerOptions, $sourceMessage] = $this->ledgerOptions($categories);

    $form['#tree'] = TRUE;
    $form['intro'] = [
      '#markup' => '<p>Beheer hier de vaste-kostenrubrieken die BREBO Office gebruikt voor budget versus werkelijk en break-even. Moneybird blijft de bron van de gerealiseerde grootboekbedragen; Office bepaalt alleen de operationele rubricering en het maandbudget.</p>',
    ];
    $form['source_status'] = [
      '#type' => 'item',
      '#title' => $this->t('Moneybird grootboekbron'),
      '#markup' => $sourceMessage,
    ];

    $form['liquidity'] = [
      '#type' => 'details',
      '#title' => $this->t('Liquiditeitswaarschuwingen'),
      '#open' => FALSE,
    ];
    $form['liquidity']['red_months'] = [
      '#type' => 'number',
      '#title' => $this->t('Rood onder aantal maanden buffer'),
      '#default_value' => (float) ($config->get('liquidity.red_months') ?? 1),
      '#min' => 0,
      '#step' => 0.1,
      '#required' => TRUE,
    ];
    $form['liquidity']['orange_months'] = [
      '#type' => 'number',
      '#title' => $this->t('Oranje onder aantal maanden buffer'),
      '#default_value' => (float) ($config->get('liquidity.orange_months') ?? 2),
      '#min' => 0,
      '#step' => 0.1,
      '#required' => TRUE,
    ];

    $form['fixed_cost_categories'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-finance-fixed-cost-settings']],
    ];

    foreach ($categories as $code => $category) {
      if (!is_array($category)) {
        continue;
      }
      $label = trim((string) ($category['label'] ?? $code));
      $configuredIds = array_values(array_filter(array_map('strval', is_array($category['ledger_account_ids'] ?? NULL) ? $category['ledger_account_ids'] : [])));

      $form['fixed_cost_categories'][(string) $code] = [
        '#type' => 'details',
        '#title' => $label,
        '#open' => FALSE,
      ];
      $form['fixed_cost_categories'][(string) $code]['monthly_budget'] = [
        '#type' => 'number',
        '#title' => $this->t('Maandbudget excl. btw'),
        '#default_value' => (float) ($category['monthly_budget'] ?? 0),
        '#min' => 0,
        '#step' => 0.01,
        '#required' => TRUE,
        '#field_prefix' => '€ ',
      ];

      if ($ledgerOptions !== []) {
        $form['fixed_cost_categories'][(string) $code]['ledger_account_ids'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Moneybird-grootboekrekeningen'),
          '#options' => $ledgerOptions,
          '#default_value' => $configuredIds,
          '#description' => $this->t('Een grootboekrekening mag aan maximaal één vaste-kostenrubriek zijn gekoppeld.'),
        ];
      }
      else {
        $form['fixed_cost_categories'][(string) $code]['ledger_account_ids_manual'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Moneybird-grootboek-ID’s'),
          '#default_value' => implode(', ', $configuredIds),
          '#description' => $this->t('Moneybird is tijdelijk niet uitleesbaar. Bestaande IDs blijven behouden; gebruik komma’s tussen meerdere IDs.'),
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $liquidity = $form_state->getValue('liquidity');
    $red = (float) ($liquidity['red_months'] ?? 0);
    $orange = (float) ($liquidity['orange_months'] ?? 0);
    if ($orange < $red) {
      $form_state->setErrorByName('liquidity][orange_months', $this->t('De oranje grens moet gelijk aan of hoger zijn dan de rode grens.'));
    }

    $seen = [];
    foreach ((array) $form_state->getValue('fixed_cost_categories') as $code => $values) {
      $budget = (float) ($values['monthly_budget'] ?? 0);
      if ($budget < 0) {
        $form_state->setErrorByName('fixed_cost_categories][' . $code . '][monthly_budget', $this->t('Een maandbudget kan niet negatief zijn.'));
      }
      foreach ($this->submittedLedgerIds((array) $values) as $ledgerId) {
        if (isset($seen[$ledgerId])) {
          $form_state->setErrorByName(
            'fixed_cost_categories][' . $code,
            $this->t('Grootboekrekening @id is al gekoppeld aan @category.', ['@id' => $ledgerId, '@category' => $seen[$ledgerId]]),
          );
        }
        $seen[$ledgerId] = (string) $code;
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $editable = $this->configFactory->getEditable(self::CONFIG_NAME);
    $existing = $editable->get('fixed_cost_categories');
    if (!is_array($existing) || $existing === []) {
      $existing = $this->defaultCategories();
    }

    $submitted = (array) $form_state->getValue('fixed_cost_categories');
    foreach ($existing as $code => $category) {
      if (!is_array($category) || !isset($submitted[$code]) || !is_array($submitted[$code])) {
        continue;
      }
      $existing[$code]['monthly_budget'] = round((float) ($submitted[$code]['monthly_budget'] ?? 0), 2);
      $ids = $this->submittedLedgerIds($submitted[$code]);
      sort($ids, SORT_NATURAL);
      $existing[$code]['ledger_account_ids'] = $ids;
    }

    $liquidity = (array) $form_state->getValue('liquidity');
    $editable
      ->set('liquidity.red_months', (float) ($liquidity['red_months'] ?? 1))
      ->set('liquidity.orange_months', (float) ($liquidity['orange_months'] ?? 2))
      ->set('fixed_cost_categories', $existing)
      ->save();

    $this->cache->invalidateTags(['brebo_finance_business_health']);
    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('Bedrijfsgezondheid en vaste-kostenrubrieken zijn bijgewerkt.'));
  }

  /** @return array{0: array<string, string>, 1: string} */
  private function ledgerOptions(array $categories): array {
    $options = [];
    try {
      $source = $this->integrationClient->fetch();
      $rows = is_array($source['profit_loss']['expenses_by_ledger_account'] ?? NULL)
        ? $source['profit_loss']['expenses_by_ledger_account']
        : [];
      foreach ($rows as $row) {
        if (!is_array($row)) {
          continue;
        }
        $id = trim((string) ($row['ledger_account_id'] ?? ''));
        if ($id === '') {
          continue;
        }
        $name = trim((string) ($row['ledger_account_name'] ?? 'Onbekende grootboekrekening'));
        $value = is_numeric($row['value'] ?? NULL) ? (float) $row['value'] : 0.0;
        $options[$id] = sprintf('%s · %s · YTD € %s', $name, $id, number_format($value, 2, ',', '.'));
      }
    }
    catch (\Throwable) {
      // Keep current configuration operable even if Moneybird is temporarily unavailable.
    }

    foreach ($categories as $category) {
      if (!is_array($category)) {
        continue;
      }
      foreach (is_array($category['ledger_account_ids'] ?? NULL) ? $category['ledger_account_ids'] : [] as $configuredId) {
        $id = trim((string) $configuredId);
        if ($id !== '' && !isset($options[$id])) {
          $options[$id] = sprintf('%s · %s', $id, (string) $this->t('momenteel niet in Moneybird YTD-rapport'));
        }
      }
    }
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);

    return $options !== []
      ? [$options, (string) $this->t('Actuele Moneybird-kostenrekeningen zijn geladen. Selecteer per rubriek welke rekeningen als vaste kosten gelden.')]
      : [[], (string) $this->t('Moneybird-kostenrekeningen konden nu niet worden geladen. Bestaande configuratie blijft behouden.')];
  }

  /** @return string[] */
  private function submittedLedgerIds(array $values): array {
    if (isset($values['ledger_account_ids']) && is_array($values['ledger_account_ids'])) {
      return array_values(array_unique(array_filter(array_map(
        static fn(mixed $value): string => trim((string) $value),
        $values['ledger_account_ids'],
      ), static fn(string $value): bool => $value !== '' && $value !== '0'))));
    }

    $manual = trim((string) ($values['ledger_account_ids_manual'] ?? ''));
    if ($manual === '') {
      return [];
    }
    return array_values(array_unique(array_filter(array_map('trim', explode(',', $manual)))));
  }

  /** @return array<string, array<string, mixed>> */
  private function defaultCategories(): array {
    return [
      'huisvesting' => ['label' => 'Huisvesting', 'monthly_budget' => 0, 'ledger_account_ids' => []],
      'voertuigen' => ['label' => 'Voertuigen', 'monthly_budget' => 0, 'ledger_account_ids' => []],
      'verzekeringen' => ['label' => 'Verzekeringen', 'monthly_budget' => 0, 'ledger_account_ids' => []],
      'ict' => ['label' => 'Software & ICT', 'monthly_budget' => 0, 'ledger_account_ids' => []],
      'advies' => ['label' => 'Accountancy & advies', 'monthly_budget' => 0, 'ledger_account_ids' => []],
      'telecom' => ['label' => 'Telefoon & communicatie', 'monthly_budget' => 0, 'ledger_account_ids' => []],
      'financiering' => ['label' => 'Financiering', 'monthly_budget' => 0, 'ledger_account_ids' => []],
      'management' => ['label' => 'Personeel & management', 'monthly_budget' => 0, 'ledger_account_ids' => []],
      'abonnementen' => ['label' => 'Abonnementen', 'monthly_budget' => 0, 'ledger_account_ids' => []],
      'overig' => ['label' => 'Overige vaste kosten', 'monthly_budget' => 0, 'ledger_account_ids' => []],
    ];
  }

}
