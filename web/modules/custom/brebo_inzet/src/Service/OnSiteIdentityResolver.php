<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\brebo_inzet\Contract\OnSiteIdentityRepositoryInterface;

/** Resolves an active Office identity from the mobile number used by OnSite. */
final class OnSiteIdentityResolver {

  public function __construct(
    private readonly OnSiteIdentityRepositoryInterface $identityRepository,
  ) {}

  public function normalizeMobile(string $mobile): string {
    $mobile = trim($mobile);
    if ($mobile === '') {
      return '';
    }

    $prefix = str_starts_with($mobile, '+') ? '+' : '';
    $digits = preg_replace('/\D+/', '', $mobile) ?? '';
    return $digits === '' ? '' : $prefix . $digits;
  }

  /** @return array{uid:int,mobile:string,language:string}|null */
  public function resolveByMobile(string $mobile): ?array {
    $normalized = $this->normalizeMobile($mobile);
    if ($normalized === '') {
      return NULL;
    }

    $identities = $this->identityRepository->activeByMobile($normalized);
    if (count($identities) > 1) {
      throw new \RuntimeException('OnSite mobiel nummer is aan meerdere actieve gebruikers gekoppeld.');
    }

    return $identities[0] ?? NULL;
  }

}
