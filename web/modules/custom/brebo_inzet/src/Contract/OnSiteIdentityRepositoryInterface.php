<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Contract;

/** Read boundary for active OnSite identities. */
interface OnSiteIdentityRepositoryInterface {

  /**
   * @return list<array{uid:int,mobile:string,language:string}>
   */
  public function activeByMobile(string $normalizedMobile): array;

  /** @return array{uid:int,mobile:string,language:string}|null */
  public function activeByUid(int $uid): ?array;

}
