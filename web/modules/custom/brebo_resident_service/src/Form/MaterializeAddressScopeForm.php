<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\brebo_resident_service\Service\AddressScopeIntake;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class MaterializeAddressScopeForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly AddressScopeIntake $intake,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('brebo_resident_service.address_scope_intake'));
  }

  public function getFormId(): string {
    return 'brebo_resident_service_materialize_address_scope';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $intake_id = NULL): array {
    $row = $intake_id ? $this->database->select('brebo_address_scope_intake', 'i')->fields('i')->condition('id', $intake_id)->execute()->fetchAssoc() : FALSE;
    if (!$row) {
      $form['message'] = ['#markup' => '<p>Adresvoorstel niet gevonden.</p>'];
      return $form;
    }
    $form_state->set('intake_id', (int) $row['id']);
    $form['proposal'] = ['#markup' => '<p><strong>' . $this->t('Gevonden scope') . ':</strong> ' . htmlspecialchars((string) $row['matched_text'], ENT_QUOTES, 'UTF-8') . '<br><strong>' . $this->t('Officiële BAG-adressen') . ':</strong> ' . (int) $row['result_count'] . '</p>'];
    $form['building_nid'] = [
      '#type' => 'number', '#title' => $this->t('Gebouw node-ID'), '#required' => TRUE,
      '#default_value' => $row['building_nid'] ?: NULL, '#min' => 1,
      '#description' => $this->t('Canonieke brebo_building node waaraan de adressen en woningen worden gekoppeld.'),
    ];
    $form['project_id'] = [
      '#type' => 'number', '#title' => $this->t('Project node-ID'), '#required' => FALSE,
      '#default_value' => $row['project_id'] ?: NULL, '#min' => 1,
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Adressen en woningen toevoegen'), '#button_type' => 'primary'];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $buildingNid = (int) $form_state->getValue('building_nid');
    $node = $this->entityTypeManager()->getStorage('node')->load($buildingNid);
    if (!$node || $node->bundle() !== 'brebo_building') {
      $form_state->setErrorByName('building_nid', $this->t('Kies een geldige BREBO-gebouw node.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $count = $this->intake->materialize(
      (int) $form_state->get('intake_id'),
      (int) $form_state->getValue('building_nid'),
      $form_state->getValue('project_id') !== '' ? (int) $form_state->getValue('project_id') : NULL,
    );
    $this->messenger()->addStatus($this->formatPlural($count, '1 nieuwe woning/gebruiksobject toegevoegd.', '@count nieuwe woningen/gebruiksobjecten toegevoegd.'));
    $form_state->setRedirect('brebo_resident_service.address_scopes');
  }
}
