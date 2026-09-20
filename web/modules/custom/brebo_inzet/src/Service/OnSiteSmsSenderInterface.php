<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

interface OnSiteSmsSenderInterface {

  /**
   * Sends a one-time verification code to a normalized mobile number.
   */
  public function sendVerificationCode(string $mobile, string $code, string $languageTag): void;

}
