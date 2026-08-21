<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Schema;

/** Schema definitions for calculation-derived work budgets. */
final class WorkBudgetSchema {
  public static function budget(): array {
    return [
      'description' => 'Execution budget derived from one immutable calculation version.',
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'project_nid' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'source_calculation_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'source_calculation_version' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
        'source_content_hash' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
        'status' => ['type' => 'varchar', 'length' => 24, 'not null' => TRUE, 'default' => 'draft'],
        'created' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'created_by' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
        'approved' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
        'approved_by' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
      ],
      'primary key' => ['id'],
      'unique keys' => ['source_calculation_version' => ['source_calculation_id', 'source_calculation_version']],
      'indexes' => ['project_status' => ['project_nid', 'status']],
    ];
  }

  public static function line(): array {
    return [
      'description' => 'Cost-type work budget lines traceable to calculation rows.',
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'work_budget_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'source_calc_line_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'paragraph_key' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
        'location_ref' => ['type' => 'varchar', 'length' => 255, 'not null' => FALSE],
        'cost_type' => ['type' => 'varchar', 'length' => 24, 'not null' => TRUE],
        'description' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE],
        'unit' => ['type' => 'varchar', 'length' => 32, 'not null' => FALSE],
        'quantity' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
        'budget_unit_cost' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
        'budget_amount' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
      ],
      'primary key' => ['id'],
      'unique keys' => ['source_cost_type' => ['work_budget_id', 'source_calc_line_id', 'cost_type']],
      'indexes' => ['budget_paragraph' => ['work_budget_id', 'paragraph_key'], 'budget_cost_type' => ['work_budget_id', 'cost_type']],
    ];
  }
}
