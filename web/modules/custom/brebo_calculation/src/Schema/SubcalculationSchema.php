<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Schema;

/** Schema definitions for reusable subcalculations and applications. */
final class SubcalculationSchema {

  /** @return array<string,mixed> */
  public static function subcalculation(): array {
    return [
      'description' => 'Reusable scoped calculation definitions within a calculation version.',
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'calculation_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'version' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
        'code' => ['type' => 'varchar', 'length' => 64, 'not null' => FALSE],
        'label' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE],
        'subcalculation_type' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE, 'default' => 'manual'],
        'status' => ['type' => 'varchar', 'length' => 24, 'not null' => TRUE, 'default' => 'draft'],
        'unit_label' => ['type' => 'varchar', 'length' => 32, 'not null' => FALSE],
        'base_quantity' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 1],
        'context_type' => ['type' => 'varchar', 'length' => 64, 'not null' => FALSE],
        'context_ref' => ['type' => 'varchar', 'length' => 255, 'not null' => FALSE],
        'commercial_override_payload' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE],
        'revision_note' => ['type' => 'text', 'not null' => FALSE],
        'locked_at' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
        'locked_by' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
        'content_hash' => ['type' => 'varchar', 'length' => 64, 'not null' => FALSE],
        'created' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'created_by' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
        'changed' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'changed_by' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
      ],
      'primary key' => ['id'],
      'unique keys' => ['calculation_version_code' => ['calculation_id', 'version', 'code']],
      'indexes' => [
        'calculation_version' => ['calculation_id', 'version'],
        'type_status' => ['subcalculation_type', 'status'],
        'context' => ['context_type', 'context_ref'],
      ],
    ];
  }

  /** @return array<string,mixed> */
  public static function scope(): array {
    return [
      'description' => 'Selected structure nodes and calculation lines belonging to a subcalculation.',
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'subcalculation_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'scope_type' => ['type' => 'varchar', 'length' => 24, 'not null' => TRUE],
        'scope_ref' => ['type' => 'varchar', 'length' => 128, 'not null' => TRUE],
        'multiplier' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 1],
        'sort_order' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'created' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'created_by' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
      ],
      'primary key' => ['id'],
      'unique keys' => ['subcalculation_scope' => ['subcalculation_id', 'scope_type', 'scope_ref']],
      'indexes' => ['subcalculation_sort' => ['subcalculation_id', 'sort_order']],
    ];
  }

  /** @return array<string,mixed> */
  public static function application(): array {
    return [
      'description' => 'Project application of a reusable subcalculation, including quantity multiplication.',
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'subcalculation_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'application_type' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
        'application_ref' => ['type' => 'varchar', 'length' => 255, 'not null' => FALSE],
        'project_ref' => ['type' => 'varchar', 'length' => 255, 'not null' => FALSE],
        'quantity' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 1],
        'status' => ['type' => 'varchar', 'length' => 24, 'not null' => TRUE, 'default' => 'draft'],
        'override_payload' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE],
        'snapshot_hash' => ['type' => 'varchar', 'length' => 64, 'not null' => FALSE],
        'locked_at' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
        'locked_by' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
        'created' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'created_by' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
        'changed' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'changed_by' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
      ],
      'primary key' => ['id'],
      'indexes' => [
        'subcalculation' => ['subcalculation_id'],
        'application_context' => ['application_type', 'application_ref'],
        'project_ref' => ['project_ref'],
        'status' => ['status'],
      ],
    ];
  }

  /** @return array<string,mixed> */
  public static function applicationObject(): array {
    return [
      'description' => 'Concrete canonical building/project objects represented by a subcalculation application.',
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'application_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'object_type' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
        'object_ref' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE],
        'factor' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 1],
        'is_exception' => ['type' => 'int', 'size' => 'tiny', 'unsigned' => TRUE, 'not null' => TRUE, 'default' => 0],
        'exception_payload' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE],
        'exception_labour' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
        'exception_material' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
        'exception_equipment' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
        'exception_subcontracting' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
        'exception_other' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
        'created' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'created_by' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
      ],
      'primary key' => ['id'],
      'unique keys' => ['application_object' => ['application_id', 'object_type', 'object_ref']],
      'indexes' => [
        'application' => ['application_id'],
        'canonical_object' => ['object_type', 'object_ref'],
        'exceptions' => ['application_id', 'is_exception'],
      ],
    ];
  }
}
