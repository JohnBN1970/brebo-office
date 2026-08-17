<?php

declare(strict_types=1);

/** @file Post-update hooks for BREBO Calculation. */

/** Add financial exception fields to concrete subcalculation objects. */
function brebo_calculation_post_update_add_object_financial_exceptions(&$sandbox = NULL): string {
  $schema = \Drupal::database()->schema();
  $table = 'brebo_calculation_subcalculation_application_object';
  if (!$schema->tableExists($table)) { return 'BREBO Calculation object table is not installed; no financial exception fields added.'; }
  $fields = [
    'exception_labour' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
    'exception_material' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
    'exception_equipment' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
    'exception_subcontracting' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
    'exception_other' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
  ];
  $added = [];
  foreach ($fields as $fieldName => $definition) { if (!$schema->fieldExists($table, $fieldName)) { $schema->addField($table, $fieldName, $definition); $added[] = $fieldName; } }
  return $added ? 'BREBO Calculation financial exception fields added: ' . implode(', ', $added) . '.' : 'BREBO Calculation financial exception fields already exist.';
}

/** Create the auditable exception-line table for concrete object deviations. */
function brebo_calculation_post_update_create_object_exception_line_table(&$sandbox = NULL): string {
  $schema = \Drupal::database()->schema(); $table = 'brebo_calculation_subcalculation_object_exception_line';
  if ($schema->tableExists($table)) { return 'BREBO Calculation object exception-line table already exists.'; }
  $schema->createTable($table, \Drupal\brebo_calculation\Schema\SubcalculationSchema::applicationObjectExceptionLine());
  return 'BREBO Calculation object exception-line table created.';
}

/** Make the calculation-to-work-package relation optional in active config. */
function brebo_calculation_post_update_optional_work_package(&$sandbox = NULL): string {
  $field = \Drupal\field\Entity\FieldConfig::loadByName('node', 'brebo_calculation', 'field_brebo_package_ref');
  if ($field === NULL) { throw new \RuntimeException('Calculation work package field field_brebo_package_ref was not found.'); }
  $field->setRequired(FALSE)->setDescription('Optionele koppeling naar een werkpakket. Een calculatie kan zelfstandig worden gestart en later worden gekoppeld.')->save();
  return 'Werkpakket is nu een optionele relatie voor calculaties.';
}

/**
 * Install the recipe-based calculation domain additively.
 *
 * No existing calculation, row or financial table is changed. Recipe library
 * and calculation-instance snapshots are deliberately separate persistence.
 */
function brebo_calculation_post_update_create_recipe_domain(&$sandbox = NULL): string {
  $schema = \Drupal::database()->schema();
  $definitions = brebo_calculation_recipe_schema();
  $created = [];
  foreach ($definitions as $table => $definition) {
    if (!$schema->tableExists($table)) {
      $schema->createTable($table, $definition);
      $created[] = $table;
    }
  }
  return $created
    ? 'BREBO Calculation recipe domain created: ' . implode(', ', $created) . '.'
    : 'BREBO Calculation recipe domain already exists.';
}
