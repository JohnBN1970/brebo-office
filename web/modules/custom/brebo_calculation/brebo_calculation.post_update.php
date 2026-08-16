<?php

declare(strict_types=1);

/**
 * @file
 * Post-update hooks for BREBO Calculation.
 */

/**
 * Add financial exception fields to concrete subcalculation objects.
 */
function brebo_calculation_post_update_add_object_financial_exceptions(&$sandbox = NULL): string {
  $schema = \Drupal::database()->schema();
  $table = 'brebo_calculation_subcalculation_application_object';

  if (!$schema->tableExists($table)) {
    return 'BREBO Calculation object table is not installed; no financial exception fields added.';
  }

  $fields = [
    'exception_labour' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
    'exception_material' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
    'exception_equipment' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
    'exception_subcontracting' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
    'exception_other' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
  ];

  $added = [];
  foreach ($fields as $fieldName => $definition) {
    if (!$schema->fieldExists($table, $fieldName)) {
      $schema->addField($table, $fieldName, $definition);
      $added[] = $fieldName;
    }
  }

  if (!$added) {
    return 'BREBO Calculation financial exception fields already exist.';
  }

  return 'BREBO Calculation financial exception fields added: ' . implode(', ', $added) . '.';
}

/**
 * Create the auditable exception-line table for concrete object deviations.
 */
function brebo_calculation_post_update_create_object_exception_line_table(&$sandbox = NULL): string {
  $schema = \Drupal::database()->schema();
  $table = 'brebo_calculation_subcalculation_object_exception_line';

  if ($schema->tableExists($table)) {
    return 'BREBO Calculation object exception-line table already exists.';
  }

  $schema->createTable($table, \Drupal\brebo_calculation\Schema\SubcalculationSchema::applicationObjectExceptionLine());
  return 'BREBO Calculation object exception-line table created.';
}
