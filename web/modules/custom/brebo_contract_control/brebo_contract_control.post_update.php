<?php

declare(strict_types=1);

/**
 * Precreate management trend snapshot storage on existing installations.
 */
function brebo_contract_control_post_update_management_snapshot_storage(array &$sandbox = NULL): void {
  /** @var \Drupal\brebo_contract_control\Service\ManagementTrendIntelligenceService $service */
  $service = \Drupal::service('brebo_contract_control.management_trends');
  $service->ensureStorage();
}
