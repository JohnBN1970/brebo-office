<?php

declare(strict_types=1);

/**
 * @file
 * Reusable BREBO Office classification schema definitions.
 */

/** Returns the central classification table schema. */
function brebo_office_core_classification_schema(): array {
  return [
    'description' => 'Central BREBO Office classification master.',
    'fields' => [
      'id' => ['type' => 'serial', 'not null' => TRUE],
      'system' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
      'code' => ['type' => 'varchar', 'length' => 128, 'not null' => TRUE],
      'description' => ['type' => 'varchar', 'length' => 512, 'not null' => TRUE],
      'parent_code' => ['type' => 'varchar', 'length' => 128, 'not null' => FALSE],
      'level' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE, 'default' => 1],
      'active' => ['type' => 'int', 'size' => 'tiny', 'unsigned' => TRUE, 'not null' => TRUE, 'default' => 1],
      'source' => ['type' => 'varchar', 'length' => 255, 'not null' => FALSE],
      'source_version' => ['type' => 'varchar', 'length' => 128, 'not null' => FALSE],
      'sort_order' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
      'created' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE, 'default' => 0],
      'changed' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE, 'default' => 0],
    ],
    'primary key' => ['id'],
    'unique keys' => ['system_code' => ['system', 'code']],
    'indexes' => [
      'system_active_sort' => ['system', 'active', 'sort_order'],
      'parent' => ['system', 'parent_code'],
    ],
  ];
}

/** Returns the cross-classification relationship table schema. */
function brebo_office_core_classification_relation_schema(): array {
  return [
    'description' => 'Relations between NL/SfB, STABU and BREBO classifications.',
    'fields' => [
      'id' => ['type' => 'serial', 'not null' => TRUE],
      'source_system' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
      'source_code' => ['type' => 'varchar', 'length' => 128, 'not null' => TRUE],
      'target_system' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
      'target_code' => ['type' => 'varchar', 'length' => 128, 'not null' => TRUE],
      'relation_type' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE, 'default' => 'related'],
      'note' => ['type' => 'text', 'size' => 'normal', 'not null' => FALSE],
      'active' => ['type' => 'int', 'size' => 'tiny', 'unsigned' => TRUE, 'not null' => TRUE, 'default' => 1],
      'created' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE, 'default' => 0],
      'changed' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE, 'default' => 0],
    ],
    'primary key' => ['id'],
    'unique keys' => ['mapping' => ['source_system', 'source_code', 'target_system', 'target_code']],
    'indexes' => [
      'source' => ['source_system', 'source_code', 'active'],
      'target' => ['target_system', 'target_code', 'active'],
    ],
  ];
}
