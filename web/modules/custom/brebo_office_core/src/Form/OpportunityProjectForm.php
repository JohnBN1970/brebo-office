<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Converts one won opportunity into one traceable, administration-owned project. */
final class OpportunityProjectForm extends FormBase {

  private ?NodeInterface $opportunity = NULL;

  public function getFormId(): string {
    return 'brebo_opportunity_project_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_opportunity') {
      throw new NotFoundHttpException();
    }
    if (!$this->currentUser()->hasPermission('create brebo_project content')) {
      throw new AccessDeniedHttpException();
    }
    if ((string) $node->get('field_brebo_opp_stage')->value !== 'Gewonnen') {
      throw new AccessDeniedHttpException('Alleen een gewonnen kans kan worden omgezet naar een project.');
    }
    if (!$node->get('field_brebo_opp_project_ref')->isEmpty()) {
      throw new AccessDeniedHttpException('Deze kans is al omgezet naar een project.');
    }

    $this->opportunity = $node;
    $organization = $node->get('field_brebo_opp_org_ref')->entity;
    $contact = $node->get('field_brebo_opp_contact_ref')->entity;
    $calculation = $node->get('field_brebo_opp_calc_ref')->entity;
    $offer = $node->get('field_brebo_opp_offer_ref')->entity;

    $user = \Drupal::entityTypeManager()->getStorage('user')->load((int) $this->currentUser()->id());
    $availableAdministrations = $user instanceof UserInterface
      ? \Drupal::service('brebo_office_core.administration_access_manager')->availableAdministrations($user)
      : [];
    $administrationOptions = [];
    foreach ($availableAdministrations as $code => $administration) {
      $administrationOptions[(string) $code] = (string) ($administration['trade_name'] ?? $administration['legal_name'] ?? $code);
    }

    $handoverReady = $organization instanceof NodeInterface
      && $contact instanceof NodeInterface
      && $offer instanceof NodeInterface
      && $administrationOptions !== [];

    $location = '';
    if ($organization instanceof NodeInterface) {
      $storage = \Drupal::entityTypeManager()->getStorage('node');
      $locationIds = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_organization_location')
        ->condition('field_brebo_loc_org_ref.target_id', $organization->id())
        ->sort('field_brebo_loc_primary', 'DESC')
        ->range(0, 1)
        ->execute();
      $locationNode = $locationIds !== [] ? $storage->load(reset($locationIds)) : NULL;
      if ($locationNode instanceof NodeInterface) {
        $location = implode(', ', array_filter([
          (string) $locationNode->get('field_brebo_loc_address')->value,
          (string) $locationNode->get('field_brebo_loc_postal_code')->value,
          (string) $locationNode->get('field_brebo_loc_city')->value,
        ]));
      }
    }

    $form['warning'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['messages', 'messages--warning']],
      'text' => ['#markup' => '<strong>Gecontroleerde overdracht</strong><br>Deze actie maakt één project aan, legt de administratie vóór nummeruitgifte vast en behoudt de commerciële kans als bronhistorie.'],
    ];
    $form['checklist'] = [
      '#type' => 'table',
      '#header' => [$this->t('Overdrachtscontrole'), $this->t('Status')],
      '#rows' => [
        [$this->t('Organisatie gekoppeld'), $organization instanceof NodeInterface ? $this->t('Gereed') : $this->t('Ontbreekt')],
        [$this->t('Primaire contactpersoon gekoppeld'), $contact instanceof NodeInterface ? $this->t('Gereed') : $this->t('Ontbreekt')],
        [$this->t('Calculatie gekoppeld (optioneel)'), $calculation instanceof NodeInterface ? $this->t('Aanwezig') : $this->t('Niet van toepassing / niet gekoppeld')],
        [$this->t('Actuele offerteversie gekoppeld'), $offer instanceof NodeInterface ? $this->t('Gereed') : $this->t('Ontbreekt')],
        [$this->t('Vrijgegeven administratie beschikbaar'), $administrationOptions !== [] ? $this->t('Gereed') : $this->t('Ontbreekt')],
      ],
    ];
    if (!$handoverReady) {
      $form['blocked'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--error']],
        'text' => ['#markup' => $this->t('De overdracht is nog niet compleet of u heeft geen vrijgegeven administratie. Rond eerst de ontbrekende onderdelen of administratietoegang af.')],
      ];
    }

    $form['administration_code'] = [
      '#type' => 'select',
      '#title' => $this->t('Administratie'),
      '#options' => $administrationOptions,
      '#empty_option' => count($administrationOptions) > 1 ? $this->t('- Kies administratie -') : NULL,
      '#default_value' => count($administrationOptions) === 1 ? (string) array_key_first($administrationOptions) : NULL,
      '#required' => TRUE,
      '#description' => $this->t('Deze keuze bepaalt de projectnummerreeks en alle volgende administratiegebonden documenten.'),
    ];
    $form['project_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Projectnaam'),
      '#default_value' => $node->label(),
      '#maxlength' => 255,
      '#required' => TRUE,
    ];
    $form['project_code_info'] = [
      '#type' => 'item',
      '#title' => $this->t('Projectcode'),
      '#markup' => $this->t('Wordt automatisch en eenmalig uitgegeven uit de projectnummerreeks van de gekozen administratie.'),
    ];
    $form['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Projectlocatie'),
      '#default_value' => $location,
      '#maxlength' => 255,
      '#required' => TRUE,
    ];
    $kinds = ['Adviesproject', 'Inspectie/Onderzoek', 'MJOP', 'Projectmanagement', 'Planmatig onderhoud', 'Renovatie', 'Verduurzaming', 'Uitvoeringsproject', 'Hybride project'];
    $form['project_kind'] = [
      '#type' => 'select',
      '#title' => $this->t('Projectsoort'),
      '#options' => array_combine($kinds, $kinds),
      '#default_value' => 'Uitvoeringsproject',
      '#required' => TRUE,
    ];
    $form['confirm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ik bevestig dat deze gewonnen kans als project mag worden gestart binnen de gekozen administratie.'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Project aanmaken'),
      '#button_type' => 'primary',
      '#disabled' => !$handoverReady,
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($this->opportunity instanceof NodeInterface) {
      foreach ([
        'field_brebo_opp_org_ref' => $this->t('organisatie'),
        'field_brebo_opp_contact_ref' => $this->t('primaire contactpersoon'),
        'field_brebo_opp_offer_ref' => $this->t('actuele offerteversie'),
      ] as $field => $label) {
        if ($this->opportunity->get($field)->isEmpty()) {
          $form_state->setErrorByName('confirm', $this->t('De @item ontbreekt in de commerciële overdracht.', ['@item' => $label]));
        }
      }
    }

    $administrationCode = trim((string) $form_state->getValue('administration_code'));
    $user = \Drupal::entityTypeManager()->getStorage('user')->load((int) $this->currentUser()->id());
    if (!$user instanceof UserInterface || !\Drupal::service('brebo_office_core.administration_access_manager')->hasAccess($user, $administrationCode)) {
      $form_state->setErrorByName('administration_code', $this->t('U heeft geen vrijgegeven toegang tot deze administratie.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->opportunity instanceof NodeInterface) {
      return;
    }

    $administrationCode = trim((string) $form_state->getValue('administration_code'));
    $organization = $this->opportunity->get('field_brebo_opp_org_ref')->entity;
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $values = [
      'type' => 'brebo_project',
      'title' => trim((string) $form_state->getValue('project_name')),
      'field_brebo_project_code' => 'PENDING-OPP-' . $this->opportunity->id(),
      'field_brebo_client' => $organization instanceof NodeInterface ? $organization->label() : 'Onbekend',
      'field_brebo_location' => trim((string) $form_state->getValue('location')),
      'field_brebo_status' => 'Concept',
      'field_brebo_project_kind' => (string) $form_state->getValue('project_kind'),
      'field_brebo_project_opp_ref' => ['target_id' => $this->opportunity->id()],
      'status' => 1,
    ];
    if ($organization instanceof NodeInterface) {
      $values['field_brebo_client_org_ref'] = ['target_id' => $organization->id()];
    }

    $project = $storage->create($values);
    $contextResolver = \Drupal::service('brebo_office_core.administration_context_resolver');
    try {
      $project->save();
      $contextResolver->assignProject($project, $administrationCode);
      $receipt = \Drupal::service('brebo_office_core.project_document_number_issuer')->issueForNode(
        $project,
        'project',
        'project',
        (string) $project->id(),
        (int) gmdate('Y'),
      );
      $projectCode = (string) ($receipt['number'] ?? '');
      if ($projectCode === '') {
        throw new \RuntimeException('De centrale projectnummering gaf geen projectcode terug.');
      }
      $project->set('field_brebo_project_code', $projectCode);
      $project->setNewRevision(TRUE);
      $project->setRevisionLogMessage('Projectcode centraal uitgegeven voor administratie ' . $administrationCode . '.');
      $project->save();
      \Drupal::keyValue('brebo_office_core.project_number_receipts')->set((string) $project->id(), $receipt);
    }
    catch (\Throwable $exception) {
      if (!$project->isNew() && $project->id()) {
        $contextResolver->unassignProject($project);
        $project->delete();
      }
      throw $exception;
    }

    $this->opportunity->set('field_brebo_opp_project_ref', ['target_id' => $project->id()]);
    $this->opportunity->setNewRevision(TRUE);
    $this->opportunity->setRevisionLogMessage('Gewonnen kans gecontroleerd omgezet naar project ' . $project->id() . ' binnen administratie ' . $administrationCode . '.');
    $this->opportunity->save();

    $storage->create([
      'type' => 'brebo_opportunity_event',
      'title' => $this->opportunity->label() . ': project aangemaakt',
      'field_brebo_event_opp_ref' => ['target_id' => $this->opportunity->id()],
      'field_brebo_event_from_stage' => 'Gewonnen',
      'field_brebo_event_to_stage' => 'Gewonnen',
      'field_brebo_event_user' => ['target_id' => (int) $this->currentUser()->id()],
      'field_brebo_event_datetime' => gmdate('Y-m-d\\TH:i:s'),
      'field_brebo_event_note' => 'Project ' . $project->label() . ' (' . $project->get('field_brebo_project_code')->value . ') aangemaakt binnen administratie ' . $administrationCode . '.',
      'status' => 1,
    ])->save();

    $this->messenger()->addStatus($this->t('Project @project is aangemaakt met projectcode @code binnen administratie @administration.', [
      '@project' => $project->label(),
      '@code' => (string) $project->get('field_brebo_project_code')->value,
      '@administration' => $administrationCode,
    ]));
    $form_state->setRedirect('brebo_office_core.project_dashboard', ['node' => $project->id()]);
  }

}
