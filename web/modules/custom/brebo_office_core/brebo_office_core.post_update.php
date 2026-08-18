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


/**
 * Adds resource capacity, availability and crew planning.
 */
function brebo_office_core_post_update_personnel_planning(array &$sandbox = NULL): string {
  \Drupal::moduleHandler()->loadInclude('brebo_office_core', 'install');
  if (!function_exists('_brebo_office_core_create_node_bundle')) {
    throw new \RuntimeException('BREBO Office install helper is unavailable.');
  }

  $node_ref = static function (string $label, string $bundle, string $description, int $weight, bool $required = FALSE, int $cardinality = 1): array {
    return [
      'label' => $label, 'type' => 'entity_reference', 'required' => $required,
      'storage' => ['target_type' => 'node'], 'cardinality' => $cardinality,
      'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => [$bundle => $bundle]]],
      'description' => $description,
      'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => $weight,
    ];
  };
  $date = static function (string $label, string $description, int $weight, bool $required = FALSE): array {
    return [
      'label' => $label, 'type' => 'datetime', 'required' => $required,
      'storage' => ['datetime_type' => 'date'], 'description' => $description,
      'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => $weight,
    ];
  };

  _brebo_office_core_create_node_bundle('brebo_staff_team', 'Ploeg',
    'Herbruikbare ploeg van eigen of ingehuurde vakmensen met capaciteit en vakbekwaamheden.', [
      'field_brebo_team_code' => [
        'label' => 'Ploegcode', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 64], 'description' => 'Unieke herkenbare code van de ploeg.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 1,
      ],
      'field_brebo_team_org' => $node_ref('Organisatie', 'brebo_organization', 'Werkgever, onderaannemer of inlenende organisatie.', 2),
      'field_brebo_team_members' => $node_ref('Ploegleden', 'brebo_contact', 'Actieve personen die normaal deel uitmaken van deze ploeg.', 3, FALSE, -1),
      'field_brebo_team_lead' => $node_ref('Voorman', 'brebo_contact', 'Eerste operationele aanspreekpunt van de ploeg.', 4),
      'field_brebo_team_capacity' => [
        'label' => 'Normcapaciteit uren per dag', 'type' => 'decimal', 'required' => TRUE,
        'storage' => ['precision' => 8, 'scale' => 2], 'description' => 'Beschikbare gezamenlijke dagcapaciteit bij volledige bezetting.',
        'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 5,
      ],
      'field_brebo_team_skills' => [
        'label' => 'Vakbekwaamheden', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Werksoorten, certificaten en aantoonbare vakbekwaamheden van de ploeg.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 6,
      ],
      'field_brebo_team_active' => [
        'label' => 'Actief inzetbaar', 'type' => 'boolean', 'required' => FALSE, 'storage' => [],
        'description' => 'Geeft aan dat de ploeg voor nieuwe inzet mag worden gepland.',
        'widget' => 'boolean_checkbox', 'formatter' => 'boolean', 'weight' => 7, 'default_value' => [['value' => 1]],
      ],
      'field_brebo_team_notes' => [
        'label' => 'Planningsnotities', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Niet-medische operationele aandachtspunten voor inzet en logistiek.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 8,
      ],
    ]);

  _brebo_office_core_create_node_bundle('brebo_availability', 'Beschikbaarheid',
    'Tijdvak waarin een persoon of ploeg inzetbaar, gereserveerd of niet beschikbaar is.', [
      'field_brebo_avail_contact' => $node_ref('Persoon', 'brebo_contact', 'Contactpersoon waarop de beschikbaarheid betrekking heeft.', 1),
      'field_brebo_avail_user' => [
        'label' => 'Intern account', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'user'], 'field_settings' => ['handler' => 'default:user'],
        'description' => 'Optionele koppeling voor een interne BREBO-medewerker.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 2,
      ],
      'field_brebo_avail_team' => $node_ref('Ploeg', 'brebo_staff_team', 'Ploeg waarop de beschikbaarheid betrekking heeft.', 3),
      'field_brebo_avail_start' => $date('Startdatum', 'Eerste dag van dit beschikbaarheidstijdvak.', 4, TRUE),
      'field_brebo_avail_end' => $date('Einddatum', 'Laatste dag van dit beschikbaarheidstijdvak.', 5, TRUE),
      'field_brebo_avail_hours_day' => [
        'label' => 'Beschikbare uren per dag', 'type' => 'decimal', 'required' => FALSE,
        'storage' => ['precision' => 6, 'scale' => 2], 'description' => 'Netto inzetbare uren per werkdag binnen het tijdvak.',
        'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 6,
      ],
      'field_brebo_avail_type' => [
        'label' => 'Beschikbaarheidstype', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 32], 'description' => 'Beschikbaar, gereserveerd, verlof, opleiding of niet beschikbaar.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 7, 'default_value' => [['value' => 'Beschikbaar']],
      ],
      'field_brebo_avail_status' => [
        'label' => 'Status', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 32], 'description' => 'Concept, bevestigd of vervallen.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 8, 'default_value' => [['value' => 'Bevestigd']],
      ],
      'field_brebo_avail_note' => [
        'label' => 'Operationele toelichting', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Alleen planningsinformatie; geen medische gegevens vastleggen.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 9,
      ],
    ]);

  _brebo_office_core_create_node_bundle('brebo_staff_assignment', 'Personeelsinzet',
    'Geplande inzet van een persoon of ploeg op een objectgekoppelde projectactiviteit.', [
      'field_brebo_staff_project' => $node_ref('Project', 'brebo_project', 'Tijdelijke projectcontext van de inzet.', 1, TRUE),
      'field_brebo_staff_activity' => $node_ref('Planningsactiviteit', 'brebo_plan_activity', 'Uitvoeringsactiviteit waarop capaciteit wordt ingezet.', 2, TRUE),
      'field_brebo_staff_building' => $node_ref('Gebouw', 'brebo_building', 'Permanent gebouw waarop de inzet plaatsvindt.', 3),
      'field_brebo_staff_object' => [
        'label' => 'Gebouwobject', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'node'],
        'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => [
          'brebo_building_zone' => 'brebo_building_zone', 'brebo_cluster' => 'brebo_cluster',
          'brebo_dwelling' => 'brebo_dwelling', 'brebo_product_position' => 'brebo_product_position',
        ]]],
        'description' => 'Zone, cluster, woning of productpositie waarop de inzet betrekking heeft.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 4,
      ],
      'field_brebo_staff_contact' => $node_ref('Persoon', 'brebo_contact', 'Eigen of ingehuurde vakmens die wordt ingezet.', 5),
      'field_brebo_staff_user' => [
        'label' => 'Intern account', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'user'], 'field_settings' => ['handler' => 'default:user'],
        'description' => 'Optionele interne BREBO-gebruiker die wordt ingezet.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 6,
      ],
      'field_brebo_staff_team' => $node_ref('Ploeg', 'brebo_staff_team', 'Ploeg die geheel of gedeeltelijk wordt ingezet.', 7),
      'field_brebo_staff_org' => $node_ref('Uitvoerende organisatie', 'brebo_organization', 'Werkgever, onderaannemer of inleenorganisatie.', 8),
      'field_brebo_staff_role' => [
        'label' => 'Rol of vak', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 128], 'description' => 'Bijvoorbeeld uitvoerder, voorman, schilder, glaszetter of timmerman.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 9,
      ],
      'field_brebo_staff_start' => $date('Geplande start', 'Eerste geplande inzetdag.', 10, TRUE),
      'field_brebo_staff_end' => $date('Gepland gereed', 'Laatste geplande inzetdag.', 11, TRUE),
      'field_brebo_staff_plan_hours' => [
        'label' => 'Geplande uren', 'type' => 'decimal', 'required' => TRUE,
        'storage' => ['precision' => 10, 'scale' => 2], 'description' => 'Totale netto geplande inzet binnen dit tijdvak.',
        'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 12,
      ],
      'field_brebo_staff_hours_day' => [
        'label' => 'Uren per dag', 'type' => 'decimal', 'required' => FALSE,
        'storage' => ['precision' => 6, 'scale' => 2], 'description' => 'Gemiddeld geplande inzet per werkdag.',
        'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 13,
      ],
      'field_brebo_staff_alloc_pct' => [
        'label' => 'Inzetpercentage', 'type' => 'decimal', 'required' => FALSE,
        'storage' => ['precision' => 5, 'scale' => 2], 'description' => 'Aandeel van de beschikbare capaciteit van 0 tot en met 100 procent.',
        'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 14,
      ],
      'field_brebo_staff_status' => [
        'label' => 'Inzetstatus', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 32], 'description' => 'Voorlopig, bevestigd, gestart, afgerond of vervallen.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 15, 'default_value' => [['value' => 'Voorlopig']],
      ],
      'field_brebo_staff_skill_req' => [
        'label' => 'Vereiste vakbekwaamheden', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Benodigde vaardigheden, VCA, certificaten of projectkwalificaties.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 16,
      ],
      'field_brebo_staff_skill_ok' => [
        'label' => 'Vakbekwaamheid gecontroleerd', 'type' => 'boolean', 'required' => FALSE, 'storage' => [],
        'description' => 'Bevestigt dat de vereiste kwalificaties aantoonbaar zijn gecontroleerd.',
        'widget' => 'boolean_checkbox', 'formatter' => 'boolean', 'weight' => 17, 'default_value' => [['value' => 0]],
      ],
      'field_brebo_staff_check_by' => [
        'label' => 'Gecontroleerd door', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'user'], 'field_settings' => ['handler' => 'default:user'],
        'description' => 'BREBO-gebruiker die de vakbekwaamheid heeft gecontroleerd.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 18,
      ],
      'field_brebo_staff_check_date' => $date('Controledatum', 'Datum waarop de vakbekwaamheid is gecontroleerd.', 19),
      'field_brebo_staff_actual_hours' => [
        'label' => 'Werkelijke uren', 'type' => 'decimal', 'required' => FALSE,
        'storage' => ['precision' => 10, 'scale' => 2], 'description' => 'Bevestigde werkelijk bestede uren voor voortgang en nacalculatie.',
        'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 20,
      ],
      'field_brebo_staff_conflict' => [
        'label' => 'Capaciteitsconflict', 'type' => 'string', 'required' => FALSE,
        'storage' => ['max_length' => 32], 'description' => 'Geen, waarschuwing of blokkade na capaciteitscontrole.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 21, 'default_value' => [['value' => 'Geen']],
      ],
      'field_brebo_staff_finance_ref' => $node_ref('Werkbegrotingsregel', 'brebo_work_budget_line', 'Verplichte bron voor vrijgegeven uren, kostprijs, inhuurafspraak en nacalculatie.', 22, TRUE),
    ]);

  $permissions = [];
  foreach (['brebo_staff_team', 'brebo_availability', 'brebo_staff_assignment'] as $bundle) {
    $permissions = array_merge($permissions, [
      "create $bundle content",
      "edit own $bundle content",
      "edit any $bundle content",
      "view $bundle revisions",
    ]);
  }
  foreach (['brebo_projectleider', 'brebo_werkvoorbereider', 'brebo_uitvoerder', 'brebo_kwaliteitsmanager'] as $role_id) {
    if ($role = \Drupal\user\Entity\Role::load($role_id)) {
      foreach ($permissions as $permission) {
        $role->grantPermission($permission);
      }
      $role->save();
    }
  }

  return 'Personeelsinzet, beschikbaarheid en ploegen met project-, object-, capaciteit- en kwalificatiekoppelingen toegevoegd.';
}


/**
 * Adds BREBO Inzet shift, time, clock and leave records.
 */
function brebo_office_core_post_update_brebo_inzet(array &$sandbox = NULL): string {
  \Drupal::moduleHandler()->loadInclude('brebo_office_core', 'install');
  if (!function_exists('_brebo_office_core_create_node_bundle')) {
    throw new \RuntimeException('BREBO Office install helper is unavailable.');
  }

  $ref = static function (string $label, string $bundle, string $description, int $weight, bool $required = FALSE): array {
    return [
      'label' => $label, 'type' => 'entity_reference', 'required' => $required,
      'storage' => ['target_type' => 'node'],
      'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => [$bundle => $bundle]]],
      'description' => $description, 'widget' => 'entity_reference_autocomplete',
      'formatter' => 'entity_reference_label', 'weight' => $weight,
    ];
  };
  $user_ref = static function (string $label, string $description, int $weight): array {
    return [
      'label' => $label, 'type' => 'entity_reference', 'required' => FALSE,
      'storage' => ['target_type' => 'user'], 'field_settings' => ['handler' => 'default:user'],
      'description' => $description, 'widget' => 'entity_reference_autocomplete',
      'formatter' => 'entity_reference_label', 'weight' => $weight,
    ];
  };
  $date = static function (string $label, string $description, int $weight, bool $required = FALSE, string $type = 'date'): array {
    return [
      'label' => $label, 'type' => 'datetime', 'required' => $required,
      'storage' => ['datetime_type' => $type], 'description' => $description,
      'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => $weight,
    ];
  };
  $decimal = static function (string $label, string $description, int $weight, bool $required = FALSE): array {
    return [
      'label' => $label, 'type' => 'decimal', 'required' => $required,
      'storage' => ['precision' => 12, 'scale' => 4], 'description' => $description,
      'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => $weight,
    ];
  };

  _brebo_office_core_create_node_bundle('brebo_shift', 'Dienst',
    'BREBO Inzet-dienst gekoppeld aan project, gebouw, activiteit en vrijgegeven werkbegrotingsuren.', [
      'field_brebo_shift_assignment' => $ref('Personeelsinzet', 'brebo_staff_assignment', 'Goedgekeurde inzetbasis voor deze dienst.', 1, TRUE),
      'field_brebo_shift_project' => $ref('Project', 'brebo_project', 'Projectcontext van de dienst.', 2, TRUE),
      'field_brebo_shift_building' => $ref('Gebouw', 'brebo_building', 'PDOK-gepositioneerde werklocatie.', 3, TRUE),
      'field_brebo_shift_activity' => $ref('Planningsactiviteit', 'brebo_plan_activity', 'Uitvoeringsactiviteit waarop wordt gewerkt.', 4, TRUE),
      'field_brebo_shift_budget' => $ref('Werkbegrotingsregel', 'brebo_work_budget_line', 'Verplichte bron van vrijgegeven arbeidsuren.', 5, TRUE),
      'field_brebo_shift_contact' => $ref('Persoon', 'brebo_contact', 'Ingeroosterde eigen of ingehuurde persoon.', 6),
      'field_brebo_shift_user' => $user_ref('Intern account', 'Optioneel intern BREBO-account.', 7),
      'field_brebo_shift_team' => $ref('Ploeg', 'brebo_staff_team', 'Ingeroosterde ploeg.', 8),
      'field_brebo_shift_start' => $date('Start dienst', 'Geplande lokale startdatum en -tijd.', 9, TRUE, 'datetime'),
      'field_brebo_shift_end' => $date('Einde dienst', 'Geplande lokale einddatum en -tijd.', 10, TRUE, 'datetime'),
      'field_brebo_shift_break_min' => [
        'label' => 'Onbetaalde pauze minuten', 'type' => 'integer', 'required' => FALSE, 'storage' => [],
        'description' => 'Geplande onbetaalde pauzeduur.', 'widget' => 'number',
        'formatter' => 'number_integer', 'weight' => 11, 'default_value' => [['value' => 0]],
      ],
      'field_brebo_shift_people' => [
        'label' => 'Benodigde bezetting', 'type' => 'integer', 'required' => TRUE, 'storage' => [],
        'description' => 'Benodigd aantal personen voor deze dienst.', 'widget' => 'number',
        'formatter' => 'number_integer', 'weight' => 12, 'default_value' => [['value' => 1]],
      ],
      'field_brebo_shift_open' => [
        'label' => 'Open dienst', 'type' => 'boolean', 'required' => FALSE, 'storage' => [],
        'description' => 'Dienst waarvoor nog bezetting gezocht wordt.', 'widget' => 'boolean_checkbox',
        'formatter' => 'boolean', 'weight' => 13, 'default_value' => [['value' => 0]],
      ],
      'field_brebo_shift_status' => [
        'label' => 'Dienststatus', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 32], 'description' => 'Concept, gepubliceerd, gestart, afgerond of vervallen.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 14,
        'default_value' => [['value' => 'Concept']],
      ],
      'field_brebo_shift_radius' => $decimal('Toegestane radius meter', 'Geofence rond de PDOK-gebouwlocatie.', 15),
      'field_brebo_shift_notes' => [
        'label' => 'Werkinstructie', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Operationele instructie, toegang en veiligheidsaandachtspunten.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 16,
      ],
    ]);

  _brebo_office_core_create_node_bundle('brebo_time_entry', 'Urenregistratie',
    'Gewerkte uren uit BREBO Inzet die na goedkeuring naar werkbegroting en nacalculatie gaan.', [
      'field_brebo_time_shift' => $ref('Dienst', 'brebo_shift', 'Dienst waarop de uren betrekking hebben.', 1, TRUE),
      'field_brebo_time_assignment' => $ref('Personeelsinzet', 'brebo_staff_assignment', 'Onderliggende personeelsinzet.', 2, TRUE),
      'field_brebo_time_budget' => $ref('Werkbegrotingsregel', 'brebo_work_budget_line', 'Werkbegrotingsregel waarop uren worden verantwoord.', 3, TRUE),
      'field_brebo_time_contact' => $ref('Persoon', 'brebo_contact', 'Persoon die de uren heeft gewerkt.', 4),
      'field_brebo_time_user' => $user_ref('Intern account', 'Optioneel intern BREBO-account.', 5),
      'field_brebo_time_start' => $date('Werkelijke start', 'Vastgestelde startdatum en -tijd.', 6, TRUE, 'datetime'),
      'field_brebo_time_end' => $date('Werkelijk einde', 'Vastgestelde einddatum en -tijd.', 7, TRUE, 'datetime'),
      'field_brebo_time_break_min' => [
        'label' => 'Onbetaalde pauze minuten', 'type' => 'integer', 'required' => FALSE, 'storage' => [],
        'description' => 'Werkelijke onbetaalde pauzeduur.', 'widget' => 'number',
        'formatter' => 'number_integer', 'weight' => 8, 'default_value' => [['value' => 0]],
      ],
      'field_brebo_time_hours' => $decimal('Netto gewerkte uren', 'Automatisch berekende uren na onbetaalde pauze.', 9, TRUE),
      'field_brebo_time_status' => [
        'label' => 'Urenstatus', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 32], 'description' => 'Concept, ingediend, goedgekeurd, afgekeurd of gecorrigeerd.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 10,
        'default_value' => [['value' => 'Concept']],
      ],
      'field_brebo_time_approved_by' => $user_ref('Goedgekeurd door', 'Projectleider of uitvoerder die de uren vrijgeeft.', 11),
      'field_brebo_time_approved_at' => $date('Goedkeuringsmoment', 'Datum en tijd van formele goedkeuring.', 12, FALSE, 'datetime'),
      'field_brebo_time_correction' => [
        'label' => 'Correctietoelichting', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Verplichte reden bij correctie of afwijzing.', 'widget' => 'text_textarea',
        'formatter' => 'text_default', 'weight' => 13,
      ],
    ]);

  _brebo_office_core_create_node_bundle('brebo_clock_event', 'Klokregistratie',
    'Onwijzigbaar klokmoment met eenmalige locatiecontrole tegen de PDOK-gebouwlocatie.', [
      'field_brebo_clock_shift' => $ref('Dienst', 'brebo_shift', 'Dienst waarop wordt geklokt.', 1, TRUE),
      'field_brebo_clock_contact' => $ref('Persoon', 'brebo_contact', 'Persoon die klokt.', 2),
      'field_brebo_clock_user' => $user_ref('Intern account', 'Optioneel intern BREBO-account.', 3),
      'field_brebo_clock_building' => $ref('Gebouw', 'brebo_building', 'Gebouw waarvan PDOK-coördinaten worden gebruikt.', 4, TRUE),
      'field_brebo_clock_type' => [
        'label' => 'Kloktype', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 32], 'description' => 'In, pauze start, pauze einde of uit.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 5,
      ],
      'field_brebo_clock_at' => $date('Klokmoment', 'Servervastgelegde datum en tijd.', 6, TRUE, 'datetime'),
      'field_brebo_clock_lat' => $decimal('Latitude', 'Eenmalig ontvangen locatie bij het klokmoment.', 7),
      'field_brebo_clock_lon' => $decimal('Longitude', 'Eenmalig ontvangen locatie bij het klokmoment.', 8),
      'field_brebo_clock_accuracy' => $decimal('Locatienauwkeurigheid meter', 'Door het apparaat gemelde nauwkeurigheid.', 9),
      'field_brebo_clock_distance' => $decimal('Afstand tot gebouw meter', 'Berekende afstand tot PDOK-gebouwcoördinaten.', 10),
      'field_brebo_clock_geo_status' => [
        'label' => 'Locatiecontrole', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 32], 'description' => 'Binnen zone, buiten zone, geen locatie of handmatig goedgekeurd.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 11,
      ],
      'field_brebo_clock_device' => [
        'label' => 'Apparaatreferentie', 'type' => 'string', 'required' => FALSE,
        'storage' => ['max_length' => 128], 'description' => 'Niet-herleidbare hash voor fraudedetectie; geen hardware-ID.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 12,
      ],
      'field_brebo_clock_note' => [
        'label' => 'Afwijkingstoelichting', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Reden en beoordeling van een locatie- of klokafwijking.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 13,
      ],
    ]);

  _brebo_office_core_create_node_bundle('brebo_leave_request', 'Verlofaanvraag',
    'Aanvraag die na goedkeuring automatisch de beschikbaarheid voor BREBO Inzet beperkt.', [
      'field_brebo_leave_contact' => $ref('Persoon', 'brebo_contact', 'Persoon die verlof aanvraagt.', 1),
      'field_brebo_leave_user' => $user_ref('Intern account', 'Optioneel intern BREBO-account.', 2),
      'field_brebo_leave_start' => $date('Start verlof', 'Eerste verlofdag.', 3, TRUE),
      'field_brebo_leave_end' => $date('Einde verlof', 'Laatste verlofdag.', 4, TRUE),
      'field_brebo_leave_hours' => $decimal('Verlofuren', 'Netto aangevraagde verlofuren.', 5, TRUE),
      'field_brebo_leave_type' => [
        'label' => 'Verloftype', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 64], 'description' => 'Vakantie, bijzonder verlof, opleiding of niet beschikbaar; geen medische details.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 6,
      ],
      'field_brebo_leave_status' => [
        'label' => 'Aanvraagstatus', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 32], 'description' => 'Concept, ingediend, goedgekeurd, afgewezen of ingetrokken.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 7,
        'default_value' => [['value' => 'Concept']],
      ],
      'field_brebo_leave_approver' => $user_ref('Beoordeeld door', 'Leidinggevende die de aanvraag beoordeelt.', 8),
      'field_brebo_leave_decided_at' => $date('Beslismoment', 'Datum en tijd van het besluit.', 9, FALSE, 'datetime'),
      'field_brebo_leave_avail_ref' => $ref('Beschikbaarheid', 'brebo_availability', 'Automatisch aangemaakt beschikbaarheidstijdvak.', 10),
      'field_brebo_leave_reason' => [
        'label' => 'Operationele toelichting', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Geen medische of andere bijzondere persoonsgegevens vastleggen.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 11,
      ],
    ]);

  $permissions = [];
  foreach (['brebo_shift', 'brebo_time_entry', 'brebo_clock_event', 'brebo_leave_request'] as $bundle) {
    $permissions = array_merge($permissions, [
      "create $bundle content", "edit own $bundle content",
      "edit any $bundle content", "view $bundle revisions",
    ]);
  }
  foreach (['brebo_projectleider', 'brebo_werkvoorbereider', 'brebo_uitvoerder', 'brebo_kwaliteitsmanager'] as $role_id) {
    if ($role = \Drupal\user\Entity\Role::load($role_id)) {
      foreach ($permissions as $permission) {
        $role->grantPermission($permission);
      }
      $role->save();
    }
  }

  return 'BREBO Inzet met diensten, uren, klokmomenten, verlof, PDOK-locatiecontrole en rolrechten toegevoegd.';
}


/**
 * Adds the BREBO Inzet competency and certificate register.
 */
function brebo_office_core_post_update_inzet_competencies(array &$sandbox = NULL): string {
  \Drupal::moduleHandler()->loadInclude('brebo_office_core', 'install');
  if (!function_exists('_brebo_office_core_create_node_bundle')) {
    throw new \RuntimeException('BREBO Office install helper is unavailable.');
  }

  $node_ref = static function (string $label, string $bundle, string $description, int $weight, bool $required = FALSE, int $cardinality = 1): array {
    return [
      'label' => $label, 'type' => 'entity_reference', 'required' => $required,
      'storage' => ['target_type' => 'node'], 'cardinality' => $cardinality,
      'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => [$bundle => $bundle]]],
      'description' => $description, 'widget' => 'entity_reference_autocomplete',
      'formatter' => 'entity_reference_label', 'weight' => $weight,
    ];
  };
  $user_ref = static function (string $label, string $description, int $weight): array {
    return [
      'label' => $label, 'type' => 'entity_reference', 'required' => FALSE,
      'storage' => ['target_type' => 'user'], 'field_settings' => ['handler' => 'default:user'],
      'description' => $description, 'widget' => 'entity_reference_autocomplete',
      'formatter' => 'entity_reference_label', 'weight' => $weight,
    ];
  };
  $date = static function (string $label, string $description, int $weight, bool $required = FALSE, string $type = 'date'): array {
    return [
      'label' => $label, 'type' => 'datetime', 'required' => $required,
      'storage' => ['datetime_type' => $type], 'description' => $description,
      'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => $weight,
    ];
  };

  _brebo_office_core_create_node_bundle('brebo_work_skill', 'Vakbekwaamheid',
    'Beheerde vaardigheid, bevoegdheid of certificaateis voor veilige en kwalitatieve personeelsinzet.', [
      'field_brebo_skill_code' => [
        'label' => 'Code', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 64], 'description' => 'Unieke herkenbare code, bijvoorbeeld VCA-B of ETICS-VAK.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 1,
      ],
      'field_brebo_skill_category' => [
        'label' => 'Categorie', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 64], 'description' => 'Vakmanschap, veiligheid, materieel, certificaat of projectbevoegdheid.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 2,
      ],
      'field_brebo_skill_description' => [
        'label' => 'Toetsingscriteria', 'type' => 'text_long', 'required' => TRUE, 'storage' => [],
        'description' => 'Objectieve criteria waaraan een geldig bewijs moet voldoen.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 3,
      ],
      'field_brebo_skill_expiring' => [
        'label' => 'Heeft vervaldatum', 'type' => 'boolean', 'required' => FALSE, 'storage' => [],
        'description' => 'Geeft aan dat geldigheid periodiek opnieuw moet worden aangetoond.',
        'widget' => 'boolean_checkbox', 'formatter' => 'boolean', 'weight' => 4,
        'default_value' => [['value' => 1]],
      ],
      'field_brebo_skill_active' => [
        'label' => 'Actief', 'type' => 'boolean', 'required' => FALSE, 'storage' => [],
        'description' => 'Alleen actieve vakbekwaamheden mogen aan nieuwe diensten worden gekoppeld.',
        'widget' => 'boolean_checkbox', 'formatter' => 'boolean', 'weight' => 5,
        'default_value' => [['value' => 1]],
      ],
    ]);

  _brebo_office_core_create_node_bundle('brebo_qualification', 'Kwalificatiebewijs',
    'Controleerbaar bewijs dat een persoon een vereiste vakbekwaamheid bezit.', [
      'field_brebo_qual_contact' => $node_ref('Persoon', 'brebo_contact', 'Eigen of ingehuurde persoon waarop het bewijs betrekking heeft.', 1),
      'field_brebo_qual_user' => $user_ref('Intern account', 'Optioneel intern BREBO-account waarop het bewijs betrekking heeft.', 2),
      'field_brebo_qual_skill' => $node_ref('Vakbekwaamheid', 'brebo_work_skill', 'Aangetoonde vaardigheid of bevoegdheid.', 3, TRUE),
      'field_brebo_qual_issuer' => [
        'label' => 'Uitgevende instantie', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 255], 'description' => 'Opleider, certificerende instelling of bevoegde beoordelaar.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 4,
      ],
      'field_brebo_qual_number' => [
        'label' => 'Bewijsnummer', 'type' => 'string', 'required' => FALSE,
        'storage' => ['max_length' => 128], 'description' => 'Certificaat-, pas- of registratienummer.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 5,
      ],
      'field_brebo_qual_issued' => $date('Afgiftedatum', 'Datum waarop het bewijs is afgegeven.', 6),
      'field_brebo_qual_expires' => $date('Geldig tot', 'Laatste geldige dag; leeg indien het bewijs niet vervalt.', 7),
      'field_brebo_qual_status' => [
        'label' => 'Bewijsstatus', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 32], 'description' => 'Concept, te controleren, geldig, verlopen, afgewezen of ingetrokken.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 8,
        'default_value' => [['value' => 'Te controleren']],
      ],
      'field_brebo_qual_verified_by' => $user_ref('Gecontroleerd door', 'BREBO-gebruiker die bron en geldigheid heeft gecontroleerd.', 9),
      'field_brebo_qual_verified_at' => $date('Controlemoment', 'Datum en tijd van de laatste inhoudelijke controle.', 10, FALSE, 'datetime'),
      'field_brebo_qual_note' => [
        'label' => 'Controletoelichting', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Bron, uitzonderingen en afwijs- of intrekkingsreden; geen bijzondere persoonsgegevens.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 11,
      ],
    ]);

  _brebo_office_core_create_node_bundle('brebo_shift', 'Dienst',
    'BREBO Inzet-dienst gekoppeld aan project, gebouw, activiteit en vrijgegeven werkbegrotingsuren.', [
      'field_brebo_shift_skills' => $node_ref('Vereiste vakbekwaamheden', 'brebo_work_skill', 'Alle harde vak-, veiligheids- en certificaateisen voor deze dienst.', 17, FALSE, -1),
      'field_brebo_shift_match' => [
        'label' => 'Kwalificatiematch', 'type' => 'string', 'required' => FALSE,
        'storage' => ['max_length' => 32], 'description' => 'Niet gecontroleerd, passend, waarschuwing of blokkade.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 18,
        'default_value' => [['value' => 'Niet gecontroleerd']],
      ],
      'field_brebo_shift_match_note' => [
        'label' => 'Matchtoelichting', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Automatische toelichting op ontbrekende of verlopen kwalificaties.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 19,
      ],
    ]);

  $permissions = [];
  foreach (['brebo_work_skill', 'brebo_qualification'] as $bundle) {
    $permissions = array_merge($permissions, [
      "create $bundle content", "edit own $bundle content",
      "edit any $bundle content", "view $bundle revisions",
    ]);
  }
  foreach (['brebo_projectleider', 'brebo_werkvoorbereider', 'brebo_uitvoerder', 'brebo_kwaliteitsmanager'] as $role_id) {
    if ($role = \Drupal\user\Entity\Role::load($role_id)) {
      foreach ($permissions as $permission) {
        $role->grantPermission($permission);
      }
      $role->save();
    }
  }

  return 'Vakbekwaamheden, kwalificatiebewijzen, diensteisen en rolrechten toegevoegd aan BREBO Inzet.';
}


/**
 * Adds audit fields for human-approved workforce proposals.
 */
function brebo_office_core_post_update_inzet_proposal_audit(array &$sandbox = NULL): string {
  \Drupal::moduleHandler()->loadInclude('brebo_office_core', 'install');
  if (!function_exists('_brebo_office_core_create_node_bundle')) {
    throw new \RuntimeException('BREBO Office install helper is unavailable.');
  }

  _brebo_office_core_create_node_bundle('brebo_shift', 'Dienst',
    'BREBO Inzet-dienst gekoppeld aan project, gebouw, activiteit en vrijgegeven werkbegrotingsuren.', [
      'field_brebo_shift_proposal_score' => [
        'label' => 'Toegepaste voorstelsscore', 'type' => 'decimal', 'required' => FALSE,
        'storage' => ['precision' => 8, 'scale' => 2],
        'description' => 'Uitlegbare score van het door een planner toegepaste personeelsvoorstel.',
        'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 20,
      ],
      'field_brebo_shift_proposal_note' => [
        'label' => 'Voorstelmotivatie', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Motivatie die op het moment van menselijke goedkeuring is vastgelegd.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 21,
      ],
      'field_brebo_shift_assigned_by' => [
        'label' => 'Voorstel toegepast door', 'type' => 'entity_reference', 'required' => FALSE,
        'storage' => ['target_type' => 'user'], 'field_settings' => ['handler' => 'default:user'],
        'description' => 'BREBO-gebruiker die het personeelsvoorstel bewust heeft toegepast.',
        'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 22,
      ],
      'field_brebo_shift_assigned_at' => [
        'label' => 'Voorstel toegepast op', 'type' => 'datetime', 'required' => FALSE,
        'storage' => ['datetime_type' => 'datetime'],
        'description' => 'Servervastgelegd moment van menselijke goedkeuring.',
        'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => 23,
      ],
    ]);

  return 'Auditvelden voor menselijke goedkeuring van personeelsvoorstellen toegevoegd.';
}


/**
 * Adds vehicles, equipment and auditable resource bookings to BREBO Inzet.
 */
function brebo_office_core_post_update_inzet_resources(array &$sandbox = NULL): string {
  \Drupal::moduleHandler()->loadInclude('brebo_office_core', 'install');
  if (!function_exists('_brebo_office_core_create_node_bundle')) {
    throw new \RuntimeException('BREBO Office install helper is unavailable.');
  }

  $ref = static function (string $label, string $bundle, string $description, int $weight, bool $required = FALSE): array {
    return [
      'label' => $label, 'type' => 'entity_reference', 'required' => $required,
      'storage' => ['target_type' => 'node'],
      'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => [$bundle => $bundle]]],
      'description' => $description, 'widget' => 'entity_reference_autocomplete',
      'formatter' => 'entity_reference_label', 'weight' => $weight,
    ];
  };
  $user_ref = static function (string $label, string $description, int $weight): array {
    return [
      'label' => $label, 'type' => 'entity_reference', 'required' => FALSE,
      'storage' => ['target_type' => 'user'], 'field_settings' => ['handler' => 'default:user'],
      'description' => $description, 'widget' => 'entity_reference_autocomplete',
      'formatter' => 'entity_reference_label', 'weight' => $weight,
    ];
  };
  $date = static function (string $label, string $description, int $weight, bool $required = FALSE, string $type = 'date'): array {
    return [
      'label' => $label, 'type' => 'datetime', 'required' => $required,
      'storage' => ['datetime_type' => $type], 'description' => $description,
      'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => $weight,
    ];
  };

  _brebo_office_core_create_node_bundle('brebo_work_resource', 'Materieel of voertuig',
    'Planbaar voertuig, machine, hoogwerker, gereedschap of toegangsmiddel voor BREBO-projecten.', [
      'field_brebo_resource_code' => [
        'label' => 'Materieelcode', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 64], 'description' => 'Unieke herkenbare materieel- of voertuigcode.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 1,
      ],
      'field_brebo_resource_type' => [
        'label' => 'Type', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 64], 'description' => 'Voertuig, hoogwerker, steiger, gereedschap, sleutel of overig middel.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 2,
      ],
      'field_brebo_resource_status' => [
        'label' => 'Operationele status', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 32], 'description' => 'Beschikbaar, gereserveerd, in gebruik, onderhoud, defect of buiten dienst.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 3,
        'default_value' => [['value' => 'Beschikbaar']],
      ],
      'field_brebo_resource_owner' => $ref('Eigenaar/verhuurder', 'brebo_organization', 'Eigenaar, leverancier of verhuurder van het middel.', 4),
      'field_brebo_resource_serial' => [
        'label' => 'Serie- of chassisnummer', 'type' => 'string', 'required' => FALSE,
        'storage' => ['max_length' => 128], 'description' => 'Controleerbare technische identificatie.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 5,
      ],
      'field_brebo_resource_plate' => [
        'label' => 'Kenteken', 'type' => 'string', 'required' => FALSE,
        'storage' => ['max_length' => 32], 'description' => 'Kenteken indien het middel een voertuig is.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 6,
      ],
      'field_brebo_resource_capacity' => [
        'label' => 'Capaciteit', 'type' => 'decimal', 'required' => FALSE,
        'storage' => ['precision' => 12, 'scale' => 2], 'description' => 'Laadvermogen, werkhoogte, aantal personen of andere relevante capaciteit.',
        'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 7,
      ],
      'field_brebo_resource_unit' => [
        'label' => 'Capaciteitseenheid', 'type' => 'string', 'required' => FALSE,
        'storage' => ['max_length' => 32], 'description' => 'Bijvoorbeeld kg, meter, personen of m².',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 8,
      ],
      'field_brebo_resource_location' => $ref('Standplaats', 'brebo_building', 'Actuele of vaste objectgebonden standplaats.', 9),
      'field_brebo_resource_inspection' => $date('Keuring geldig tot', 'Laatste dag waarop de formele keuring geldig is.', 10),
      'field_brebo_resource_maintenance' => $date('Onderhoud uiterlijk', 'Datum waarop gepland onderhoud uiterlijk uitgevoerd moet zijn.', 11),
      'field_brebo_resource_skill' => $ref('Vereiste bevoegdheid', 'brebo_work_skill', 'Vakbekwaamheid of bevoegdheid die de bestuurder of bediener moet bezitten.', 12),
      'field_brebo_resource_cost_hour' => [
        'label' => 'Kostprijs per uur', 'type' => 'decimal', 'required' => FALSE,
        'storage' => ['precision' => 12, 'scale' => 4], 'description' => 'Interne of externe uurkostprijs voor begroting en nacalculatie.',
        'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 13,
      ],
      'field_brebo_resource_active' => [
        'label' => 'Actief planbaar', 'type' => 'boolean', 'required' => FALSE, 'storage' => [],
        'description' => 'Alleen actieve middelen kunnen definitief worden gereserveerd.',
        'widget' => 'boolean_checkbox', 'formatter' => 'boolean', 'weight' => 14,
        'default_value' => [['value' => 1]],
      ],
      'field_brebo_resource_note' => [
        'label' => 'Materieelnotitie', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Gebruikseisen, beperkingen, schade of overdrachtsinformatie.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 15,
      ],
    ]);

  _brebo_office_core_create_node_bundle('brebo_resource_booking', 'Materieelreservering',
    'Tijdgebonden reservering van materieel of voertuig aan dienst, project, gebouw en werkbegroting.', [
      'field_brebo_booking_resource' => $ref('Materieel/voertuig', 'brebo_work_resource', 'Het te reserveren middel.', 1, TRUE),
      'field_brebo_booking_shift' => $ref('Dienst', 'brebo_shift', 'Operationele dienst waarvoor het middel nodig is.', 2, TRUE),
      'field_brebo_booking_project' => $ref('Project', 'brebo_project', 'Projectcontext van de reservering.', 3, TRUE),
      'field_brebo_booking_building' => $ref('Gebouw', 'brebo_building', 'Objectlocatie waar het middel wordt ingezet.', 4, TRUE),
      'field_brebo_booking_budget' => $ref('Werkbegrotingsregel', 'brebo_work_budget_line', 'Vrijgegeven budgetbron voor inzetkosten.', 5, TRUE),
      'field_brebo_booking_start' => $date('Start reservering', 'Geplande uitgifte- of inzetdatum en -tijd.', 6, TRUE, 'datetime'),
      'field_brebo_booking_end' => $date('Einde reservering', 'Geplande retourdatum en -tijd.', 7, TRUE, 'datetime'),
      'field_brebo_booking_driver' => $ref('Bestuurder/bediener', 'brebo_contact', 'Persoon die bevoegd moet zijn om het middel te gebruiken.', 8),
      'field_brebo_booking_status' => [
        'label' => 'Reserveringsstatus', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 32], 'description' => 'Concept, aangevraagd, bevestigd, uitgegeven, retour of geannuleerd.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 9,
        'default_value' => [['value' => 'Concept']],
      ],
      'field_brebo_booking_approved_by' => $user_ref('Vrijgegeven door', 'Planner die de reservering formeel heeft vrijgegeven.', 10),
      'field_brebo_booking_approved_at' => $date('Vrijgavemoment', 'Servervastgelegd moment van formele vrijgave.', 11, FALSE, 'datetime'),
      'field_brebo_booking_control' => [
        'label' => 'Vrijgavecontrole', 'type' => 'string', 'required' => FALSE,
        'storage' => ['max_length' => 32], 'description' => 'Vrijgegeven, waarschuwing of blokkade na automatische materieelcontrole.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 12,
        'default_value' => [['value' => 'Niet gecontroleerd']],
      ],
      'field_brebo_booking_control_note' => [
        'label' => 'Controletoelichting', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Automatische uitleg over keuring, onderhoud, overlap en bestuurdersbevoegdheid.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 13,
      ],
      'field_brebo_booking_note' => [
        'label' => 'Uitgifte- en retournotitie', 'type' => 'text_long', 'required' => FALSE, 'storage' => [],
        'description' => 'Sleutels, brandstof, schade, accessoires en overdrachtsafspraken.',
        'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 14,
      ],
    ]);

  $permissions = [];
  foreach (['brebo_work_resource', 'brebo_resource_booking'] as $bundle) {
    $permissions = array_merge($permissions, [
      "create $bundle content", "edit own $bundle content",
      "edit any $bundle content", "view $bundle revisions",
    ]);
  }
  foreach (['brebo_projectleider', 'brebo_werkvoorbereider', 'brebo_uitvoerder', 'brebo_kwaliteitsmanager'] as $role_id) {
    if ($role = \Drupal\user\Entity\Role::load($role_id)) {
      foreach ($permissions as $permission) {
        $role->grantPermission($permission);
      }
      $role->save();
    }
  }

  return 'Materieel, voertuigen, tijdgebonden reserveringen, werkbegrotingskoppeling en rolrechten toegevoegd.';
}


/**
 * Adds versioned digital day starts and recipient acknowledgements.
 */
function brebo_office_core_post_update_inzet_day_start(array &$sandbox = NULL): string {
  \Drupal::moduleHandler()->loadInclude('brebo_office_core', 'install');
  if (!function_exists('_brebo_office_core_create_node_bundle')) {
    throw new \RuntimeException('BREBO Office install helper is unavailable.');
  }

  $ref = static function (string $label, string $bundle, string $description, int $weight, bool $required = FALSE, int $cardinality = 1): array {
    return [
      'label' => $label, 'type' => 'entity_reference', 'required' => $required,
      'storage' => ['target_type' => 'node'], 'cardinality' => $cardinality,
      'field_settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => [$bundle => $bundle]]],
      'description' => $description, 'widget' => 'entity_reference_autocomplete',
      'formatter' => 'entity_reference_label', 'weight' => $weight,
    ];
  };
  $user_ref = static function (string $label, string $description, int $weight): array {
    return [
      'label' => $label, 'type' => 'entity_reference', 'required' => FALSE,
      'storage' => ['target_type' => 'user'], 'field_settings' => ['handler' => 'default:user'],
      'description' => $description, 'widget' => 'entity_reference_autocomplete',
      'formatter' => 'entity_reference_label', 'weight' => $weight,
    ];
  };
  $date = static function (string $label, string $description, int $weight, bool $required = FALSE): array {
    return [
      'label' => $label, 'type' => 'datetime', 'required' => $required,
      'storage' => ['datetime_type' => 'datetime'], 'description' => $description,
      'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => $weight,
    ];
  };
  $text = static function (string $label, string $description, int $weight, bool $required = FALSE): array {
    return [
      'label' => $label, 'type' => 'text_long', 'required' => $required, 'storage' => [],
      'description' => $description, 'widget' => 'text_textarea',
      'formatter' => 'text_default', 'weight' => $weight,
    ];
  };

  _brebo_office_core_create_node_bundle('brebo_day_start', 'Digitale dagstart',
    'Versieerbare en formeel vrijgegeven werkinstructie voor één BREBO Inzet-dienst.', [
      'field_brebo_day_shift' => $ref('Dienst', 'brebo_shift', 'De dienst waarop deze dagstart uitsluitend betrekking heeft.', 1, TRUE),
      'field_brebo_day_project' => $ref('Project', 'brebo_project', 'Projectcontext die exact met de dienst moet overeenkomen.', 2, TRUE),
      'field_brebo_day_building' => $ref('Gebouw', 'brebo_building', 'Objectlocatie die exact met de dienst moet overeenkomen.', 3, TRUE),
      'field_brebo_day_version' => [
        'label' => 'Dagstartversie', 'type' => 'integer', 'required' => TRUE, 'storage' => [],
        'description' => 'Oplopend versienummer; nieuwe inhoud vereist een nieuwe revisie en ontvangstbevestiging.',
        'widget' => 'number', 'formatter' => 'number_integer', 'weight' => 4,
        'default_value' => [['value' => 1]],
      ],
      'field_brebo_day_status' => [
        'label' => 'Dagstartstatus', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 32], 'description' => 'Concept, gereed, vrijgegeven, vervangen of ingetrokken.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 5,
        'default_value' => [['value' => 'Concept']],
      ],
      'field_brebo_day_generated_by' => $user_ref('Opgesteld door', 'Planner of uitvoerder die de inhoud heeft samengesteld.', 6),
      'field_brebo_day_generated_at' => $date('Opgesteld op', 'Servervastgelegd samenstellingsmoment.', 7),
      'field_brebo_day_work' => $text('Werkzaamheden', 'Concrete werkzaamheden, werkvolgorde en beoogd resultaat.', 8, TRUE),
      'field_brebo_day_access' => $text('Toegang en logistiek', 'Toegang, sleutels, parkeren, bewonersafspraken, opslag en aanvoerroute.', 9, TRUE),
      'field_brebo_day_risks' => $text('Risico’s en maatregelen', 'Taakspecifieke veiligheids-, kwaliteits- en omgevingsrisico’s met beheersmaatregelen.', 10, TRUE),
      'field_brebo_day_controls' => $text('Controlepunten', 'Hold points, fotobewijs, maatvoering, producteisen en vrijgavemomenten.', 11, TRUE),
      'field_brebo_day_contacts' => $text('Contact- en escalatiegegevens', 'Uitvoerder, projectleider, bewonerscontact en nood-/escalatieroute.', 12, TRUE),
      'field_brebo_day_bookings' => $ref('Materieelreserveringen', 'brebo_resource_booking', 'Vrijgegeven voertuigen en middelen voor deze dienst.', 13, FALSE, -1),
      'field_brebo_day_ready' => [
        'label' => 'Gereedheidscontrole', 'type' => 'string', 'required' => FALSE,
        'storage' => ['max_length' => 32], 'description' => 'Gereed, waarschuwing of blokkade na broncontrole.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 14,
        'default_value' => [['value' => 'Niet gecontroleerd']],
      ],
      'field_brebo_day_ready_note' => $text('Gereedheidstoelichting', 'Automatische uitleg over ontbrekende of geblokkeerde broninformatie.', 15),
      'field_brebo_day_released_by' => $user_ref('Vrijgegeven door', 'Bevoegde planner of uitvoerder die de dagstart formeel vrijgeeft.', 16),
      'field_brebo_day_released_at' => $date('Vrijgegeven op', 'Servervastgelegd moment van formele vrijgave.', 17),
    ]);

  _brebo_office_core_create_node_bundle('brebo_day_ack', 'Dagstartbevestiging',
    'Aantoonbare ontvangst en begrip van één specifieke digitale dagstartversie.', [
      'field_brebo_ack_day' => $ref('Digitale dagstart', 'brebo_day_start', 'Vrijgegeven dagstart waarop de bevestiging betrekking heeft.', 1, TRUE),
      'field_brebo_ack_contact' => $ref('Persoon', 'brebo_contact', 'Eigen of ingehuurde medewerker die de briefing ontvangt.', 2),
      'field_brebo_ack_user' => $user_ref('Intern account', 'Optioneel intern BREBO-account van de ontvanger.', 3),
      'field_brebo_ack_version' => [
        'label' => 'Ontvangen versie', 'type' => 'integer', 'required' => TRUE, 'storage' => [],
        'description' => 'Exact dagstartversienummer dat aan de ontvanger is getoond.',
        'widget' => 'number', 'formatter' => 'number_integer', 'weight' => 4,
      ],
      'field_brebo_ack_at' => $date('Bevestigd op', 'Servervastgelegd ontvangst- en bevestigingsmoment.', 5, TRUE),
      'field_brebo_ack_understood' => [
        'label' => 'Instructie begrepen', 'type' => 'boolean', 'required' => FALSE, 'storage' => [],
        'description' => 'Ontvanger bevestigt dat de instructie is gelezen en begrepen.',
        'widget' => 'boolean_checkbox', 'formatter' => 'boolean', 'weight' => 6,
        'default_value' => [['value' => 0]],
      ],
      'field_brebo_ack_question' => $text('Vraag of onduidelijkheid', 'Openstaande vraag die vóór uitvoering moet worden beantwoord.', 7),
      'field_brebo_ack_status' => [
        'label' => 'Bevestigingsstatus', 'type' => 'string', 'required' => TRUE,
        'storage' => ['max_length' => 32], 'description' => 'Ontvangen, begrepen of vraag open.',
        'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 8,
        'default_value' => [['value' => 'Ontvangen']],
      ],
    ]);

  $permissions = [];
  foreach (['brebo_day_start', 'brebo_day_ack'] as $bundle) {
    $permissions = array_merge($permissions, [
      "create $bundle content", "edit own $bundle content",
      "edit any $bundle content", "view $bundle revisions",
    ]);
  }
  foreach (['brebo_projectleider', 'brebo_werkvoorbereider', 'brebo_uitvoerder', 'brebo_kwaliteitsmanager'] as $role_id) {
    if ($role = \Drupal\user\Entity\Role::load($role_id)) {
      foreach ($permissions as $permission) {
        $role->grantPermission($permission);
      }
      $role->save();
    }
  }

  return 'Digitale dagstart, gereedheidscontrole, versiebeheer, ontvangstbevestiging en rolrechten toegevoegd.';
}
