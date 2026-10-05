<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Contract;

interface AdministrationRegistrySourceInterface {

  /** @return array<string,array<string,mixed>> */
  public function administrations(): array;

  public function primaryAdministrationCode(): string;

  /** @return array<string,mixed> */
  public function legacySettings(): array;

}
