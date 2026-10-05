<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Infrastructure;

use Drupal\brebo_office_core\Contract\AdministrationContextStoreInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

final class DrupalAdministrationContextStore implements AdministrationContextStoreInterface {

  private const COLLECTION = 'brebo_office_core.project_administration';

  public function __construct(private readonly KeyValueFactoryInterface $keyValueFactory) {}

  public function getProjectAdministrationCode(int $projectId): string {
    return trim((string) $this->keyValueFactory
      ->get(self::COLLECTION)
      ->get((string) $projectId, ''));
  }

  public function setProjectAdministrationCode(int $projectId, string $code): void {
    $this->keyValueFactory
      ->get(self::COLLECTION)
      ->set((string) $projectId, $code);
  }

  public function deleteProjectAdministrationCode(int $projectId): void {
    $this->keyValueFactory
      ->get(self::COLLECTION)
      ->delete((string) $projectId);
  }

}
