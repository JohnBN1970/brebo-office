<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Creates a project-bound clock zone.
 */
final class ProjectClockZoneForm extends FormBase {

  private ?NodeInterface $project = NULL;

  public function __construct(
    private readonly EntityTypeManagerInterface $clockZoneEntityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  public function getFormId(): string {
    return 'brebo_inzet_project_clock_zone';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
    $this->project = $node;

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Naam kloklocatie'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#placeholder' => $this->t('Bijvoorbeeld Achterterrein, Blok B of Depot'),
    ];
    $form['latitude'] = [
      '#type' => 'number',
      '#title' => $this->t('Breedtegraad'),
      '#required' => TRUE,
      '#step' => '0.00000001',
      '#min' => -90,
      '#max' => 90,
      '#description' => $this->t('Voorlopig numeriek; in de volgende stap wordt dit door de versleepbare kaartpin ingevuld.'),
    ];
    $form['longitude'] = [
      '#type' => 'number',
      '#title' => $this->t('Lengtegraad'),
      '#required' => TRUE,
      '#step' => '0.00000001',
      '#min' => -180,
      '#max' => 180,
    ];
    $form['radius'] = [
      '#type' => 'number',
      '#title' => $this->t('Toegestane klokafstand'),
      '#field_suffix' => ' m',
      '#required' => TRUE,
      '#default_value' => 150,
      '#min' => 10,
      '#max' => 5000,
      '#step' => 5,
      '#description' => $this->t('Straal rondom het gekozen middelpunt waarbinnen normaal geklokt mag worden.'),
    ];
    $form['active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Kloklocatie actief'),
      '#default_value' => TRUE,
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Toelichting'),
      '#rows' => 3,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Kloklocatie opslaan'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Annuleren'),
      '#url' => \Drupal\Core\Url::fromRoute('brebo_inzet.project_clock_zones', ['node' => $node->id()]),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $radius = (float) $form_state->getValue('radius');
    if ($radius < 10 || $radius > 5000) {
      $form_state->setErrorByName('radius', $this->t('Kies een klokafstand tussen 10 en 5000 meter.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->project instanceof NodeInterface) {
      throw new \RuntimeException('Project context ontbreekt bij opslaan van kloklocatie.');
    }

    $zone = $this->clockZoneEntityTypeManager->getStorage('node')->create([
      'type' => 'brebo_clock_zone',
      'title' => trim((string) $form_state->getValue('name')),
      'field_brebo_project_ref' => ['target_id' => (int) $this->project->id()],
      'field_brebo_zone_latitude' => (float) $form_state->getValue('latitude'),
      'field_brebo_zone_longitude' => (float) $form_state->getValue('longitude'),
      'field_brebo_zone_radius' => (float) $form_state->getValue('radius'),
      'field_brebo_zone_active' => (bool) $form_state->getValue('active'),
      'field_brebo_description' => (string) $form_state->getValue('description'),
      'status' => 1,
    ]);
    $zone->save();

    $this->messenger()->addStatus($this->t('Kloklocatie @name opgeslagen.', ['@name' => $zone->label()]));
    $form_state->setRedirect('brebo_inzet.project_clock_zones', ['node' => $this->project->id()]);
  }

}
