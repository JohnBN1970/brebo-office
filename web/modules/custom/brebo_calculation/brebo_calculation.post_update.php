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

/** Install the recipe-based calculation domain additively. */
function brebo_calculation_post_update_create_recipe_domain(&$sandbox = NULL): string {
  if (!function_exists('brebo_calculation_recipe_schema')) { \Drupal::moduleHandler()->loadInclude('brebo_calculation', 'install'); }
  if (!function_exists('brebo_calculation_recipe_schema')) { throw new \RuntimeException('BREBO Calculation recipe schema definition could not be loaded.'); }
  $schema = \Drupal::database()->schema(); $definitions = brebo_calculation_recipe_schema(); $created = [];
  foreach ($definitions as $table => $definition) { if (!$schema->tableExists($table)) { $schema->createTable($table, $definition); $created[] = $table; } }
  return $created ? 'BREBO Calculation recipe domain created: ' . implode(', ', $created) . '.' : 'BREBO Calculation recipe domain already exists.';
}

/** Ensure the complete calculation domain schema exists on older installations. */
function brebo_calculation_post_update_ensure_complete_domain_schema(&$sandbox = NULL): string {
  if (!function_exists('brebo_calculation_schema')) { \Drupal::moduleHandler()->loadInclude('brebo_calculation', 'install'); }
  if (!function_exists('brebo_calculation_schema')) { throw new \RuntimeException('BREBO Calculation schema definition could not be loaded.'); }
  $schema = \Drupal::database()->schema(); $created = [];
  foreach (brebo_calculation_schema() as $table => $definition) { if ($schema->tableExists($table)) { continue; } $schema->createTable($table, $definition); $created[] = $table; }
  return $created ? 'BREBO Calculation complete domain schema repaired: ' . implode(', ', $created) . '.' : 'BREBO Calculation complete domain schema already exists.';
}

/** Create the generic BREBO calculation norm library. */
function brebo_calculation_post_update_create_norm_library(&$sandbox = NULL): string {
  $schema = \Drupal::database()->schema(); $table = 'brebo_calculation_norm';
  if ($schema->tableExists($table)) return 'BREBO Calculation norm library already exists.';
  $schema->createTable($table, [
    'description' => 'Configurable norms used by BREBO object domains and calculation recipes.',
    'fields' => [
      'id' => ['type' => 'serial', 'not null' => TRUE], 'domain' => ['type' => 'varchar_ascii', 'length' => 64, 'not null' => TRUE],
      'norm_key' => ['type' => 'varchar_ascii', 'length' => 64, 'not null' => TRUE], 'label' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE],
      'value' => ['type' => 'numeric', 'precision' => 18, 'scale' => 6, 'not null' => TRUE], 'unit' => ['type' => 'varchar', 'length' => 32, 'not null' => FALSE],
      'conditions_json' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE], 'priority' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
      'active' => ['type' => 'int', 'size' => 'tiny', 'unsigned' => TRUE, 'not null' => TRUE, 'default' => 1], 'source' => ['type' => 'varchar', 'length' => 255, 'not null' => FALSE],
      'changed' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE, 'default' => 0],
    ],
    'primary key' => ['id'], 'indexes' => ['domain_key_active' => ['domain', 'norm_key', 'active'], 'priority' => ['priority']],
  ]);
  return 'BREBO Calculation generic norm library created.';
}

/** Create actual-observation feedback storage for BREBO norms. */
function brebo_calculation_post_update_create_norm_observation_feedback(&$sandbox = NULL): string {
  $schema = \Drupal::database()->schema(); $table = 'brebo_calculation_norm_observation';
  if ($schema->tableExists($table)) return 'BREBO Calculation norm observation table already exists.';
  $schema->createTable($table, [
    'description' => 'Actual observations used to evaluate BREBO norms without duplicating source administration.',
    'fields' => [
      'id' => ['type' => 'serial', 'not null' => TRUE], 'domain' => ['type' => 'varchar_ascii', 'length' => 64, 'not null' => TRUE],
      'norm_key' => ['type' => 'varchar_ascii', 'length' => 64, 'not null' => TRUE], 'planned_value' => ['type' => 'numeric', 'precision' => 18, 'scale' => 6, 'not null' => TRUE],
      'actual_value' => ['type' => 'numeric', 'precision' => 18, 'scale' => 6, 'not null' => TRUE], 'unit' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
      'delta_value' => ['type' => 'numeric', 'precision' => 18, 'scale' => 6, 'not null' => TRUE], 'delta_pct' => ['type' => 'numeric', 'precision' => 12, 'scale' => 4, 'not null' => FALSE],
      'source_domain' => ['type' => 'varchar_ascii', 'length' => 64, 'not null' => TRUE], 'source_reference' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE],
      'project_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE], 'context_json' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE],
      'created' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
    ],
    'primary key' => ['id'], 'indexes' => ['norm_lookup' => ['domain', 'norm_key'], 'source_lookup' => ['source_domain', 'source_reference'], 'project_lookup' => ['project_id']],
  ]);
  return 'BREBO Calculation norm observation feedback table created.';
}

/** Add traceability from calculation rows back to immutable object sources. */
function brebo_calculation_post_update_add_object_source_traceability(&$sandbox = NULL): string {
  $schema = \Drupal::database()->schema(); $table = 'brebo_calculation_row_domain';
  if (!$schema->tableExists($table)) return 'BREBO Calculation row domain is not installed; no source traceability added.';
  $fields = [
    'source_domain' => ['type' => 'varchar_ascii', 'length' => 64, 'not null' => FALSE],
    'source_reference' => ['type' => 'varchar', 'length' => 255, 'not null' => FALSE],
    'source_checksum' => ['type' => 'varchar_ascii', 'length' => 64, 'not null' => FALSE],
  ];
  $added=[];foreach($fields as$name=>$definition){if(!$schema->fieldExists($table,$name)){$schema->addField($table,$name,$definition);$added[]=$name;}}
  if (!$schema->indexExists($table, 'object_source')) {
    $schema->addIndex($table, 'object_source', ['source_domain', 'source_reference'], [
      'fields' => [
        'source_domain' => $fields['source_domain'],
        'source_reference' => $fields['source_reference'],
      ],
      'indexes' => ['object_source' => ['source_domain', 'source_reference']],
    ]);
  }
  return $added ? 'BREBO Calculation object traceability added: '.implode(', ',$added).'.' : 'BREBO Calculation object traceability already exists.';
}

/** Add queryable price provenance for object-derived calculation rows. */
function brebo_calculation_post_update_add_object_price_provenance(&$sandbox = NULL): string {
  $schema = \Drupal::database()->schema(); $table = 'brebo_calculation_row_domain';
  if (!$schema->tableExists($table)) return 'BREBO Calculation row domain is not installed; no price provenance added.';
  $fields = [
    'price_source_reference' => ['type' => 'varchar', 'length' => 512, 'not null' => FALSE],
    'price_source_date' => ['type' => 'varchar', 'length' => 10, 'not null' => FALSE],
    'price_confidence' => ['type' => 'varchar_ascii', 'length' => 8, 'not null' => FALSE],
  ];
  $added=[];foreach($fields as$name=>$definition){if(!$schema->fieldExists($table,$name)){$schema->addField($table,$name,$definition);$added[]=$name;}}
  if (!$schema->indexExists($table, 'price_source_date')) {
    $schema->addIndex($table, 'price_source_date', ['price_source_date'], [
      'fields' => ['price_source_date' => $fields['price_source_date']],
      'indexes' => ['price_source_date' => ['price_source_date']],
    ]);
  }
  return $added ? 'BREBO Calculation price provenance added: '.implode(', ',$added).'.' : 'BREBO Calculation price provenance already exists.';
}


/**
 * Promote primary calculation-line values into BREBO-owned row storage.
 */
function brebo_calculation_post_update_promote_row_domain_authority(&$sandbox = NULL): string {
  $database = \Drupal::database();
  $schema = $database->schema();
  $table = 'brebo_calculation_row_domain';
  if (!$schema->tableExists($table)) {
    return 'BREBO Calculation row domain is not installed; authority promotion skipped.';
  }

  $fields = [
    'description' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE, 'default' => ''],
    'contract_quantity' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
    'actual_quantity' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => FALSE],
    'unit' => ['type' => 'varchar', 'length' => 32, 'not null' => FALSE],
    'budget_hours' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
    'labour_rate' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0],
  ];
  foreach ($fields as $name => $definition) {
    if (!$schema->fieldExists($table, $name)) {
      $schema->addField($table, $name, $definition);
    }
  }

  $ids = array_map('intval', $database->select($table, 'r')->fields('r', ['calc_line_id'])->distinct()->execute()->fetchCol());
  if ($ids === []) {
    return 'BREBO Calculation row authority fields added; no existing rows required backfill.';
  }

  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $updated = 0;
  foreach (array_chunk($ids, 100) as $chunk) {
    foreach ($storage->loadMultiple($chunk) as $line) {
      if (!$line instanceof \Drupal\node\NodeInterface || $line->bundle() !== 'brebo_calc_line') {
        continue;
      }
      $actualRaw = $line->hasField('field_brebo_actual_quantity') ? $line->get('field_brebo_actual_quantity')->value : NULL;
      $database->update($table)->fields([
        'description' => mb_substr((string) ($line->get('field_brebo_line_description')->value ?? $line->label()), 0, 255),
        'contract_quantity' => (float) ($line->get('field_brebo_contract_quantity')->value ?? 0),
        'actual_quantity' => ($actualRaw === NULL || $actualRaw === '') ? NULL : (float) $actualRaw,
        'unit' => mb_substr((string) ($line->get('field_brebo_unit')->value ?? ''), 0, 32),
        'budget_hours' => $line->hasField('field_brebo_budget_hours') ? (float) ($line->get('field_brebo_budget_hours')->value ?? 0) : 0.0,
        'labour_rate' => $line->hasField('field_brebo_labor_rate') ? (float) ($line->get('field_brebo_labor_rate')->value ?? 0) : 0.0,
      ])->condition('calc_line_id', (int) $line->id())->execute();
      $updated++;
    }
  }

  return sprintf('BREBO Calculation row authority fields ready; %d legacy calc lines backfilled.', $updated);
}


/**
 * Introduce a BREBO-owned identity for calculation rows.
 */
function brebo_calculation_post_update_add_row_identity(&$sandbox = NULL): string {
  $database = \Drupal::database();
  $schema = $database->schema();
  $table = 'brebo_calculation_row_domain';
  if (!$schema->tableExists($table)) {
    return 'BREBO Calculation row domain is not installed; row identity migration skipped.';
  }

  if (!$schema->fieldExists($table, 'row_id')) {
    $schema->addField($table, 'row_id', [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => FALSE,
    ]);
  }

  $next = (int) $database->select($table, 'r')->addExpression('MAX(row_id)', 'max_row_id')->execute()->fetchField();
  $query = $database->select($table, 'r')->fields('r', ['calc_line_id', 'version'])->isNull('row_id');
  foreach ($query->execute() as $row) {
    $next++;
    $database->update($table)
      ->fields(['row_id' => $next])
      ->condition('calc_line_id', (int) $row->calc_line_id)
      ->condition('version', (string) $row->version)
      ->execute();
  }

  if (!$schema->indexExists($table, 'row_id')) {
    $schema->addUniqueKey($table, 'row_id', ['row_id']);
  }
  if (!$schema->indexExists($table, 'legacy_line_version')) {
    $schema->addUniqueKey($table, 'legacy_line_version', ['calc_line_id', 'version']);
  }

  return 'BREBO-owned calculation row identities are backfilled and unique; calc_line_id remains the temporary legacy mapping.';
}


/**
 * Widen BREBO calculation row ids for independent random identity allocation.
 */
function brebo_calculation_post_update_widen_row_identity(&$sandbox = NULL): string {
  $database = \Drupal::database();
  $schema = $database->schema();
  $table = 'brebo_calculation_row_domain';
  if (!$schema->tableExists($table) || !$schema->fieldExists($table, 'row_id')) {
    return 'BREBO Calculation row identity is not installed; widening skipped.';
  }

  $schema->changeField($table, 'row_id', 'row_id', [
    'type' => 'int',
    'size' => 'big',
    'unsigned' => TRUE,
    'not null' => TRUE,
  ]);
  if (!$schema->indexExists($table, 'row_id')) {
    $schema->addUniqueKey($table, 'row_id', ['row_id']);
  }

  return 'BREBO Calculation row identity widened for independent allocation.';
}


/**
 * Promote calculation row ordering into BREBO-owned storage.
 */
function brebo_calculation_post_update_promote_row_order(&$sandbox = NULL): string {
  $database = \Drupal::database();
  $schema = $database->schema();
  $table = 'brebo_calculation_row_domain';
  if (!$schema->tableExists($table)) {
    return 'BREBO Calculation row domain is not installed; row ordering promotion skipped.';
  }
  if (!$schema->fieldExists($table, 'sort_order')) {
    $schema->addField($table, 'sort_order', [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
    ]);
  }

  $ids = array_map('intval', $database->select($table, 'r')->fields('r', ['calc_line_id'])->distinct()->execute()->fetchCol());
  if ($ids === []) {
    return 'BREBO Calculation row ordering field added; no legacy rows required backfill.';
  }

  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $updated = 0;
  foreach (array_chunk($ids, 100) as $chunk) {
    foreach ($storage->loadMultiple($chunk) as $line) {
      if (!$line instanceof \Drupal\node\NodeInterface || $line->bundle() !== 'brebo_calc_line') {
        continue;
      }
      $database->update($table)
        ->fields(['sort_order' => (int) ($line->get('field_brebo_line_sequence')->value ?? 0)])
        ->condition('calc_line_id', (int) $line->id())
        ->execute();
      $updated++;
    }
  }

  return sprintf('BREBO Calculation row ordering promoted for %d legacy rows.', $updated);
}
