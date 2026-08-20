<?php

declare(strict_types=1);

/**
 * @file
 * Classification-specific BREBO Office post-update functions.
 */

/**
 * Installs the central BREBO Office classification tables.
 */
function brebo_office_core_post_update_classification_master(&$sandbox = NULL): string {
  require_once __DIR__ . '/brebo_office_core.classification_update.php';
  $created = brebo_office_core_install_classification_schema();

  if ($created === []) {
    return 'BREBO Office classification tables already exist.';
  }

  return 'Created BREBO Office classification tables: ' . implode(', ', $created) . '.';
}
