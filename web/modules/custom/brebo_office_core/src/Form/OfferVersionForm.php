<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Creates a version-bound commercial offer from a calculation.
 */
final class OfferVersionForm extends FormBase {

  private ?NodeInterface $calculation = NULL;

  public function getFormId(): string {
    return 'brebo_office_offer_version';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation') {
      throw new NotFoundHttpException();
    }
    if (!$node->access('update') || !$this->currentUser()->hasPermission('create brebo_offer_version content')) {
      throw new AccessDeniedHttpException();
    }

    $this->calculation = $node;
    $storage = $this->entityTypeManager()->getStorage('node');
    $existing_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_offer_version')
      ->condition('field_brebo_calculation_ref.target_id', $node->id())
      ->execute();
    $next_version = 1;
    foreach ($storage->loadMultiple($existing_ids) as $offer) {
      if ($offer instanceof NodeInterface) {
        $next_version = max($next_version, ((int) ($offer->get('field_brebo_offer_version')->value ?? 0)) + 1);
      }
    }

    $calculation_code = (string) ($node->get('field_brebo_calc_code')->value ?? ('CALC-' . $node->id()));
    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Maak een vaste commerciële offerteversie. Na opslaan blijven layout, teksten en fiscale instellingen gekoppeld aan deze versie.') . '</p>',
    ];
    $form['identity'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Offerteversie'),
    ];
    $form['identity']['offer_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Offertenummer'),
      '#required' => TRUE,
      '#default_value' => $calculation_code . '-OFF-' . str_pad((string) $next_version, 2, '0', STR_PAD_LEFT),
      '#maxlength' => 64,
    ];
    $form['identity']['offer_version'] = [
      '#type' => 'number',
      '#title' => $this->t('Versienummer'),
      '#required' => TRUE,
      '#default_value' => $next_version,
      '#min' => 1,
    ];
    $form['identity']['offer_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#required' => TRUE,
      '#options' => [
        'Concept' => $this->t('Concept'),
        'Vastgesteld' => $this->t('Vastgesteld'),
        'Verzonden' => $this->t('Verzonden'),
        'Geaccepteerd' => $this->t('Geaccepteerd'),
        'Afgewezen' => $this->t('Afgewezen'),
        'Vervallen' => $this->t('Vervallen'),
      ],
      '#default_value' => 'Concept',
    ];
    $form['identity']['valid_until'] = [
      '#type' => 'date',
      '#title' => $this->t('Geldig tot'),
    ];

    $form['presentation'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Presentatie'),
    ];
    $form['presentation']['offer_layout'] = [
      '#type' => 'select',
      '#title' => $this->t('Offertelayout'),
      '#required' => TRUE,
      '#options' => [
        'Zakelijk' => $this->t('Zakelijk'),
        'Compact' => $this->t('Compacte prijsopgave'),
        'Technisch' => $this->t('Uitgebreide technische aanbieding'),
        'Aanbesteding' => $this->t('Aanbestedings-/begrotingsstaat'),
        'VvE' => $this->t('VvE-/bewonersvriendelijk'),
        'Maatwerk' => $this->t('Maatwerk'),
      ],
      '#default_value' => 'Zakelijk',
    ];
    $form['presentation']['price_detail'] = [
      '#type' => 'select',
      '#title' => $this->t('Prijsdetailniveau'),
      '#required' => TRUE,
      '#options' => [
        'Gesloten' => $this->t('Gesloten aanbieding'),
        'Halfopen' => $this->t('Halfopen aanbieding'),
        'Open' => $this->t('Open begroting'),
        'Regie' => $this->t('Regie-/eenheidsprijzenstaat'),
        'Maatwerk' => $this->t('Maatwerk'),
      ],
      '#default_value' => 'Halfopen',
    ];

    $form['texts'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Commerciële teksten'),
    ];
    foreach ([
      'scope' => $this->t('Scope'),
      'exclusions' => $this->t('Uitsluitingen'),
      'work_terms' => $this->t('Voor het werk geldende voorwaarden'),
    ] as $key => $label) {
      $form['texts'][$key] = [
        '#type' => 'textarea',
        '#title' => $label,
        '#rows' => 6,
      ];
    }

    $form['tax'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Btw en G-rekening'),
    ];
    $form['tax']['vat_default'] = [
      '#type' => 'select',
      '#title' => $this->t('Standaard btw-behandeling'),
      '#required' => TRUE,
      '#options' => [
        'Belast' => $this->t('Btw belast'),
        'Verlegd' => $this->t('Btw verlegd'),
        'Vrijgesteld' => $this->t('Btw vrijgesteld'),
        'Niet van toepassing' => $this->t('Niet van toepassing'),
      ],
      '#default_value' => 'Belast',
    ];
    $form['tax']['g_account_on'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('G-rekening van toepassing'),
    ];
    $form['tax']['g_account_pct'] = [
      '#type' => 'number',
      '#title' => $this->t('G-rekeningpercentage'),
      '#min' => 0,
      '#max' => 100,
      '#step' => 0.01,
      '#states' => [
        'visible' => [':input[name="g_account_on"]' => ['checked' => TRUE]],
        'required' => [':input[name="g_account_on"]' => ['checked' => TRUE]],
      ],
    ];
    $form['tax']['g_account_base'] = [
      '#type' => 'select',
      '#title' => $this->t('G-rekeninggrondslag'),
      '#empty_option' => $this->t('- Selecteer -'),
      '#options' => [
        'Arbeid' => $this->t('Arbeid'),
        'Aanneemsom' => $this->t('Aanneemsom'),
        'Vaste grondslag' => $this->t('Overeengekomen vaste grondslag'),
      ],
      '#states' => [
        'visible' => [':input[name="g_account_on"]' => ['checked' => TRUE]],
        'required' => [':input[name="g_account_on"]' => ['checked' => TRUE]],
      ],
    ];
    $form['tax']['g_account_iban'] = [
      '#type' => 'textfield',
      '#title' => $this->t('G-rekeningnummer (IBAN)'),
      '#maxlength' => 64,
      '#states' => [
        'visible' => [':input[name="g_account_on"]' => ['checked' => TRUE]],
        'required' => [':input[name="g_account_on"]' => ['checked' => TRUE]],
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Offerteversie opslaan'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Annuleren'),
      '#url' => $node->toUrl('canonical')->setRouteParameter('node', $node->id()),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('g_account_on')) {
      $percentage = (float) $form_state->getValue('g_account_pct');
      if ($percentage <= 0 || $percentage > 100) {
        $form_state->setErrorByName('g_account_pct', $this->t('Vul een G-rekeningpercentage groter dan 0 en maximaal 100 in.'));
      }
      $iban = strtoupper(str_replace(' ', '', (string) $form_state->getValue('g_account_iban')));
      if (!preg_match('/^[A-Z]{2}[0-9A-Z]{13,32}$/', $iban)) {
        $form_state->setErrorByName('g_account_iban', $this->t('Vul een geldig IBAN-formaat in.'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $calculation = $this->calculation;
    if (!$calculation instanceof NodeInterface) {
      return;
    }

    $storage = $this->entityTypeManager()->getStorage('node');
    $offer_number = trim((string) $form_state->getValue('offer_number'));
    $version = (int) $form_state->getValue('offer_version');
    $duplicate = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_offer_version')
      ->condition('field_brebo_offer_number', $offer_number)
      ->condition('field_brebo_offer_version', $version)
      ->execute();
    if ($duplicate) {
      $form_state->setErrorByName('offer_number', $this->t('Deze combinatie van offertenummer en versienummer bestaat al.'));
      return;
    }

    $g_account_on = (bool) $form_state->getValue('g_account_on');
    $snapshot = json_encode([
      'calculation_id' => (int) $calculation->id(),
      'calculation_label' => (string) $calculation->label(),
      'calculation_version' => (string) ($calculation->get('field_brebo_calc_version')->value ?? ''),
      'calculation_changed' => (int) $calculation->getChangedTime(),
      'created_at' => gmdate(DATE_ATOM),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $offer = $storage->create([
      'type' => 'brebo_offer_version',
      'title' => $offer_number . ' — v' . $version,
      'field_brebo_calculation_ref' => ['target_id' => $calculation->id()],
      'field_brebo_offer_number' => $offer_number,
      'field_brebo_offer_version' => $version,
      'field_brebo_offer_status' => $form_state->getValue('offer_status'),
      'field_brebo_offer_layout' => $form_state->getValue('offer_layout'),
      'field_brebo_price_detail' => $form_state->getValue('price_detail'),
      'field_brebo_offer_scope' => $form_state->getValue('scope'),
      'field_brebo_exclusions' => $form_state->getValue('exclusions'),
      'field_brebo_work_terms' => $form_state->getValue('work_terms'),
      'field_brebo_valid_until' => $form_state->getValue('valid_until') ?: NULL,
      'field_brebo_vat_default' => $form_state->getValue('vat_default'),
      'field_brebo_g_account_on' => $g_account_on ? 1 : 0,
      'field_brebo_g_account_pct' => $g_account_on ? $form_state->getValue('g_account_pct') : '0.0000',
      'field_brebo_g_account_base' => $g_account_on ? $form_state->getValue('g_account_base') : NULL,
      'field_brebo_g_account_iban' => $g_account_on ? strtoupper(str_replace(' ', '', (string) $form_state->getValue('g_account_iban'))) : NULL,
      'field_brebo_offer_snapshot' => $snapshot ?: '',
      'status' => 1,
    ]);
    $offer->setNewRevision(TRUE);
    $offer->setRevisionLogMessage('Offerteversie gemaakt vanuit calculatie ' . $calculation->label() . '.');
    $offer->save();

    $this->messenger()->addStatus($this->t('Offerteversie @number v@version is opgeslagen.', [
      '@number' => $offer_number,
      '@version' => $version,
    ]));
    $form_state->setRedirect('entity.node.edit_form', ['node' => $offer->id()]);
  }

}
