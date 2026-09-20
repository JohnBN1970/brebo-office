<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

/**
 * Fail-closed sender used until a production SMS provider is configured.
 */
final class UnavailableOnSiteSmsSender implements OnSiteSmsSenderInterface {

  public function sendVerificationCode(string $mobile, string $code, string $languageTag): void {
    throw new \RuntimeException('OnSite SMS provider is nog niet geconfigureerd.');
  }

}
