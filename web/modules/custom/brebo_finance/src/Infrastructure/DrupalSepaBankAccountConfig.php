<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\SepaBankAccountConfigInterface;
use Drupal\Core\Site\Settings;

final class DrupalSepaBankAccountConfig implements SepaBankAccountConfigInterface {

  public function debtorAccount(): array {
    return [
      'name' => trim((string) Settings::get('brebo_bank_account_name', 'BREBO Bouw en Advies BV')),
      'iban' => (string) Settings::get('brebo_bank_iban', ''),
      'bic' => (string) Settings::get('brebo_bank_bic', ''),
    ];
  }

}
