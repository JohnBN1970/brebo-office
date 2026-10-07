<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Contract;

interface OnSiteOtpRateLimiterInterface {
  public function consume(string $identifier, int $limit, int $window): bool;
}
