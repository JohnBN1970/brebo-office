<?php

declare(strict_types=1);

/**
 * @file
 * Isolated schema installer for the BREBO Office classification master.
 *
 * This file intentionally keeps classification persistence separate from the
 * large legacy install file. It can be required by a small update hook or
 * deployment script without replacing existing BREBO Office schema code.
 */

require_once __DIR__ . '/classification.schema.inc';

/** Creates missing classification master tables idempotently. */
function brebo_office_core_install_classification_schema(): array {
  $schema = \Drupal::database()->schema();
  $created = [];

  if (!$schema->tableExists('brebo_classification')) {
    $schema->createTable('brebo_classification', brebo_office_core_classification_schema());
    $created[] = 'brebo_classification';
  }

  if (!$schema->tableExists('brebo_classification_relation')) {
    $schema->createTable('brebo_classification_relation', brebo_office_core_classification_relation_schema());
    $created[] = 'brebo_classification_relation';
  }

  return $created;
}
