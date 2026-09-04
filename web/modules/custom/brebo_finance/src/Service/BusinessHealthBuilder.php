<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/** Builds company-level financial health and steering information. */
final class BusinessHealthBuilder {

  private const CACHE_ID = 'brebo_finance:business_health:v1';

  public function __construct(
    private readonly BusinessHealthIntegrationClient $client,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly CacheBackendInterface $cache,
  ) {}

  /** @return array<string, mixed> */
  public function build(): array {
    if ($cached = $this->cache->get(self::CACHE_ID)) {
      return is_array($cached->data) ? $cached->data : [];
    }

    try {
      $source = $this->client->fetch();
      $result = $this->normalize($source);
      $this->cache->set(self::CACHE_ID, $result, time() + 300, ['brebo_finance_business_health']);
      return $result;
    }
    catch (\Throwable) {
      return [
        'status' => 'unavailable',
        'tone' => 'neutral',
        'source' => 'moneybird',
        'operator_message' => 'Bedrijfsgezondheid kon niet uit Moneybird worden geladen. De operationele Finance-data blijft beschikbaar.',
        'profit_loss' => NULL,
        'liquidity' => NULL,
        'fixed_costs' => NULL,
        'break_even' => NULL,
        'steering' => [],
      ];
    }
  }

  /** @return array<string, mixed> */
  private function normalize(array $source): array {
    $profitLoss = is_array($source['profit_loss'] ?? NULL) ? $source['profit_loss'] : [];
    $bank = is_array($source['bank'] ?? NULL) ? $source['bank'] : [];

    $revenue = $this->number($profitLoss['total_revenue'] ?? 0);
    $grossProfit = $this->number($profitLoss['gross_profit'] ?? 0);
    $operatingProfit = $this->number($profitLoss['operating_profit'] ?? 0);
    $netProfit = $this->number($profitLoss['net_profit'] ?? 0);
    $totalExpenses = $this->number($profitLoss['total_expenses'] ?? 0);
    $grossMarginPct = $revenue !== 0.0 ? ($grossProfit / $revenue) * 100 : NULL;
    $operatingMarginPct = $revenue !== 0.0 ? ($operatingProfit / $revenue) * 100 : NULL;

    $expenseRows = is_array($profitLoss['expenses_by_ledger_account'] ?? NULL)
      ? $profitLoss['expenses_by_ledger_account']
      : [];
    $fixed = $this->fixedCosts($expenseRows);

    $monthsElapsed = max(1, (int) date('n'));
    $indirectCostYtd = array_reduce($expenseRows, fn(float $carry, mixed $row): float => $carry + (is_array($row) ? $this->number($row['value'] ?? 0) : 0.0), 0.0);
    $indirectMonthlyRunRate = $indirectCostYtd / $monthsElapsed;
    $grossMarginRatio = $grossMarginPct !== NULL ? $grossMarginPct / 100 : NULL;
    $breakEvenMonthly = $grossMarginRatio !== NULL && $grossMarginRatio > 0
      ? $indirectMonthlyRunRate / $grossMarginRatio
      : NULL;

    $closingBalance = $this->number($bank['closing_balance'] ?? 0);
    $liquidityMonths = $indirectMonthlyRunRate > 0 ? $closingBalance / $indirectMonthlyRunRate : NULL;
    $liquidityTone = $this->liquidityTone($closingBalance, $liquidityMonths);

    $tone = 'green';
    if ($netProfit < 0 || $liquidityTone === 'red') {
      $tone = 'red';
    }
    elseif ($operatingProfit < 0 || $liquidityTone === 'orange' || !empty($fixed['requires_configuration'])) {
      $tone = 'orange';
    }

    $steering = [];
    if ($operatingProfit < 0) {
      $steering[] = 'De brutowinst dekt de indirecte bedrijfskosten nog niet; stuur op brutomarge, omzet en kostenbasis.';
    }
    if ($liquidityTone === 'red') {
      $steering[] = 'De actuele liquiditeitspositie vraagt directe aandacht.';
    }
    elseif ($liquidityTone === 'orange') {
      $steering[] = 'De liquiditeitsbuffer is beperkt ten opzichte van de huidige indirecte kostenrun-rate.';
    }
    if (!empty($fixed['requires_configuration'])) {
      $steering[] = 'Markeer de Moneybird-grootboekrekeningen die als vaste kosten tellen om budget versus werkelijk en het vaste-kosten-break-evenpunt te activeren.';
    }
    if ($breakEvenMonthly !== NULL && $revenue > 0) {
      $averageMonthlyRevenue = $revenue / $monthsElapsed;
      if ($averageMonthlyRevenue < $breakEvenMonthly) {
        $steering[] = 'De gemiddelde maandelijkse omzet ligt onder het berekende break-evenniveau op basis van de huidige brutomarge en indirecte kosten.';
      }
    }

    return [
      'status' => 'ok',
      'tone' => $tone,
      'source' => 'moneybird',
      'period' => (string) ($source['period'] ?? 'this_year'),
      'profit_loss' => [
        'revenue' => $this->money($revenue),
        'gross_profit' => $this->money($grossProfit),
        'gross_margin_pct' => $grossMarginPct !== NULL ? round($grossMarginPct, 2) : NULL,
        'operating_profit' => $this->money($operatingProfit),
        'operating_margin_pct' => $operatingMarginPct !== NULL ? round($operatingMarginPct, 2) : NULL,
        'net_profit' => $this->money($netProfit),
        'total_expenses' => $this->money($totalExpenses),
      ],
      'liquidity' => [
        'tone' => $liquidityTone,
        'opening_balance' => $this->money($this->number($bank['opening_balance'] ?? 0)),
        'closing_balance' => $this->money($closingBalance),
        'liquidity_months' => $liquidityMonths !== NULL ? round($liquidityMonths, 2) : NULL,
        'accounts' => is_array($bank['accounts'] ?? NULL) ? array_values($bank['accounts']) : [],
      ],
      'fixed_costs' => $fixed,
      'break_even' => [
        'basis' => 'actual_indirect_cost_run_rate',
        'indirect_cost_ytd' => $this->money($indirectCostYtd),
        'indirect_monthly_run_rate' => $this->money($indirectMonthlyRunRate),
        'monthly_revenue_required' => $breakEvenMonthly !== NULL ? $this->money($breakEvenMonthly) : NULL,
        'gross_margin_pct' => $grossMarginPct !== NULL ? round($grossMarginPct, 2) : NULL,
      ],
      'steering' => $steering,
    ];
  }

  /** @return array<string, mixed> */
  private function fixedCosts(array $expenseRows): array {
    $config = $this->configFactory->get('brebo_finance.business_health');
    $categories = $config->get('fixed_cost_categories');
    if (!is_array($categories)) {
      $categories = $this->defaultCategories();
    }

    $ledgerToCategory = [];
    $monthlyBudget = 0.0;
    $normalizedCategories = [];
    foreach ($categories as $code => $category) {
      if (!is_array($category)) continue;
      $label = trim((string) ($category['label'] ?? $code));
      $budget = max(0.0, $this->number($category['monthly_budget'] ?? 0));
      $monthlyBudget += $budget;
      $ledgerIds = array_values(array_filter(array_map('strval', is_array($category['ledger_account_ids'] ?? NULL) ? $category['ledger_account_ids'] : [])));
      foreach ($ledgerIds as $ledgerId) {
        $ledgerToCategory[$ledgerId] = (string) $code;
      }
      $normalizedCategories[(string) $code] = [
        'code' => (string) $code,
        'label' => $label,
        'monthly_budget' => $this->money($budget),
        'actual_ytd' => '0.00',
        'ledger_account_ids' => $ledgerIds,
      ];
    }

    $unclassified = [];
    $actualFixed = 0.0;
    foreach ($expenseRows as $row) {
      if (!is_array($row)) continue;
      $ledgerId = (string) ($row['ledger_account_id'] ?? '');
      $value = $this->number($row['value'] ?? 0);
      $categoryCode = $ledgerToCategory[$ledgerId] ?? NULL;
      if ($categoryCode !== NULL && isset($normalizedCategories[$categoryCode])) {
        $current = $this->number($normalizedCategories[$categoryCode]['actual_ytd']);
        $normalizedCategories[$categoryCode]['actual_ytd'] = $this->money($current + $value);
        $actualFixed += $value;
      }
      else {
        $unclassified[] = [
          'ledger_account_id' => $ledgerId,
          'ledger_account_name' => (string) ($row['ledger_account_name'] ?? 'Onbekende grootboekrekening'),
          'value' => $this->money($value),
        ];
      }
    }

    $monthsElapsed = max(1, (int) date('n'));
    $ytdBudget = $monthlyBudget * $monthsElapsed;
    return [
      'categories' => array_values($normalizedCategories),
      'monthly_budget' => $this->money($monthlyBudget),
      'budget_ytd' => $this->money($ytdBudget),
      'actual_ytd' => $this->money($actualFixed),
      'variance_ytd' => $this->money($actualFixed - $ytdBudget),
      'unclassified_expense_accounts' => $unclassified,
      'requires_configuration' => $ledgerToCategory === [] || $monthlyBudget <= 0,
    ];
  }

  private function liquidityTone(float $closingBalance, ?float $months): string {
    $config = $this->configFactory->get('brebo_finance.business_health');
    $red = max(0.0, (float) ($config->get('liquidity.red_months') ?? 1));
    $orange = max($red, (float) ($config->get('liquidity.orange_months') ?? 2));
    if ($closingBalance < 0) return 'red';
    if ($months === NULL) return 'neutral';
    if ($months < $red) return 'red';
    if ($months < $orange) return 'orange';
    return 'green';
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

  private function number(mixed $value): float {
    return is_numeric($value) ? (float) $value : 0.0;
  }

  private function money(float $value): string {
    return number_format($value, 2, '.', '');
  }

}
