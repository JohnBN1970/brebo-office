<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Manages visible BREBO Office policy and operating defaults.
 */
final class OfficeSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['brebo_office_core.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'brebo_office_core_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('brebo_office_core.settings');

    $form['intro'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['messages', 'messages--status']],
      'text' => [
        '#markup' => $this->t('<strong>BREBO blijft de baas.</strong> Deze instellingen bepalen de zichtbare bedrijfsregels waarbinnen BREBO Office en AI mogen handelen. Wachtwoorden en API-sleutels worden hier nooit opgeslagen.'),
      ],
    ];

    $form['organization'] = [
      '#type' => 'details',
      '#title' => $this->t('Bedrijfsidentiteit'),
      '#open' => TRUE,
    ];
    $form['organization']['trade_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Handelsnaam'),
      '#default_value' => $config->get('organization.trade_name') ?? 'BREBO',
      '#required' => TRUE,
    ];
    $form['organization']['legal_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Statutaire naam'),
      '#default_value' => $config->get('organization.legal_name') ?? 'BREBO Bouw en Advies B.V.',
    ];
    $form['organization']['registration_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('KVK-nummer'),
      '#default_value' => $config->get('organization.registration_number') ?? '',
    ];
    $form['organization']['general_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Algemeen e-mailadres'),
      '#default_value' => $config->get('organization.general_email') ?? '',
    ];
    $form['organization']['general_phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Algemeen telefoonnummer'),
      '#default_value' => $config->get('organization.general_phone') ?? '',
    ];
    $form['organization']['address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vestigingsadres'),
      '#default_value' => $config->get('organization.address') ?? '',
    ];

    $form['project'] = [
      '#type' => 'details',
      '#title' => $this->t('Project- en communicatiestandaarden'),
      '#open' => TRUE,
    ];
    $form['project']['default_project_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Standaardstatus nieuw project'),
      '#options' => [
        'Concept' => $this->t('Concept'),
        'Intake' => $this->t('Intake'),
        'Voorbereiding' => $this->t('Voorbereiding'),
      ],
      '#default_value' => $config->get('project.default_project_status') ?? 'Concept',
    ];
    $form['project']['response_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Standaard reactietermijn'),
      '#field_suffix' => $this->t('kalenderdagen'),
      '#default_value' => $config->get('project.response_days') ?? 5,
      '#min' => 1,
      '#max' => 90,
      '#required' => TRUE,
    ];
    $form['project']['warning_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Oranje waarschuwing vóór termijn'),
      '#field_suffix' => $this->t('dagen'),
      '#default_value' => $config->get('project.warning_days') ?? 2,
      '#min' => 0,
      '#max' => 30,
      '#required' => TRUE,
    ];
    $form['project']['project_code_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Standaard projectcodeprefix'),
      '#default_value' => $config->get('project.project_code_prefix') ?? 'BREBO',
      '#maxlength' => 16,
    ];
    $form['project']['timezone'] = [
      '#type' => 'select',
      '#title' => $this->t('Bedrijfstijdzone'),
      '#options' => [
        'Europe/Amsterdam' => 'Europe/Amsterdam',
        'Europe/Brussels' => 'Europe/Brussels',
      ],
      '#default_value' => $config->get('project.timezone') ?? 'Europe/Amsterdam',
    ];

    $form['finance'] = [
      '#type' => 'details',
      '#title' => $this->t('Financiële beleidsgrenzen'),
    ];
    $form['finance']['default_margin_percent'] = [
      '#type' => 'number',
      '#title' => $this->t('Standaard beoogde marge'),
      '#field_suffix' => '%',
      '#default_value' => $config->get('finance.default_margin_percent') ?? 30,
      '#min' => 0,
      '#max' => 100,
      '#step' => 0.01,
    ];
    $form['finance']['vat_high_percent'] = [
      '#type' => 'number',
      '#title' => $this->t('Btw hoog'),
      '#field_suffix' => '%',
      '#default_value' => $config->get('finance.vat_high_percent') ?? 21,
      '#min' => 0,
      '#max' => 100,
      '#step' => 0.01,
    ];
    $form['finance']['vat_low_percent'] = [
      '#type' => 'number',
      '#title' => $this->t('Btw laag'),
      '#field_suffix' => '%',
      '#default_value' => $config->get('finance.vat_low_percent') ?? 9,
      '#min' => 0,
      '#max' => 100,
      '#step' => 0.01,
    ];
    $form['finance']['management_warning_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Directiesignaal vanaf financiële impact'),
      '#field_prefix' => '€ ',
      '#default_value' => $config->get('finance.management_warning_amount') ?? 5000,
      '#min' => 0,
      '#step' => 100,
    ];

    $form['mail'] = [
      '#type' => 'details',
      '#title' => $this->t('Mailinrichting'),
    ];
    $form['mail']['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maildomein'),
      '#default_value' => $config->get('mail.domain') ?? 'brebobv.nl',
    ];
    $form['mail']['sender_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Standaard afzendernaam'),
      '#default_value' => $config->get('mail.sender_name') ?? 'BREBO Office',
    ];
    $form['mail']['sender_address'] = [
      '#type' => 'email',
      '#title' => $this->t('Standaard afzenderadres'),
      '#default_value' => $config->get('mail.sender_address') ?? '',
    ];
    $form['mail']['project_inbox'] = [
      '#type' => 'email',
      '#title' => $this->t('Centrale projectinbox'),
      '#default_value' => $config->get('mail.project_inbox') ?? '',
    ];
    $form['mail']['migration_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Migratiestatus'),
      '#options' => [
        'zoho_active' => $this->t('Zoho actief'),
        'transition' => $this->t('Parallelle overgang'),
        'brebo_active' => $this->t('BREBO-mail actief'),
      ],
      '#default_value' => $config->get('mail.migration_status') ?? 'zoho_active',
    ];
    $form['mail']['automatic_dossier_linking'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Inkomende projectmail automatisch voor dossierkoppeling aanbieden'),
      '#default_value' => $config->get('mail.automatic_dossier_linking') ?? TRUE,
    ];

    $form['ai'] = [
      '#type' => 'details',
      '#title' => $this->t('AI-mandaat en menselijke controle'),
      '#open' => TRUE,
    ];
    $form['ai']['operating_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Standaard AI-werkwijze'),
      '#options' => [
        'proposal_only' => $this->t('Alleen voorstellen'),
        'within_mandate' => $this->t('Zelfstandig binnen vastgesteld mandaat'),
      ],
      '#default_value' => $config->get('ai.operating_mode') ?? 'within_mandate',
    ];
    $form['ai']['financial_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximale financiële impact zonder voorafgaand akkoord'),
      '#field_prefix' => '€ ',
      '#default_value' => $config->get('ai.financial_limit') ?? 0,
      '#min' => 0,
      '#step' => 100,
      '#description' => $this->t('0 betekent dat iedere financieel bindende handeling vooraf akkoord vereist.'),
    ];
    $form['ai']['create_internal_actions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Interne acties automatisch laten aanmaken'),
      '#default_value' => $config->get('ai.create_internal_actions') ?? TRUE,
    ];
    $form['ai']['send_external_messages'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Externe berichten zelfstandig laten verzenden'),
      '#default_value' => $config->get('ai.send_external_messages') ?? FALSE,
      '#description' => $this->t('Standaard uit: externe communicatie blijft concept totdat een bevoegde gebruiker akkoord geeft.'),
    ];
    $form['ai']['change_canonical_rules'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Canonieke bedrijfsregels zelfstandig laten wijzigen'),
      '#default_value' => $config->get('ai.change_canonical_rules') ?? FALSE,
      '#disabled' => TRUE,
      '#description' => $this->t('Altijd uit. Vastgestelde bedrijfsregels vereisen expliciet menselijk besluit.'),
    ];
    $form['ai']['audit_retention_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Bewaartermijn AI-audittrail'),
      '#field_suffix' => $this->t('dagen'),
      '#default_value' => $config->get('ai.audit_retention_days') ?? 2555,
      '#min' => 365,
      '#max' => 3650,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ((int) $form_state->getValue('warning_days') >= (int) $form_state->getValue('response_days')) {
      $form_state->setErrorByName('warning_days', $this->t('De waarschuwing moet vóór het einde van de reactietermijn vallen.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('brebo_office_core.settings');
    foreach ([
      'organization' => ['trade_name', 'legal_name', 'registration_number', 'general_email', 'general_phone', 'address'],
      'project' => ['default_project_status', 'response_days', 'warning_days', 'project_code_prefix', 'timezone'],
      'finance' => ['default_margin_percent', 'vat_high_percent', 'vat_low_percent', 'management_warning_amount'],
      'mail' => ['domain', 'sender_name', 'sender_address', 'project_inbox', 'migration_status', 'automatic_dossier_linking'],
      'ai' => ['operating_mode', 'financial_limit', 'create_internal_actions', 'send_external_messages', 'audit_retention_days'],
    ] as $section => $keys) {
      foreach ($keys as $key) {
        $config->set("$section.$key", $form_state->getValue($key));
      }
    }
    $config->set('ai.change_canonical_rules', FALSE)->save();

    parent::submitForm($form, $form_state);
  }

}
