<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Contract;

interface OnSiteOtpSecretProviderInterface {
  public function secret(): string;
}
