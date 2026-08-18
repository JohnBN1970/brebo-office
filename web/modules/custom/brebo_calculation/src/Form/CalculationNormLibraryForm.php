<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Manage central BREBO calculation norms without direct database access. */
final class CalculationNormLibraryForm extends FormBase {

  public function __construct(private readonly Connection $database) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function getFormId(): string { return 'brebo_calculation_norm_library_form'; }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    if (!$this->database->schema()->tableExists('brebo_calculation_norm')) {
      $form['missing'] = ['#markup' => '<p>De normenbibliotheek is nog niet geïnstalleerd. Voer eerst de database-updates uit.</p>'];
      return $form;
    }

    $form['existing'] = ['#type' => 'table', '#header' => [
      $this->t('Domein'), $this->t('Norm'), $this->t('Omschrijving'), $this->t('Waarde'), $this->t('Eenheid'), $this->t('Voorwaarden'), $this->t('Prioriteit'), $this->t('Actief'), $this->t('Bron'),
    ], '#empty' => $this->t('Nog geen normen vastgelegd.')];
    $rows = $this->database->select('brebo_calculation_norm', 'n')->fields('n')->orderBy('domain')->orderBy('norm_key')->orderBy('priority', 'DESC')->execute();
    foreach ($rows as $row) {
      $form['existing'][$row->id] = [
        'domain' => ['#plain_text' => $row->domain], 'key' => ['#plain_text' => $row->norm_key], 'label' => ['#plain_text' => $row->label],
        'value' => ['#plain_text' => (string) $row->value], 'unit' => ['#plain_text' => (string) $row->unit],
        'conditions' => ['#plain_text' => $row->conditions_json ?: '{}'], 'priority' => ['#plain_text' => (string) $row->priority],
        'active' => ['#plain_text' => $row->active ? $this->t('Ja') : $this->t('Nee')], 'source' => ['#plain_text' => (string) $row->source],
      ];
    }

    $form['add'] = ['#type' => 'details', '#title' => $this->t('Norm toevoegen'), '#open' => TRUE];
    $form['add']['domain'] = ['#type' => 'textfield', '#title' => $this->t('Domein'), '#required' => TRUE, '#default_value' => 'glass', '#description' => $this->t('Bijvoorbeeld glass, painting, facade of roofing.')];
    $form['add']['norm_key'] = ['#type' => 'textfield', '#title' => $this->t('Normsleutel'), '#required' => TRUE, '#description' => $this->t('Bijvoorbeeld installation_hours_per_m2 of waste_pct.')];
    $form['add']['label'] = ['#type' => 'textfield', '#title' => $this->t('Omschrijving'), '#required' => TRUE];
    $form['add']['value'] = ['#type' => 'number', '#title' => $this->t('Waarde'), '#required' => TRUE, '#step' => 0.000001];
    $form['add']['unit'] = ['#type' => 'textfield', '#title' => $this->t('Eenheid'), '#size' => 16];
    $form['add']['conditions_json'] = ['#type' => 'textarea', '#title' => $this->t('Voorwaarden (JSON)'), '#default_value' => '{}', '#description' => $this->t('Voorbeeld: {"application_type":"fire_separation","weight_kg_min":50}. Leeg object betekent algemeen toepasbaar.')];
    $form['add']['priority'] = ['#type' => 'number', '#title' => $this->t('Prioriteit'), '#default_value' => 0];
    $form['add']['source'] = ['#type' => 'textfield', '#title' => $this->t('Bron'), '#description' => $this->t('Herkomst van de norm, bijvoorbeeld nacalculatie 2026, leverancier of BREBO praktijknorm.')];
    $form['add']['active'] = ['#type' => 'checkbox', '#title' => $this->t('Actief'), '#default_value' => TRUE];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Norm opslaan'), '#button_type' => 'primary'];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $json = trim((string) $form_state->getValue('conditions_json')) ?: '{}';
    $decoded = json_decode($json, TRUE);
    if (!is_array($decoded) || array_is_list($decoded)) $form_state->setErrorByName('conditions_json', $this->t('Voorwaarden moeten een geldig JSON-object zijn.'));
    foreach (['domain', 'norm_key'] as $field) {
      if (!preg_match('/^[a-z0-9_]+$/', (string) $form_state->getValue($field))) $form_state->setErrorByName($field, $this->t('Gebruik alleen kleine letters, cijfers en underscores.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->database->insert('brebo_calculation_norm')->fields([
      'domain' => trim((string) $form_state->getValue('domain')), 'norm_key' => trim((string) $form_state->getValue('norm_key')),
      'label' => trim((string) $form_state->getValue('label')), 'value' => (float) $form_state->getValue('value'),
      'unit' => trim((string) $form_state->getValue('unit')) ?: NULL, 'conditions_json' => trim((string) $form_state->getValue('conditions_json')) ?: '{}',
      'priority' => (int) $form_state->getValue('priority'), 'active' => $form_state->getValue('active') ? 1 : 0,
      'source' => trim((string) $form_state->getValue('source')) ?: NULL, 'changed' => time(),
    ])->execute();
    $this->messenger()->addStatus($this->t('Norm toegevoegd aan de centrale BREBO normenbibliotheek.'));
    $form_state->setRebuild();
  }

}
