<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/** Imports controlled, versioned classification datasets into the master. */
final class ClassificationSeeder {

  public function __construct(private readonly ClassificationMaster $master) {}

  /**
   * Imports a dataset without embedding unverified classification content.
   *
   * Each row must contain code and description. Optional keys are parent_code,
   * level and sort_order. Source and version are stored on every imported row.
   */
  public function import(string $system, string $source, string $version, array $rows): int {
    if (trim($source) === '' || trim($version) === '') {
      throw new \InvalidArgumentException('Classification source and version are required.');
    }

    $count = 0;
    foreach ($rows as $row) {
      if (!is_array($row) || !isset($row['code'], $row['description'])) {
        throw new \InvalidArgumentException('Every classification row requires code and description.');
      }

      $this->master->upsert(
        $system,
        (string) $row['code'],
        (string) $row['description'],
        isset($row['parent_code']) ? (string) $row['parent_code'] : NULL,
        isset($row['level']) ? (int) $row['level'] : 1,
        $source,
        $version,
        isset($row['sort_order']) ? (int) $row['sort_order'] : $count,
      );
      $count++;
    }

    return $count;
  }
}
