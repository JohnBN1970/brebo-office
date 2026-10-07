<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Infrastructure;

use Drupal\brebo_inzet\Contract\OnSiteOtpRateLimiterInterface;
use Drupal\Core\Flood\FloodInterface;

final class DrupalOnSiteOtpRateLimiter implements OnSiteOtpRateLimiterInterface {
  private const EVENT = 'brebo_onsite_otp_request';
  public function __construct(private readonly FloodInterface $flood) {}
  public function consume(string $identifier, int $limit, int $window): bool {
    if (!$this->flood->isAllowed(self::EVENT, $limit, $window, $identifier)) {
      return FALSE;
    }
    $this->flood->register(self::EVENT, $window, $identifier);
    return TRUE;
  }
}
