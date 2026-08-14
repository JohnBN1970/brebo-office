<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Converts one won opportunity into one traceable project.
 */
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

    $location = '';
    if ($organization instanceof NodeInterface) {
      $storage = \Drupal::entityTypeManager()->getStorage('node');
      $location_ids = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_organization_location')
        ->condition('field_brebo_loc_org_ref.target_id', $organization->id())
        ->sort('field_brebo_loc_primary', 'DESC')
        ->range(0, 1)
        ->execute();
      $location_node = $location_ids !== [] ? $storage->load(reset($location_ids)) : NULL;
      if ($location_node instanceof NodeInterface) {
        $location = implode(', ', array_filter([
          (string) $location_node->get('field_brebo_loc_address')->value,
          (string) $location_node->get('field_brebo_loc_postal_code')->value,
          (string) $location_node->get('field_brebo_loc_city')->value,
        ]));
      }
    }

    $form['warning'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['messages', 'messages--warning']],
      'text' => ['#markup' => '<strong>Gecontroleerde overdracht</strong><br>Deze actie maakt één project aan en legt wederzijdse verwijzingen vast. De commerciële kans blijft als bronhistorie behouden.'],
    ];
    $form['project_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Projectnaam'),
      '#default_value' => $node->label(),
      '#maxlength' => 255,
      '#required' => TRUE,
    ];
    $form['project_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Projectcode'),
      '#default_value' => 'BREBO-' . date('Y') . '-' . $node->id(),
      '#maxlength' => 32,
      '#required' => TRUE,
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
      '#title' => $this->t('Ik bevestig dat deze gewonnen kans als project mag worden gestart.'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Project aanmaken'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $code = trim((string) $form_state->getValue('project_code'));
    $existing = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_project')
      ->condition('field_brebo_project_code', $code)
      ->range(0, 1)
      ->execute();
    if ($existing !== []) {
      $form_state->setErrorByName('project_code', $this->t('Deze projectcode bestaat al.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->opportunity instanceof NodeInterface) {
      return;
    }
    $organization = $this->opportunity->get('field_brebo_opp_org_ref')->entity;
    $values = [
      'type' => 'brebo_project',
      'title' => trim((string) $form_state->getValue('project_name')),
      'field_brebo_project_code' => trim((string) $form_state->getValue('project_code')),
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

    $project = \Drupal::entityTypeManager()->getStorage('node')->create($values);
    $project->save();

    $this->opportunity->set('field_brebo_opp_project_ref', ['target_id' => $project->id()]);
    $this->opportunity->setNewRevision(TRUE);
    $this->opportunity->setRevisionLogMessage('Gewonnen kans gecontroleerd omgezet naar project ' . $project->id() . '.');
    $this->opportunity->save();

    \Drupal::entityTypeManager()->getStorage('node')->create([
      'type' => 'brebo_opportunity_event',
      'title' => $this->opportunity->label() . ': project aangemaakt',
      'field_brebo_event_opp_ref' => ['target_id' => $this->opportunity->id()],
      'field_brebo_event_from_stage' => 'Gewonnen',
      'field_brebo_event_to_stage' => 'Gewonnen',
      'field_brebo_event_user' => ['target_id' => (int) $this->currentUser()->id()],
      'field_brebo_event_datetime' => gmdate('Y-m-d\\TH:i:s'),
      'field_brebo_event_note' => 'Project ' . $project->label() . ' (' . $project->id() . ') aangemaakt.',
      'status' => 1,
    ])->save();

    $this->messenger()->addStatus($this->t('Project @project is aangemaakt en gekoppeld aan de gewonnen kans.', ['@project' => $project->label()]));
    $form_state->setRedirect('brebo_office_core.project_dashboard', ['node' => $project->id()]);
  }

}
