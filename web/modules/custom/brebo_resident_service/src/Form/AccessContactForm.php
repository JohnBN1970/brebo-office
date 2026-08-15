<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AccessContactForm extends FormBase {

  public function __construct(private readonly Connection $database) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function getFormId(): string {
    return 'brebo_resident_service_access_contact_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, string $scope_type = 'building', int $scope_id = 0): array {
    if (!in_array($scope_type, ['project', 'building', 'technical_zone', 'residence'], TRUE) || $scope_id <= 0) {
      throw new NotFoundHttpException();
    }

    $buildingNid = NULL;
    $technicalZoneId = NULL;
    $residenceId = NULL;
    $projectId = NULL;
    if ($scope_type === 'project') {
      $projectId = $scope_id;
    }
    elseif ($scope_type === 'building') {
      $buildingNid = $scope_id;
      $projectId = $this->getRequest()->query->getInt('project_id') ?: NULL;
    }
    elseif ($scope_type === 'residence') {
      $residence = $this->database->select('brebo_residence', 'r')->fields('r', ['building_nid', 'project_id'])->condition('id', $scope_id)->execute()->fetchAssoc();
      if (!$residence) {
        throw new NotFoundHttpException();
      }
      $buildingNid = (int) $residence['building_nid'];
      $projectId = !empty($residence['project_id']) ? (int) $residence['project_id'] : NULL;
      $residenceId = $scope_id;
    }
    else {
      $buildingNid = $this->getRequest()->query->getInt('building_nid') ?: NULL;
      $projectId = $this->getRequest()->query->getInt('project_id') ?: NULL;
      $technicalZoneId = $scope_id;
      if (!$buildingNid) {
        throw new NotFoundHttpException();
      }
    }

    $form['scope_type'] = ['#type' => 'hidden', '#value' => $scope_type];
    $form['scope_id'] = ['#type' => 'hidden', '#value' => $scope_id];
    $form['building_nid'] = ['#type' => 'hidden', '#value' => $buildingNid];
    $form['technical_zone_id'] = ['#type' => 'hidden', '#value' => $technicalZoneId];
    $form['residence_id'] = ['#type' => 'hidden', '#value' => $residenceId];
    $form['project_id'] = ['#type' => 'number', '#title' => $this->t('Project-ID'), '#min' => 1, '#required' => $scope_type === 'project', '#default_value' => $projectId, '#disabled' => $scope_type === 'project'];
    $form['contact_name'] = ['#type' => 'textfield', '#title' => $this->t('Aanspreekpunt'), '#maxlength' => 255];
    $form['contact_role'] = ['#type' => 'textfield', '#title' => $this->t('Rol'), '#description' => $this->t('Bijvoorbeeld bewonersbegeleider, bewoner, huismeester, beheerder of opdrachtgever.')];
    $form['email'] = ['#type' => 'email', '#title' => $this->t('E-mail')];
    $form['phone'] = ['#type' => 'tel', '#title' => $this->t('Telefoon')];
    $form['preferred_channel'] = ['#type' => 'select', '#title' => $this->t('Voorkeurskanaal'), '#options' => ['unknown' => 'Onbekend', 'phone' => 'Telefoon', 'email' => 'E-mail', 'whatsapp' => 'WhatsApp', 'other' => 'Anders'], '#default_value' => 'unknown'];
    $form['contact_purpose'] = ['#type' => 'textfield', '#title' => $this->t('Waarvoor aanspreekpunt')];
    $form['access_required'] = ['#type' => 'checkbox', '#title' => $this->t('Toegang vereist')];
    $form['access_via'] = ['#type' => 'textfield', '#title' => $this->t('Toegang via')];
    $form['appointment_required'] = ['#type' => 'checkbox', '#title' => $this->t('Afspraak vereist')];
    $form['access_status'] = ['#type' => 'select', '#title' => $this->t('Toegangsstatus'), '#options' => [
      'unknown' => 'Onbekend', 'not_needed' => 'Niet nodig', 'to_arrange' => 'Nog regelen', 'appointment_made' => 'Afspraak gemaakt',
      'confirmed' => 'Bevestigd', 'granted' => 'Toegang verleend', 'key_available' => 'Sleutel beschikbaar', 'no_contact' => 'Geen contact', 'refused' => 'Geweigerd', 'blocked' => 'Geblokkeerd',
    ], '#default_value' => 'unknown'];
    $form['available_from'] = ['#type' => 'textfield', '#title' => $this->t('Beschikbaar vanaf'), '#placeholder' => '08:00'];
    $form['available_until'] = ['#type' => 'textfield', '#title' => $this->t('Beschikbaar tot'), '#placeholder' => '17:00'];
    $form['notes'] = ['#type' => 'textarea', '#title' => $this->t('Instructies / notities')];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Opslaan')];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $now = time();
    $this->database->insert('brebo_access_contact')->fields([
      'project_id' => $form_state->getValue('project_id') ?: NULL,
      'scope_type' => $form_state->getValue('scope_type'),
      'scope_id' => (int) $form_state->getValue('scope_id'),
      'building_nid' => $form_state->getValue('building_nid') ?: 0,
      'technical_zone_id' => $form_state->getValue('technical_zone_id') ?: NULL,
      'residence_id' => $form_state->getValue('residence_id') ?: NULL,
      'contact_name' => trim((string) $form_state->getValue('contact_name')) ?: NULL,
      'contact_role' => trim((string) $form_state->getValue('contact_role')) ?: NULL,
      'email' => trim((string) $form_state->getValue('email')) ?: NULL,
      'phone' => trim((string) $form_state->getValue('phone')) ?: NULL,
      'preferred_channel' => $form_state->getValue('preferred_channel'),
      'contact_purpose' => trim((string) $form_state->getValue('contact_purpose')) ?: NULL,
      'access_required' => (int) (bool) $form_state->getValue('access_required'),
      'access_via' => trim((string) $form_state->getValue('access_via')) ?: NULL,
      'appointment_required' => (int) (bool) $form_state->getValue('appointment_required'),
      'access_status' => $form_state->getValue('access_status'),
      'available_from' => trim((string) $form_state->getValue('available_from')) ?: NULL,
      'available_until' => trim((string) $form_state->getValue('available_until')) ?: NULL,
      'notes' => trim((string) $form_state->getValue('notes')) ?: NULL,
      'source' => 'manual',
      'created_by_uid' => (int) $this->currentUser()->id(),
      'created' => $now,
      'changed' => $now,
    ])->execute();
    $this->messenger()->addStatus($this->t('Aanspreekpunt en toegangsafspraak opgeslagen.'));

    if ($form_state->getValue('scope_type') === 'residence') {
      $form_state->setRedirect('brebo_resident_service.residence_detail', ['residence_id' => (int) $form_state->getValue('scope_id')]);
    }
    elseif ($form_state->getValue('building_nid')) {
      $form_state->setRedirect('brebo_resident_service.building_residents', ['node' => (int) $form_state->getValue('building_nid')]);
    }
    else {
      $form_state->setRedirect('<front>');
    }
  }
}
