<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

/** Read boundary for canonical CRM context used by mail intake. */
interface MailCrmReadRepositoryInterface {

  /** @return array{id:int,organization_id:?int}|null */
  public function contactByEmail(string $email): ?array;

  /** @return array{id:int}|null */
  public function organizationByEmail(string $email): ?array;

  /** @return array{id:int}|null */
  public function uniqueOrganizationByDomain(string $domain): ?array;

}
