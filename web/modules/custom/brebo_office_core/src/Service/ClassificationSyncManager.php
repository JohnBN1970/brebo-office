<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/** Synchronizes a classification source into BREBO Office local master data. */
final class ClassificationSyncManager {

  public function __construct(private readonly ClassificationSeeder $seeder) {}

  /** Imports one source adapter and returns the number of synchronized rows. */
  public function sync(ClassificationSourceAdapterInterface $adapter): int {
    return $this->seeder->import(
      $adapter->system(),
      $adapter->source(),
      $adapter->version(),
      $adapter->rows(),
    );
  }
}
