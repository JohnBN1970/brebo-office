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

/**
 * Creates feedback as durable learning data and closes the improvement loop.
 */
function brebo_office_core_post_update_canonical_feedback_learning_loop(array &$sandbox = NULL): string {
  \Drupal::moduleHandler()->loadInclude('brebo_office_core', 'install');
  if (!function_exists('_brebo_office_core_create_node_bundle')) {
    throw new \RuntimeException('BREBO Office install helper is unavailable.');
  }

  _brebo_office_core_create_node_bundle(
    'brebo_feedback',
    'Feedback en tevredenheid',
    'Herleidbare feedback over communicatie, uitvoering en oplevering als structurele leerdata voor gebouw en project.',
    [
      'field_brebo_building_ref' => [
        'label' => 'Gebouw', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'node'],
        'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_building' => 'brebo_building']]],
        'description' => 'Permanent gebouw waarop de feedback duurzaam betrekking heeft.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 1,
      ],
      'field_brebo_project_ref' => [
        'label' => 'Project', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'node'],
        'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_project' => 'brebo_project']]],
        'description' => 'Optioneel tijdelijk project waarin de feedback is ontstaan.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 2,
      ],
      'field_brebo_context_ref' => [
        'label' => 'Exact contextobject', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'node'],
        'field_settings' => ['handler' => 'default:node'],
        'description' => 'Exact gebouwdeel, woning, productpositie, projectscope, werkpakket, communicatie- of ander dossierobject waarop de feedback ziet.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 3,
      ],
      'field_brebo_source_comm_ref' => [
        'label' => 'Broncommunicatie', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'node'],
        'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_communication' => 'brebo_communication']]],
        'description' => 'Optioneel communicatie-item waarmee de feedback als bronbewijs herleidbaar blijft.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 4,
      ],
      'field_brebo_feedback_date' => [
        'label' => 'Feedbackdatum', 'type' => 'datetime', 'required' => TRUE,
        'storage' => ['datetime_type' => 'date'],
        'description' => 'Datum waarop de feedback is ontvangen of geregistreerd.',
        'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => 5,
      ],
      'field_brebo_feedback_source' => [
        'label' => 'Feedbackbron', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 64],
        'description' => 'Bijvoorbeeld bewoner, opdrachtgever, ketenpartner, leverancier of intern.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 6,
      ],
      'field_brebo_feedback_type' => [
        'label' => 'Feedbacksoort', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 64],
        'description' => 'Bijvoorbeeld tevredenheidsmeting, compliment, klacht, evaluatie, oplevering of service/garantie.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 7,
      ],
      'field_brebo_score_clarity' => [
        'label' => 'Score communicatie en duidelijkheid', 'type' => 'integer', 'required' => FALSE, 'storage' => [],
        'description' => 'Ervaringsscore voor communicatie en duidelijkheid; schaal wordt in de registratie expliciet vermeld.',
        'widget' => 'number', 'formatter' => 'number_integer', 'weight' => 8,
      ],
      'field_brebo_score_reachability' => [
        'label' => 'Score bereikbaarheid', 'type' => 'integer', 'required' => FALSE, 'storage' => [],
        'description' => 'Ervaringsscore voor bereikbaarheid.',
        'widget' => 'number', 'formatter' => 'number_integer', 'weight' => 9,
      ],
      'field_brebo_score_response' => [
        'label' => 'Score reactiesnelheid', 'type' => 'integer', 'required' => FALSE, 'storage' => [],
        'description' => 'Ervaringsscore voor reactiesnelheid.',
        'widget' => 'number', 'formatter' => 'number_integer', 'weight' => 10,
      ],
      'field_brebo_score_agreements' => [
        'label' => 'Score nakomen afspraken', 'type' => 'integer', 'required' => FALSE, 'storage' => [],
        'description' => 'Ervaringsscore voor het nakomen van afspraken.',
        'widget' => 'number', 'formatter' => 'number_integer', 'weight' => 11,
      ],
      'field_brebo_score_execution' => [
        'label' => 'Score kwaliteit uitvoering', 'type' => 'integer', 'required' => FALSE, 'storage' => [],
        'description' => 'Ervaringsscore voor de kwaliteit van de uitvoering.',
        'widget' => 'number', 'formatter' => 'number_integer', 'weight' => 12,
      ],
      'field_brebo_score_nuisance' => [
        'label' => 'Score ervaren overlast', 'type' => 'integer', 'required' => FALSE, 'storage' => [],
        'description' => 'Ervaringsscore voor de omgang met en beperking van overlast.',
        'widget' => 'number', 'formatter' => 'number_integer', 'weight' => 13,
      ],
      'field_brebo_score_complaints' => [
        'label' => 'Score klachtbehandeling', 'type' => 'integer', 'required' => FALSE, 'storage' => [],
        'description' => 'Ervaringsscore voor klachtbehandeling, indien van toepassing.',
        'widget' => 'number', 'formatter' => 'number_integer', 'weight' => 14,
      ],
      'field_brebo_score_handover' => [
        'label' => 'Score oplevering', 'type' => 'integer', 'required' => FALSE, 'storage' => [],
        'description' => 'Ervaringsscore voor oplevering en afronding.',
        'widget' => 'number', 'formatter' => 'number_integer', 'weight' => 15,
      ],
      'field_brebo_score_overall' => [
        'label' => 'Algemeen oordeel', 'type' => 'integer', 'required' => FALSE, 'storage' => [],
        'description' => 'Algemene ervaringsscore.',
        'widget' => 'number', 'formatter' => 'number_integer', 'weight' => 16,
      ],
      'field_brebo_score_scale' => [
        'label' => 'Scoreschaal', 'type' => 'string', 'required' => FALSE,
        'storage' => ['max_length' => 32],
        'description' => 'Expliciete schaal voor de ingevulde scores, bijvoorbeeld 1-5 of 1-10.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 17,
      ],
      'field_brebo_feedback_reason' => [
        'label' => 'Toelichting en reden', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Kwalitatieve reden achter de gegeven score of reactie.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 18,
      ],
      'field_brebo_positive_point' => [
        'label' => 'Positief punt', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Wat aantoonbaar goed is ervaren en behouden moet blijven.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 19,
      ],
      'field_brebo_negative_point' => [
        'label' => 'Negatief punt', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Wat aantoonbaar negatief is ervaren.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 20,
      ],
      'field_brebo_improvement_point' => [
        'label' => 'Verbeterpunt', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Concreet verbeterpunt dat uit de feedback volgt.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 21,
      ],
      'field_brebo_feedback_cause' => [
        'label' => 'Beoordeelde oorzaak', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Gevalideerde oorzaak of patroon achter de feedback; geen onbewezen aanname als feit registreren.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 22,
      ],
      'field_brebo_action_ref' => [
        'label' => 'Verbeteractie', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'node'],
        'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_action' => 'brebo_action']]],
        'description' => 'Herleidbare verbeteractie die uit deze feedback is vastgesteld.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 23,
      ],
      'field_brebo_changed_practice' => [
        'label' => 'Gewijzigde werkwijze', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Vastgelegde wijziging van werkwijze, instructie of proces na uitvoering en beoordeling van de verbeteractie.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 24,
      ],
      'field_brebo_remeasure_date' => [
        'label' => 'Opnieuw meten op', 'type' => 'datetime', 'required' => FALSE,
        'storage' => ['datetime_type' => 'date'],
        'description' => 'Datum waarop de werking van de verbetering opnieuw wordt beoordeeld of gemeten.',
        'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => 25,
      ],
      'field_brebo_feedback_status' => [
        'label' => 'Feedbackstatus', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 32],
        'description' => 'Nieuw, beoordeeld, actie vereist, verbetering uitgevoerd, opnieuw gemeten of gesloten.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 26,
        'default_value' => [['value' => 'Nieuw']],
      ],
    ],
  );

  foreach (['brebo_projectleider', 'brebo_werkvoorbereider', 'brebo_kwaliteitsmanager'] as $role_id) {
    if ($role = Role::load($role_id)) {
      foreach ([
        'create brebo_feedback content',
        'edit own brebo_feedback content',
        'edit any brebo_feedback content',
        'view brebo_feedback revisions',
      ] as $permission) {
        $role->grantPermission($permission);
      }
      $role->save();
    }
  }

  return 'Feedback- en tevredenheidsobject met herleidbare leerkring ingericht: ervaring naar oorzaak, verbeteractie, gewijzigde werkwijze en hermeting.';
}


/**
 * Adds only the approved links from funnel opportunities to delivery records.
 */
function brebo_office_core_post_update_funnel_delivery_links(array &$sandbox = NULL): string {
  \Drupal::moduleHandler()->loadInclude('brebo_office_core', 'install');
  if (!function_exists('_brebo_office_core_create_node_bundle')) {
    throw new \RuntimeException('BREBO Office install helper is unavailable.');
  }

  _brebo_office_core_create_node_bundle('brebo_opportunity', 'Commerciële kans',
    'Zelfstandige commerciële kans binnen de begeleide funnel.', [
      'field_brebo_opp_calc_ref' => [
        'label' => 'Calculatie', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'node'],
        'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_calculation' => 'brebo_calculation']]],
        'description' => 'De calculatie die bij deze commerciële kans hoort.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 7,
      ],
      'field_brebo_opp_offer_ref' => [
        'label' => 'Offerteversie', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'node'],
        'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_offer_version' => 'brebo_offer_version']]],
        'description' => 'De actuele formele offerteversie voor deze kans.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 8,
      ],
      'field_brebo_opp_project_ref' => [
        'label' => 'Project', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'node'],
        'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_project' => 'brebo_project']]],
        'description' => 'Het project dat gecontroleerd uit de gewonnen kans is ontstaan.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 9,
      ],
    ]);

  _brebo_office_core_create_node_bundle('brebo_project', 'Project',
    'Centraal projectobject binnen BREBO Office.', [
      'field_brebo_project_opp_ref' => [
        'label' => 'Commerciële herkomst', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'node'],
        'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_opportunity' => 'brebo_opportunity']]],
        'description' => 'De gewonnen commerciële kans waaruit dit project is ontstaan.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 5,
      ],
    ]);

  return 'Vier goedgekeurde funnelkoppelingen naar calculatie, offerte en project ingericht.';
}

/**
 * Adds commercial lead attribution to funnel opportunities.
 */
function brebo_office_core_post_update_funnel_lead_attribution(array &$sandbox = NULL): string {
  \Drupal::moduleHandler()->loadInclude('brebo_office_core', 'install');
  if (!function_exists('_brebo_office_core_create_node_bundle')) {
    throw new \RuntimeException('BREBO Office install helper is unavailable.');
  }

  _brebo_office_core_create_node_bundle('brebo_opportunity', 'Commerciële kans',
    'Zelfstandige commerciële kans binnen de begeleide funnel.', [
      'field_brebo_opp_source' => [
        'label' => 'Leadbron', 'type' => 'string', 'required' => FALSE,
        'storage' => ['max_length' => 128],
        'description' => 'Herkomst van de lead, bijvoorbeeld bestaande klant, aanbeveling, website, aanbesteding of acquisitie.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 14,
      ],
      'field_brebo_opp_channel' => [
        'label' => 'Acquisitiekanaal', 'type' => 'string', 'required' => FALSE,
        'storage' => ['max_length' => 128],
        'description' => 'Kanaal waarmee het eerste commerciële contact tot stand kwam.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 15,
      ],
      'field_brebo_opp_campaign' => [
        'label' => 'Campagne of actie', 'type' => 'string', 'required' => FALSE,
        'storage' => ['max_length' => 255],
        'description' => 'Campagne, netwerkactie, aanbesteding of andere commerciële actie waaraan de lead wordt toegeschreven.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 16,
      ],
    ]);

  return 'Leadbron, acquisitiekanaal en campagne aan commerciële kansen toegevoegd.';
}

/**
 * Adds structured commercial qualification to opportunities.
 */
function brebo_office_core_post_update_funnel_qualification(array &$sandbox = NULL): string {
  \Drupal::moduleHandler()->loadInclude('brebo_office_core', 'install');
  if (!function_exists('_brebo_office_core_create_node_bundle')) {
    throw new \RuntimeException('BREBO Office install helper is unavailable.');
  }

  _brebo_office_core_create_node_bundle('brebo_opportunity', 'Commerciële kans',
    'Zelfstandige commerciële kans binnen de begeleide funnel.', [
      'field_brebo_opp_requirement' => [
        'label' => 'Klantbehoefte en scope', 'type' => 'text_long', 'required' => FALSE,
        'storage' => [],
        'description' => 'Concrete klantbehoefte, gewenste uitkomst en afbakening van de commerciële vraag.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 17,
      ],
      'field_brebo_opp_decision_maker' => [
        'label' => 'Beslisser', 'type' => 'string', 'required' => FALSE,
        'storage' => ['max_length' => 255],
        'description' => 'Naam of rol van degene die formeel over de opdracht beslist.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 18,
      ],
      'field_brebo_opp_budget_confirmed' => [
        'label' => 'Budget bevestigd', 'type' => 'boolean', 'required' => FALSE,
        'storage' => [],
        'description' => 'Geeft aan dat budget of financiële ruimte met de klant is bevestigd.',
        'widget' => 'boolean_checkbox', 'formatter' => 'boolean', 'weight' => 19,
        'default_value' => [['value' => 0]],
      ],
      'field_brebo_opp_decision_date' => [
        'label' => 'Beslis- of aanbestedingsdatum', 'type' => 'datetime', 'required' => FALSE,
        'storage' => ['datetime_type' => 'date'],
        'description' => 'Datum van het verwachte klantbesluit of de aanbestedingsdeadline.',
        'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => 20,
      ],
    ]);

  return 'Klantbehoefte, beslisser, budgetbevestiging en beslisdatum aan commerciële kansen toegevoegd.';
}

/**
 * Adds object-linked execution activities without replacing project route gates.
 */
function brebo_office_core_post_update_project_execution_planning(array &$sandbox = NULL): string {
  \Drupal::moduleHandler()->loadInclude('brebo_office_core', 'install');
  if (!function_exists('_brebo_office_core_create_node_bundle')) {
    throw new \RuntimeException('BREBO Office install helper is unavailable.');
  }

  _brebo_office_core_create_node_bundle('brebo_plan_activity', 'Planningsactiviteit',
    'Uitvoeringsactiviteit onder de bestuurlijke projectroute, gekoppeld aan permanente gebouwobjecten.', [
      'field_brebo_project_ref' => [
        'label' => 'Project', 'type' => 'entity_reference', 'required' => TRUE,
        'storage' => ['target_type' => 'node'],
        'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_project' => 'brebo_project']]],
        'description' => 'Tijdelijke projectcontext van deze activiteit.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 1,
      ],
      'field_brebo_plan_code' => [
        'label' => 'Activiteitcode', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 64], 'description' => 'Unieke WBS- of activiteitcode binnen het project.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 2,
      ],
      'field_brebo_plan_phase' => [
        'label' => 'Projectfase', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 64], 'description' => 'Initiatie, opname, werkvoorbereiding, inkoop, uitvoering, oplevering of nazorg.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 3,
      ],
      'field_brebo_route_item_ref' => [
        'label' => 'Route- of vrijgavestap', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'node'],
        'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_route_item' => 'brebo_route_item']]],
        'description' => 'Bestuurlijke stap, mijlpaal of vrijgave waarvan deze activiteit afhankelijk is.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 4,
      ],
      'field_brebo_building_ref' => [
        'label' => 'Gebouw', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'node'],
        'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_building' => 'brebo_building']]],
        'description' => 'Permanent gebouw waarop de activiteit betrekking heeft.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 5,
      ],
      'field_brebo_plan_object_ref' => [
        'label' => 'Gebouwobject', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'node'],
        'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => [
          'brebo_building_zone' => 'brebo_building_zone', 'brebo_cluster' => 'brebo_cluster',
          'brebo_dwelling' => 'brebo_dwelling', 'brebo_product_position' => 'brebo_product_position',
        ]]],
        'description' => 'Permanente zone, cluster, woning of productpositie waarop het werk wordt uitgevoerd.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 6,
      ],
      'field_brebo_plan_sequence' => [
        'label' => 'Volgorde', 'type' => 'integer', 'required' => TRUE, 'storage' => [],
        'description' => 'Sorteervolgorde binnen fase of werkstroom.',
        'widget' => 'number', 'formatter' => 'number_integer', 'weight' => 7, 'default_value' => [['value' => 10]],
      ],
      'field_brebo_plan_baseline_start' => [
        'label' => 'Baseline start', 'type' => 'datetime', 'required' => FALSE,
        'storage' => ['datetime_type' => 'date'], 'description' => 'Goedgekeurde oorspronkelijke startdatum.',
        'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => 8,
      ],
      'field_brebo_plan_baseline_end' => [
        'label' => 'Baseline gereed', 'type' => 'datetime', 'required' => FALSE,
        'storage' => ['datetime_type' => 'date'], 'description' => 'Goedgekeurde oorspronkelijke einddatum.',
        'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => 9,
      ],
      'field_brebo_plan_start' => [
        'label' => 'Actuele start', 'type' => 'datetime', 'required' => TRUE,
        'storage' => ['datetime_type' => 'date'], 'description' => 'Huidig geplande startdatum.',
        'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => 10,
      ],
      'field_brebo_plan_end' => [
        'label' => 'Actueel gereed', 'type' => 'datetime', 'required' => TRUE,
        'storage' => ['datetime_type' => 'date'], 'description' => 'Huidig geplande einddatum.',
        'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => 11,
      ],
      'field_brebo_plan_actual_start' => [
        'label' => 'Werkelijke start', 'type' => 'datetime', 'required' => FALSE,
        'storage' => ['datetime_type' => 'date'], 'description' => 'Aantoonbare werkelijke startdatum.',
        'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => 12,
      ],
      'field_brebo_plan_actual_end' => [
        'label' => 'Werkelijk gereed', 'type' => 'datetime', 'required' => FALSE,
        'storage' => ['datetime_type' => 'date'], 'description' => 'Aantoonbare werkelijke gereeddatum.',
        'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => 13,
      ],
      'field_brebo_plan_duration' => [
        'label' => 'Doorlooptijd in werkdagen', 'type' => 'integer', 'required' => FALSE, 'storage' => [],
        'description' => 'Geplande netto doorlooptijd in werkdagen.',
        'widget' => 'number', 'formatter' => 'number_integer', 'weight' => 14,
      ],
      'field_brebo_plan_predecessors' => [
        'label' => 'Voorgangers', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'node'], 'cardinality' => -1,
        'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_plan_activity' => 'brebo_plan_activity']]],
        'description' => 'Activiteiten die volgens de afhankelijkheidsrelatie eerst of gelijktijdig moeten starten of eindigen.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 15,
      ],
      'field_brebo_plan_relation' => [
        'label' => 'Afhankelijkheidsrelatie', 'type' => 'string', 'required' => FALSE,
        'storage' => ['max_length' => 8], 'description' => 'FS, SS, FF of SF; standaard einde-start.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 16, 'default_value' => [['value' => 'FS']],
      ],
      'field_brebo_plan_lag_days' => [
        'label' => 'Wachttijd in werkdagen', 'type' => 'integer', 'required' => FALSE, 'storage' => [],
        'description' => 'Positieve wachttijd of negatieve overlap na de voorganger.',
        'widget' => 'number', 'formatter' => 'number_integer', 'weight' => 17, 'default_value' => [['value' => 0]],
      ],
      'field_brebo_plan_status' => [
        'label' => 'Activiteitstatus', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 32], 'description' => 'Niet gestart, In uitvoering, Geblokkeerd, Gereed of Vervallen.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 18, 'default_value' => [['value' => 'Niet gestart']],
      ],
      'field_brebo_plan_progress' => [
        'label' => 'Voortgang percentage', 'type' => 'decimal', 'required' => TRUE,
        'storage' => ['precision' => 5, 'scale' => 2], 'description' => 'Aantoonbare voortgang van 0 tot en met 100 procent.',
        'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 19, 'default_value' => [['value' => '0.00']],
      ],
      'field_brebo_plan_owner' => [
        'label' => 'Verantwoordelijke', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'user'], 'field_settings' => ['handler' => 'default:user'],
        'description' => 'BREBO-eigenaar van planning, opvolging en gereedmelding.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 20,
      ],
      'field_brebo_plan_supplier' => [
        'label' => 'Uitvoerende partij', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'node'],
        'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['brebo_organization' => 'brebo_organization']]],
        'description' => 'Onderaannemer, leverancier of andere uitvoerende organisatie.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 21,
      ],
      'field_brebo_plan_milestone' => [
        'label' => 'Mijlpaal', 'type' => 'boolean', 'required' => FALSE, 'storage' => [],
        'description' => 'Activiteit zonder doorlooptijd die een formeel moment markeert.',
        'widget' => 'boolean_checkbox', 'formatter' => 'boolean', 'weight' => 22, 'default_value' => [['value' => 0]],
      ],
      'field_brebo_plan_critical' => [
        'label' => 'Kritieke activiteit', 'type' => 'boolean', 'required' => FALSE, 'storage' => [],
        'description' => 'Vertraging beïnvloedt direct de project- of opleverdatum.',
        'widget' => 'boolean_checkbox', 'formatter' => 'boolean', 'weight' => 23, 'default_value' => [['value' => 0]],
      ],
      'field_brebo_plan_proof_required' => [
        'label' => 'Bewijs vereist', 'type' => 'boolean', 'required' => FALSE, 'storage' => [],
        'description' => 'Gereedmelding vereist foto, controle, document of ander afsluitbewijs.',
        'widget' => 'boolean_checkbox', 'formatter' => 'boolean', 'weight' => 24, 'default_value' => [['value' => 0]],
      ],
      'field_brebo_plan_evidence' => [
        'label' => 'Gereedbewijs', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Herleidbare verwijzing naar foto, document, controle of opleverbewijs.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 25,
      ],
      'field_brebo_plan_block_reason' => [
        'label' => 'Blokkeringsreden', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Oorzaak, gevolg en benodigde deblokkeringsactie.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 26,
      ],
    ]);

  foreach (['brebo_projectleider', 'brebo_werkvoorbereider', 'brebo_uitvoerder', 'brebo_kwaliteitsmanager'] as $role_id) {
    if ($role = \Drupal\user\Entity\Role::load($role_id)) {
      foreach (['create brebo_plan_activity content', 'edit own brebo_plan_activity content',
        'edit any brebo_plan_activity content', 'view brebo_plan_activity revisions'] as $permission) {
        $role->grantPermission($permission);
      }
      $role->save();
    }
  }

  return 'Objectgekoppelde uitvoeringsplanning met baseline, afhankelijkheden en bewijs toegevoegd.';
}

