<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/** Contract for external or packaged classification sources. */
interface ClassificationSourceAdapterInterface {

  /** Machine name of the classification system, e.g. nlsfb or stabu. */
  public function system(): string;

  /** Human-readable source identifier. */
  public function source(): string;

  /** Source dataset/API version used for the returned rows. */
  public function version(): string;

  /**
   * Returns normalized rows with at least code and description.
   *
   * Optional row keys: parent_code, level and sort_order.
   */
  public function rows(): array;
}
