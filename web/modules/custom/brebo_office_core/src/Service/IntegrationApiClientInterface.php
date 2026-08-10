<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/**
 * Read-only contract for the central BREBO Integration API status check.
 */
interface IntegrationApiClientInterface {

  /**
   * Returns a sanitized status result without sending business data.
   *
   * @return array{state: string, http_status: int|null, response_time_ms: int|null, checked_at: string}
   */
  public function status(): array;

}
