<?php

declare(strict_types=1);

use Drupal\user\Entity\Role;

/**
 * Creates the canonical action, signal and risk layer.
 */
function brebo_office_core_post_update_canonical_action_signal_risk_layer(array &$sandbox = NULL): string {
  \Drupal::moduleHandler()->loadInclude('brebo_office_core', 'install');
  if (!function_exists('_brebo_office_core_create_node_bundle')) {
    throw new \RuntimeException('BREBO Office install helper is unavailable.');
  }

  $project_ref = [
    'label' => 'Project', 'type' => 'entity_reference', 'required' => FALSE,
    'storage' => ['target_type' => 'node'],
    'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_project' => 'brebo_project']]],
    'description' => 'Optioneel tijdelijk project waarop dit dossierobject betrekking heeft.',
    'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 2,
  ];
  $building_ref = [
    'label' => 'Gebouw', 'type' => 'entity_reference', 'required' => FALSE,
    'storage' => ['target_type' => 'node'],
    'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_building' => 'brebo_building']]],
    'description' => 'Permanent gebouw waaraan dit dossierobject duurzaam gekoppeld blijft.',
    'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 1,
  ];
  $context_ref = [
    'label' => 'Exact contextobject', 'type' => 'entity_reference', 'required' => FALSE,
    'storage' => ['target_type' => 'node'],
    'field_settings' => ['handler' => 'default:node'],
    'description' => 'Optioneel exact scope-, gebouw-, woning-, product-, communicatie- of ander dossierobject waarop dit item betrekking heeft.',
    'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 3,
  ];
  $source_comm_ref = [
    'label' => 'Broncommunicatie', 'type' => 'entity_reference', 'required' => FALSE,
    'storage' => ['target_type' => 'node'],
    'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_communication' => 'brebo_communication']]],
    'description' => 'Optioneel communicatie-item waaruit dit item aantoonbaar is ontstaan.',
    'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 4,
  ];
  $owner = [
    'label' => 'Verantwoordelijke', 'type' => 'entity_reference', 'required' => TRUE,
    'storage' => ['target_type' => 'user'], 'field_settings' => ['handler' => 'default:user'],
    'description' => 'Persoon met formele verantwoordelijkheid voor opvolging.',
    'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 8,
  ];

  _brebo_office_core_create_node_bundle('brebo_action', 'Actie',
    'Centrale auditeerbare actie met eigenaar, termijn, status, bron en resultaat.', [
      'field_brebo_building_ref' => $building_ref,
      'field_brebo_project_ref' => $project_ref,
      'field_brebo_context_ref' => $context_ref,
      'field_brebo_source_comm_ref' => $source_comm_ref,
      'field_brebo_action_description' => [
        'label' => 'Actieomschrijving', 'type' => 'text_long', 'required' => TRUE, 'storage' => [],
        'description' => 'Concrete uit te voeren actie en beoogd resultaat.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 5,
      ],
      'field_brebo_priority' => [
        'label' => 'Prioriteit', 'type' => 'string', 'required' => TRUE, 'storage' => ['max_length' => 32],
        'description' => 'Laag, normaal, hoog of kritisch.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 6,
        'default_value' => [['value' => 'Normaal']],
      ],
      'field_brebo_due_date' => [
        'label' => 'Deadline', 'type' => 'string', 'required' => FALSE, 'storage' => ['max_length' => 10],
        'description' => 'Uiterste datum in de vorm JJJJ-MM-DD.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 7,
      ],
      'field_brebo_responsible_user' => $owner,
      'field_brebo_action_status' => [
        'label' => 'Actiestatus', 'type' => 'string', 'required' => TRUE, 'storage' => ['max_length' => 32],
        'description' => 'Open, in uitvoering, geblokkeerd, gereed of vervallen.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 9,
        'default_value' => [['value' => 'Open']],
      ],
      'field_brebo_action_result' => [
        'label' => 'Resultaat en bewijs', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Afhandeling, resultaat en verwijzing naar bewijs of vervolgobject.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 10,
      ],
    ]);

  _brebo_office_core_create_node_bundle('brebo_signal', 'Signaal',
    'Centrale waarneming of waarschuwing die beoordeeld en zo nodig omgezet wordt in actie, risico of controle.', [
      'field_brebo_building_ref' => $building_ref,
      'field_brebo_project_ref' => $project_ref,
      'field_brebo_context_ref' => $context_ref,
      'field_brebo_source_comm_ref' => $source_comm_ref,
      'field_brebo_signal_description' => [
        'label' => 'Signaal', 'type' => 'text_long', 'required' => TRUE, 'storage' => [],
        'description' => 'Feitelijke waarneming, afwijking, onzekerheid of waarschuwing zonder vooraf ingevulde conclusie.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 5,
      ],
      'field_brebo_signal_severity' => [
        'label' => 'Ernst', 'type' => 'string', 'required' => TRUE, 'storage' => ['max_length' => 32],
        'description' => 'Informatie, aandacht, hoog of kritiek.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 6,
        'default_value' => [['value' => 'Aandacht']],
      ],
      'field_brebo_signal_status' => [
        'label' => 'Signaalstatus', 'type' => 'string', 'required' => TRUE, 'storage' => ['max_length' => 32],
        'description' => 'Nieuw, beoordeeld, opvolging vereist, afgehandeld of vervallen.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 7,
        'default_value' => [['value' => 'Nieuw']],
      ],
      'field_brebo_responsible_user' => $owner,
      'field_brebo_assessment' => [
        'label' => 'Beoordeling en vervolg', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Beoordeling, besluit over vervolg en verwijzing naar actie, risico, controle of afwijking.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 9,
      ],
    ]);

  _brebo_office_core_create_node_bundle('brebo_risk', 'Risico',
    'Centrale risicoregistratie met oorzaak, gebeurtenis, gevolg, kans, impact, maatregel, eigenaar en restrisico.', [
      'field_brebo_building_ref' => $building_ref,
      'field_brebo_project_ref' => $project_ref,
      'field_brebo_context_ref' => $context_ref,
      'field_brebo_source_comm_ref' => $source_comm_ref,
      'field_brebo_risk_cause' => [
        'label' => 'Oorzaak', 'type' => 'text_long', 'required' => TRUE, 'storage' => [],
        'description' => 'Bron of omstandigheid waardoor het risico kan ontstaan.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 5,
      ],
      'field_brebo_risk_event' => [
        'label' => 'Risicogebeurtenis', 'type' => 'text_long', 'required' => TRUE, 'storage' => [],
        'description' => 'Onzekere gebeurtenis die kan optreden.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 6,
      ],
      'field_brebo_risk_consequence' => [
        'label' => 'Gevolg', 'type' => 'text_long', 'required' => TRUE, 'storage' => [],
        'description' => 'Mogelijk gevolg voor kwaliteit, veiligheid, tijd, geld, contract of omgeving.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 7,
      ],
      'field_brebo_responsible_user' => $owner,
      'field_brebo_risk_probability' => [
        'label' => 'Kans', 'type' => 'string', 'required' => TRUE, 'storage' => ['max_length' => 32],
        'description' => 'Vastgelegde kansklasse volgens de toepasselijke BREBO-risicomethode.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 9,
      ],
      'field_brebo_risk_impact' => [
        'label' => 'Impact', 'type' => 'string', 'required' => TRUE, 'storage' => ['max_length' => 32],
        'description' => 'Vastgelegde impactklasse volgens de toepasselijke BREBO-risicomethode.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 10,
      ],
      'field_brebo_risk_measure' => [
        'label' => 'Beheersmaatregel', 'type' => 'text_long', 'required' => TRUE, 'storage' => [],
        'description' => 'Maatregel om kans en/of impact aantoonbaar te reduceren.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 11,
      ],
      'field_brebo_due_date' => [
        'label' => 'Termijn maatregel', 'type' => 'string', 'required' => FALSE, 'storage' => ['max_length' => 10],
        'description' => 'Uiterste datum voor de beheersmaatregel in de vorm JJJJ-MM-DD.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 12,
      ],
      'field_brebo_residual_risk' => [
        'label' => 'Restrisico', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Resterend risico na uitvoering van de beheersmaatregel.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 13,
      ],
      'field_brebo_risk_status' => [
        'label' => 'Risicostatus', 'type' => 'string', 'required' => TRUE, 'storage' => ['max_length' => 32],
        'description' => 'Open, beheerst, geaccepteerd, geëscaleerd of gesloten.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 14,
        'default_value' => [['value' => 'Open']],
      ],
    ]);

  foreach (['brebo_projectleider', 'brebo_werkvoorbereider', 'brebo_kwaliteitsmanager'] as $role_id) {
    if ($role = Role::load($role_id)) {
      foreach (['brebo_action', 'brebo_signal', 'brebo_risk'] as $bundle) {
        foreach (["create $bundle content", "edit own $bundle content", "edit any $bundle content", "view $bundle revisions"] as $permission) {
          $role->grantPermission($permission);
        }
      }
      $role->save();
    }
  }

  return 'Canonieke centrale actie-, signaal- en risicolaag ingericht zonder bestaande domeinopvolging te dupliceren.';
}
