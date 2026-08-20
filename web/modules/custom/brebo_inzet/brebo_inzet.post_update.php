<?php

declare(strict_types=1);

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
        'label' => 'Project',
        'type' => 'entity_reference',
        'required' => TRUE,
        'storage' => ['target_type' => 'node'],
        'field_settings' => [
          'handler' => 'default:node',
          'handler_settings' => ['target_bundles' => ['brebo_project' => 'brebo_project']],
        ],
        'description' => 'Project waarvoor deze kloklocatie geldig is.',
        'widget' => 'entity_reference_autocomplete',
        'formatter' => 'entity_reference_label',
        'weight' => 1,
      ],
      'field_brebo_zone_latitude' => [
        'label' => 'Breedtegraad',
        'type' => 'decimal',
        'required' => TRUE,
        'storage' => ['precision' => 11, 'scale' => 8],
        'description' => 'Middelpunt van de klokzone. Staat los van de officiële gebouwcoördinaten.',
        'widget' => 'number',
        'formatter' => 'number_decimal',
        'weight' => 2,
      ],
      'field_brebo_zone_longitude' => [
        'label' => 'Lengtegraad',
        'type' => 'decimal',
        'required' => TRUE,
        'storage' => ['precision' => 11, 'scale' => 8],
        'description' => 'Middelpunt van de klokzone. Staat los van de officiële gebouwcoördinaten.',
        'widget' => 'number',
        'formatter' => 'number_decimal',
        'weight' => 3,
      ],
      'field_brebo_zone_radius' => [
        'label' => 'Toegestane klokafstand',
        'type' => 'decimal',
        'required' => TRUE,
        'storage' => ['precision' => 8, 'scale' => 2],
        'description' => 'Straal van de werkzone in meters. BREBO Inzet begrenst de effectieve waarde op 10 tot 5000 meter.',
        'widget' => 'number',
        'formatter' => 'number_decimal',
        'weight' => 4,
        'default_value' => [['value' => '150.00']],
      ],
      'field_brebo_zone_active' => [
        'label' => 'Actief',
        'type' => 'boolean',
        'required' => FALSE,
        'storage' => [],
        'description' => 'Alleen actieve kloklocaties worden meegenomen bij locatieklokken.',
        'widget' => 'boolean_checkbox',
        'formatter' => 'boolean',
        'weight' => 5,
        'default_value' => [['value' => 1]],
      ],
      'field_brebo_description' => [
        'label' => 'Toelichting',
        'type' => 'text_long',
        'required' => FALSE,
        'storage' => [],
        'description' => 'Bijvoorbeeld bouwplaats, achterterrein, depot, blok B of parkeerzone.',
        'widget' => 'text_textarea',
        'formatter' => 'text_default',
        'weight' => 6,
      ],
    ],
  );

  foreach (['brebo_projectleider', 'brebo_werkvoorbereider', 'brebo_uitvoerder'] as $role_id) {
    if ($role = Role::load($role_id)) {
      foreach ([
        'create brebo_clock_zone content',
        'edit own brebo_clock_zone content',
        'edit any brebo_clock_zone content',
        'delete own brebo_clock_zone content',
        'delete any brebo_clock_zone content',
        'view brebo_clock_zone revisions',
      ] as $permission) {
        $role->grantPermission($permission);
      }
      $role->save();
    }
  }

  return 'Projectgebonden kloklocaties met instelbaar middelpunt, radius en status aangemaakt.';
}
