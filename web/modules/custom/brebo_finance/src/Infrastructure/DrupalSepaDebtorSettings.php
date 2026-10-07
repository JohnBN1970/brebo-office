<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\SepaDebtorSettingsInterface;
use Drupal\Core\Site\Settings;

/** Drupal settings adapter for SEPA debtor bank configuration. */
final class DrupalSepaDebtorSettings implements SepaDebtorSettingsInterface {

  public function debtor(): array {
    return [
      'name' => (string) Settings::get('brebo_bank_account_name', 'BREBO Bouw en Advies BV'),
      'iban' => (string) Settings::get('brebo_bank_iban', ''),
      'bic' => (string) Settings::get('brebo_bank_bic', ''),
    ];
  }

}
