<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Infrastructure;

use Drupal\brebo_inzet\Contract\OnSiteOtpSecretProviderInterface;
use Drupal\Core\PrivateKey;

final class DrupalOnSiteOtpSecretProvider implements OnSiteOtpSecretProviderInterface {
  public function __construct(private readonly PrivateKey $privateKey) {}
  public function secret(): string {
    return $this->privateKey->get();
  }
}
