<?php

declare(strict_types=1);

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\Entity\Role;

/**
 * Creates project-bound clock zones for BREBO Inzet.
 */
function brebo_inzet_post_update_clock_zones(array &$sandbox = NULL): string {
  \Drupal::moduleHandler()->loadInclude('brebo_office_core', 'install');
  if (!function_exists('_brebo_office_core_create_node_bundle')) {
    throw new \RuntimeException('BREBO Office install helper is unavailable.');
  }

  _brebo_office_core_create_node_bundle(
    'brebo_clock_zone',
    'Kloklocatie',
    'Projectgebonden geografische werkzone waarin medewerkers mogen inklokken en uitklokken.',
    [
      'field_brebo_project_ref' => [
        'label' => 'Project', 'type' => 'entity_reference', 'required' => TRUE,
        'storage' => ['target_type' => 'node'],
        'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_project' => 'brebo_project']]],
        'description' => 'Project waarvoor deze kloklocatie geldig is.', 'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 1,
      ],
      'field_brebo_zone_latitude' => ['label' => 'Breedtegraad', 'type' => 'decimal', 'required' => TRUE, 'storage' => ['precision' => 11, 'scale' => 8], 'description' => 'Middelpunt van de klokzone. Staat los van de officiële gebouwcoördinaten.', 'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 2],
      'field_brebo_zone_longitude' => ['label' => 'Lengtegraad', 'type' => 'decimal', 'required' => TRUE, 'storage' => ['precision' => 11, 'scale' => 8], 'description' => 'Middelpunt van de klokzone. Staat los van de officiële gebouwcoördinaten.', 'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 3],
      'field_brebo_zone_radius' => ['label' => 'Toegestane klokafstand', 'type' => 'decimal', 'required' => TRUE, 'storage' => ['precision' => 8, 'scale' => 2], 'description' => 'Straal van de werkzone in meters. BREBO Inzet begrenst de effectieve waarde op 10 tot 5000 meter.', 'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 4, 'default_value' => [['value' => '150.00']]],
      'field_brebo_zone_active' => ['label' => 'Actief', 'type' => 'boolean', 'required' => FALSE, 'storage' => [], 'description' => 'Alleen actieve kloklocaties worden meegenomen bij locatieklokken.', 'widget' => 'boolean_checkbox', 'formatter' => 'boolean', 'weight' => 5, 'default_value' => [['value' => 1]]],
      'field_brebo_description' => ['label' => 'Toelichting', 'type' => 'text_long', 'required' => FALSE, 'storage' => [], 'description' => 'Bijvoorbeeld bouwplaats, achterterrein, depot, blok B of parkeerzone.', 'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 6],
    ],
  );

  foreach (['brebo_projectleider', 'brebo_werkvoorbereider', 'brebo_uitvoerder'] as $role_id) {
    if ($role = Role::load($role_id)) {
      foreach (['create brebo_clock_zone content', 'edit own brebo_clock_zone content', 'edit any brebo_clock_zone content', 'delete own brebo_clock_zone content', 'delete any brebo_clock_zone content', 'view brebo_clock_zone revisions'] as $permission) {
        $role->grantPermission($permission);
      }
      $role->save();
    }
  }

  return 'Projectgebonden kloklocaties met instelbaar middelpunt, radius en status aangemaakt.';
}

/**
 * Creates durable clock registrations for BREBO Inzet.
 */
function brebo_inzet_post_update_clock_registrations(array &$sandbox = NULL): string {
  \Drupal::moduleHandler()->loadInclude('brebo_office_core', 'install');
  if (!function_exists('_brebo_office_core_create_node_bundle')) {
    throw new \RuntimeException('BREBO Office install helper is unavailable.');
  }

  _brebo_office_core_create_node_bundle(
    'brebo_clock_registration',
    'Klokregistratie',
    'Duurzame registratie van werkelijke kloktijd, project, locatiecontrole en afwijkingsbesluit.',
    [
      'field_brebo_project_ref' => ['label' => 'Project', 'type' => 'entity_reference', 'required' => TRUE, 'storage' => ['target_type' => 'node'], 'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_project' => 'brebo_project']]], 'description' => 'Project waarop deze klokregistratie betrekking heeft.', 'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 1],
      'field_brebo_clock_user' => ['label' => 'Medewerker', 'type' => 'entity_reference', 'required' => TRUE, 'storage' => ['target_type' => 'user'], 'field_settings' => ['handler' => 'default:user'], 'description' => 'Gebruiker die de klokactie uitvoert.', 'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 2],
      'field_brebo_clock_zone_ref' => ['label' => 'Kloklocatie', 'type' => 'entity_reference', 'required' => FALSE, 'storage' => ['target_type' => 'node'], 'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_clock_zone' => 'brebo_clock_zone']]], 'description' => 'Werkzone die bij de GPS-controle is herkend.', 'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 3],
      'field_brebo_clock_in' => ['label' => 'Ingeklokt', 'type' => 'datetime', 'required' => TRUE, 'storage' => ['datetime_type' => 'datetime'], 'description' => 'Werkelijk geregistreerde inkloktijd.', 'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => 4],
      'field_brebo_clock_out' => ['label' => 'Uitgeklokt', 'type' => 'datetime', 'required' => FALSE, 'storage' => ['datetime_type' => 'datetime'], 'description' => 'Werkelijk geregistreerde uitkloktijd.', 'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => 5],
      'field_brebo_clock_latitude' => ['label' => 'GPS breedtegraad', 'type' => 'decimal', 'required' => FALSE, 'storage' => ['precision' => 11, 'scale' => 8], 'description' => 'GPS-positie bij de laatste klokactie.', 'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 6],
      'field_brebo_clock_longitude' => ['label' => 'GPS lengtegraad', 'type' => 'decimal', 'required' => FALSE, 'storage' => ['precision' => 11, 'scale' => 8], 'description' => 'GPS-positie bij de laatste klokactie.', 'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 7],
      'field_brebo_clock_accuracy' => ['label' => 'GPS nauwkeurigheid', 'type' => 'decimal', 'required' => FALSE, 'storage' => ['precision' => 8, 'scale' => 2], 'description' => 'Door het apparaat gemelde GPS-nauwkeurigheid in meters.', 'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 8],
      'field_brebo_clock_distance' => ['label' => 'Afstand tot klokzone', 'type' => 'decimal', 'required' => FALSE, 'storage' => ['precision' => 10, 'scale' => 2], 'description' => 'Berekende afstand tot het herkende middelpunt in meters.', 'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 9],
      'field_brebo_clock_status' => ['label' => 'Klokstatus', 'type' => 'string', 'required' => TRUE, 'storage' => ['max_length' => 64], 'description' => 'Samengevoegd oordeel uit tijd, locatie en eventuele projectwissel.', 'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 10, 'default_value' => [['value' => 'Open']]],
      'field_brebo_clock_severity' => ['label' => 'Ernst', 'type' => 'string', 'required' => TRUE, 'storage' => ['max_length' => 16], 'description' => 'Groen, oranje of rood.', 'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 11, 'default_value' => [['value' => 'groen']]],
      'field_brebo_clock_reason' => ['label' => 'Reden afwijking', 'type' => 'text_long', 'required' => FALSE, 'storage' => [], 'description' => 'Verplichte toelichting wanneer de beslismotor een reden vereist.', 'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 12],
      'field_brebo_next_project_ref' => ['label' => 'Vervolgproject', 'type' => 'entity_reference', 'required' => FALSE, 'storage' => ['target_type' => 'node'], 'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_project' => 'brebo_project']]], 'description' => 'Project waarop aansluitend is ingeklokt wanneer een projectwissel is herkend.', 'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 13],
      'field_brebo_clock_message' => ['label' => 'Controle-uitkomst', 'type' => 'text_long', 'required' => FALSE, 'storage' => [], 'description' => 'Menselijk leesbare toelichting van de automatische beoordeling.', 'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 14],
    ],
  );

  foreach (['brebo_projectleider', 'brebo_werkvoorbereider', 'brebo_uitvoerder'] as $role_id) {
    if ($role = Role::load($role_id)) {
      foreach (['create brebo_clock_registration content', 'edit own brebo_clock_registration content', 'edit any brebo_clock_registration content', 'view brebo_clock_registration revisions'] as $permission) {
        $role->grantPermission($permission);
      }
      $role->save();
    }
  }

  return 'Duurzame klokregistraties met tijd-, GPS-, projectwissel- en afwijkingsgegevens aangemaakt.';
}

/**
 * Adds project-level default clocking policy used when a shift has no override.
 */
function brebo_inzet_post_update_project_clock_policy(array &$sandbox = NULL): string {
  $bundle = 'brebo_project';
  $fields = [
    'field_brebo_workday_start' => [
      'label' => 'Standaard werktijd vanaf', 'type' => 'string', 'required' => FALSE,
      'storage' => ['max_length' => 5], 'description' => 'Standaard starttijd in HH:MM, bijvoorbeeld 07:00. Een dienst kan dit later overschrijven.',
      'widget' => 'string_textfield', 'weight' => 80, 'default_value' => [['value' => '07:00']],
    ],
    'field_brebo_workday_end' => [
      'label' => 'Standaard werktijd tot', 'type' => 'string', 'required' => FALSE,
      'storage' => ['max_length' => 5], 'description' => 'Standaard eindtijd in HH:MM, bijvoorbeeld 16:00. Een dienst kan dit later overschrijven.',
      'widget' => 'string_textfield', 'weight' => 81, 'default_value' => [['value' => '16:00']],
    ],
    'field_brebo_pause_mode' => [
      'label' => 'Pauzeregistratie', 'type' => 'list_string', 'required' => FALSE,
      'storage' => ['allowed_values' => ['off' => 'Uit', 'optional' => 'Optioneel', 'required' => 'Verplicht']],
      'description' => 'Bepaalt of pauzeknoppen op het mobiele klokscherm zichtbaar of verplicht zijn.',
      'widget' => 'options_select', 'weight' => 82, 'default_value' => [['value' => 'off']],
    ],
  ];

  foreach ($fields as $field_name => $definition) {
    if (!FieldStorageConfig::loadByName('node', $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => $definition['type'],
        'settings' => $definition['storage'],
        'cardinality' => 1,
      ])->save();
    }
    if (!FieldConfig::loadByName('node', $bundle, $field_name)) {
      $field = FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => $bundle,
        'label' => $definition['label'],
        'description' => $definition['description'],
        'required' => $definition['required'],
      ]);
      $field->setDefaultValue($definition['default_value']);
      $field->save();
    }
  }

  $display = EntityFormDisplay::load("node.$bundle.default") ?: EntityFormDisplay::create([
    'targetEntityType' => 'node', 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE,
  ]);
  foreach ($fields as $field_name => $definition) {
    $display->setComponent($field_name, ['type' => $definition['widget'], 'weight' => $definition['weight']]);
  }
  $display->save();

  return 'Projectinstellingen voor standaard werktijden en optionele pauzeregistratie toegevoegd.';
}

/**
 * Repairs clocking schema for installations where earlier Inzet updates were skipped.
 */
function brebo_inzet_post_update_repair_clock_runtime_schema(array &$sandbox = NULL): string {
  if (!\Drupal::moduleHandler()->moduleExists('options')) {
    \Drupal::service('module_installer')->install(['options']);
  }

  brebo_inzet_post_update_clock_zones($sandbox);
  brebo_inzet_post_update_clock_registrations($sandbox);
  brebo_inzet_post_update_project_clock_policy($sandbox);

  return 'Kloklocaties, klokregistraties en projectbeleid gecontroleerd en waar nodig hersteld.';
}
