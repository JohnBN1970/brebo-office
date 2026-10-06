<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\SalesTaxSettingsSourceInterface;
use InvalidArgumentException;

/** Reads and validates configurable sales VAT and G-account settings. */
final class SalesTaxSettings {

  public function __construct(private readonly SalesTaxSettingsSourceInterface $source) {}

  /** @return array<string, array{label:string, rate:string, treatment:string, active:bool}> */
  public function vatRates(): array {
    $configured = $this->source->salesSettings()['vat_rates'] ?? NULL;
    if (!is_array($configured) || $configured === []) {
      $configured = [
        'NL_21' => ['label' => '21%', 'rate' => '21.0000', 'treatment' => 'normal', 'active' => TRUE],
        'NL_9' => ['label' => '9%', 'rate' => '9.0000', 'treatment' => 'normal', 'active' => TRUE],
        'NL_REVERSE' => ['label' => 'Btw verlegd', 'rate' => '0.0000', 'treatment' => 'reverse_charge', 'active' => TRUE],
      ];
    }
    $rates = [];
    foreach ($configured as $code => $row) {
      if (!is_array($row)) continue;
      $rates[(string) $code] = [
        'label' => trim((string) ($row['label'] ?? $code)),
        'rate' => number_format((float) ($row['rate'] ?? 0), 4, '.', ''),
        'treatment' => (string) ($row['treatment'] ?? 'normal'),
        'active' => (bool) ($row['active'] ?? TRUE),
      ];
    }
    return $rates;
  }

  /** @return array<string,string> */
  public function activeVatOptions(): array {
    $options = [];
    foreach ($this->vatRates() as $code => $row) {
      if ($row['active']) $options[$code] = $row['label'];
    }
    return $options;
  }

  /** @return array{code:string,label:string,rate:string,treatment:string,active:bool} */
  public function vat(string $code): array {
    $rates = $this->vatRates();
    if (!isset($rates[$code]) || !$rates[$code]['active']) {
      throw new InvalidArgumentException('Onbekende of niet-actieve btw-code: ' . $code);
    }
    return ['code' => $code, ...$rates[$code]];
  }

  /** @return array{enabled:bool,default_percentage:string,regular_iban:string,g_iban:string} */
  public function gAccount(): array {
    $settings = $this->source->salesSettings();
    $gAccount = is_array($settings['g_account'] ?? NULL) ? $settings['g_account'] : [];
    return [
      'enabled' => (bool) ($gAccount['enabled'] ?? FALSE),
      'default_percentage' => number_format((float) ($gAccount['default_percentage'] ?? 0), 2, '.', ''),
      'regular_iban' => trim((string) ($gAccount['regular_iban'] ?? '')),
      'g_iban' => trim((string) ($gAccount['g_iban'] ?? '')),
    ];
  }

  /** @return array{regular_amount:string,g_amount:string,percentage:string} */
  public function split(float $invoiceTotal, float $percentage): array {
    if ($invoiceTotal < 0 || $percentage < 0 || $percentage > 100) {
      throw new InvalidArgumentException('Ongeldige G-rekeningverdeling.');
    }
    $g = round($invoiceTotal * ($percentage / 100), 2);
    $regular = round($invoiceTotal - $g, 2);
    return [
      'regular_amount' => number_format($regular, 2, '.', ''),
      'g_amount' => number_format($g, 2, '.', ''),
      'percentage' => number_format($percentage, 2, '.', ''),
    ];
  }
}
