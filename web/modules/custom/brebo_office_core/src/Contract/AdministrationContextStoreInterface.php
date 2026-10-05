<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Contract;

interface AdministrationContextStoreInterface {

  public function getProjectAdministrationCode(int $projectId): string;

  public function setProjectAdministrationCode(int $projectId, string $code): void;

  public function deleteProjectAdministrationCode(int $projectId): void;

}
