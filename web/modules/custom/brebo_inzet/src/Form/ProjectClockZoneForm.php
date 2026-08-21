<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Form;

use Drupal\brebo_building_data\Service\ProjectBuildingRepository;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Creates or edits a project-bound clock zone.
 */
final class ProjectClockZoneForm extends FormBase {

  private ?NodeInterface $project = NULL;
  private ?NodeInterface $zone = NULL;

  public function __construct(
    protected EntityTypeManagerInterface $clockZoneEntityTypeManager,
    protected ProjectBuildingRepository $projectBuildingRepository,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('brebo_building_data.project_building_repository'),
    );
  }

  public function getFormId(): string {
    return 'brebo_inzet_project_clock_zone';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, ?NodeInterface $zone = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
    if ($zone !== NULL) {
      if ($zone->bundle() !== 'brebo_clock_zone' || (int) $zone->get('field_brebo_project_ref')->target_id !== (int) $node->id()) {
        throw new NotFoundHttpException();
      }
      $this->zone = $zone;
    }
    $this->project = $node;
    $form['#attached']['library'][] = 'brebo_inzet/clock-zone-map';

    $building = $this->projectBuilding($node);
    $buildingCoordinates = $this->buildingCoordinates($building);
    [$projectLatitude, $projectLongitude] = $buildingCoordinates ?? ['52.37021600', '4.89516800'];
    $defaultLatitude = $zone?->get('field_brebo_zone_latitude')->value ?? $projectLatitude;
    $defaultLongitude = $zone?->get('field_brebo_zone_longitude')->value ?? $projectLongitude;
    $defaultRadius = $zone?->get('field_brebo_zone_radius')->value ?? 150;
    $mapUrl = $this->pdokMapUrl((float) $defaultLatitude, (float) $defaultLongitude);

    $projectLabel = htmlspecialchars((string) $node->label(), ENT_QUOTES, 'UTF-8');
    $buildingLabel = $building instanceof NodeInterface ? htmlspecialchars((string) $building->label(), ENT_QUOTES, 'UTF-8') : (string) $this->t('Geen eenduidig gebouw gekoppeld');
    $address = $building instanceof NodeInterface ? $this->buildingAddress($building) : '';
    $addressMarkup = $address !== '' ? '<div class="brebo-clock-zone-context__address">' . htmlspecialchars($address, ENT_QUOTES, 'UTF-8') . '</div>' : '';

    $form['context'] = [
      '#markup' => '<section class="brebo-clock-zone-context"><div><span class="brebo-clock-zone-context__label">' . $this->t('Project') . '</span><strong>' . $projectLabel . '</strong></div><div><span class="brebo-clock-zone-context__label">' . $this->t('Gebouw') . '</span><strong>' . $buildingLabel . '</strong>' . $addressMarkup . '</div></section>',
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Naam kloklocatie'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $zone?->label() ?? '',
      '#placeholder' => $this->t('Bijvoorbeeld Achterterrein, Blok B of Depot'),
    ];
    $mapAttributes = ['class' => ['brebo-clock-zone-map'], 'data-brebo-clock-zone-map' => 'true'];
    if ($buildingCoordinates !== NULL) {
      $mapAttributes['data-building-latitude'] = $buildingCoordinates[0];
      $mapAttributes['data-building-longitude'] = $buildingCoordinates[1];
    }
    $form['map'] = [
      '#type' => 'container',
      '#attributes' => $mapAttributes,
      'canvas' => [
        '#markup' => '<div class="brebo-clock-zone-map__canvas"><img class="brebo-clock-zone-map__image" src="' . htmlspecialchars($mapUrl, ENT_QUOTES, 'UTF-8') . '" alt="PDOK luchtfoto rond de kloklocatie"><span class="brebo-clock-zone-map__circle" aria-hidden="true"></span><button type="button" class="brebo-clock-zone-map__marker" aria-label="Versleep middelpunt kloklocatie"></button></div>',
      ],
      'help' => [
        '#markup' => '<p class="brebo-clock-zone-map__help">' . $this->t('Versleep de kaart of de pin en zoom in tot het juiste pand zichtbaar is. Straatnamen en huisnummering kunnen boven de luchtfoto worden getoond. Huidige klokzone: <span class="brebo-clock-zone-map__readout">@radius m</span>.', ['@radius' => (string) round((float) $defaultRadius)]) . '</p>',
      ],
    ];
    $form['radius'] = [
      '#type' => 'number', '#title' => $this->t('Klokzone'), '#field_suffix' => ' m',
      '#required' => TRUE, '#default_value' => $defaultRadius, '#min' => 10, '#max' => 5000, '#step' => 5,
      '#description' => $this->t('Afstand rondom het gekozen middelpunt waarbinnen normaal aanwezig of vertrek mag worden geregistreerd.'),
    ];
    $form['active'] = [
      '#type' => 'checkbox', '#title' => $this->t('Kloklocatie actief'),
      '#default_value' => $zone ? (bool) $zone->get('field_brebo_zone_active')->value : TRUE,
    ];
    $form['description'] = [
      '#type' => 'textarea', '#title' => $this->t('Toelichting'), '#rows' => 3,
      '#default_value' => $zone ? (string) $zone->get('field_brebo_description')->value : '',
    ];
    $form['technical'] = [
      '#type' => 'details',
      '#title' => $this->t('Technische gegevens'),
      '#open' => FALSE,
      '#attributes' => ['class' => ['brebo-clock-zone-technical']],
    ];
    $form['technical']['latitude'] = [
      '#type' => 'number', '#title' => $this->t('Breedtegraad'), '#required' => TRUE,
      '#step' => '0.00000001', '#min' => -90, '#max' => 90, '#default_value' => $defaultLatitude,
      '#description' => $this->t('Wordt automatisch bijgewerkt via de kaart.'),
    ];
    $form['technical']['longitude'] = [
      '#type' => 'number', '#title' => $this->t('Lengtegraad'), '#required' => TRUE,
      '#step' => '0.00000001', '#min' => -180, '#max' => 180, '#default_value' => $defaultLongitude,
      '#description' => $this->t('Wordt automatisch bijgewerkt via de kaart.'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Kloklocatie opslaan'), '#button_type' => 'primary'];
    $form['actions']['cancel'] = [
      '#type' => 'link', '#title' => $this->t('Annuleren'),
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

    $values = [
      'title' => trim((string) $form_state->getValue('name')),
      'field_brebo_project_ref' => ['target_id' => (int) $this->project->id()],
      'field_brebo_zone_latitude' => (float) $form_state->getValue('latitude'),
      'field_brebo_zone_longitude' => (float) $form_state->getValue('longitude'),
      'field_brebo_zone_radius' => (float) $form_state->getValue('radius'),
      'field_brebo_zone_active' => (bool) $form_state->getValue('active'),
      'field_brebo_description' => (string) $form_state->getValue('description'),
      'status' => 1,
    ];

    if ($this->zone instanceof NodeInterface) {
      foreach ($values as $field => $value) {
        $this->zone->set($field, $value);
      }
      $this->zone->setNewRevision(TRUE);
      $this->zone->setRevisionLogMessage('Kloklocatie visueel bijgewerkt via BREBO Inzet.');
      $this->zone->save();
      $savedZone = $this->zone;
    }
    else {
      $savedZone = $this->clockZoneEntityTypeManager->getStorage('node')->create(['type' => 'brebo_clock_zone'] + $values);
      $savedZone->save();
    }

    $this->messenger()->addStatus($this->t('Kloklocatie @name opgeslagen.', ['@name' => $savedZone->label()]));
    $form_state->setRedirect('brebo_inzet.project_clock_zones', ['node' => $this->project->id()]);
  }

  private function projectBuilding(NodeInterface $project): ?NodeInterface {
    $storage = $this->clockZoneEntityTypeManager->getStorage('node');
    foreach ($this->projectBuildingRepository->buildingsForProject((int) $project->id()) as $relation) {
      $buildingId = (int) ($relation['building_nid'] ?? 0);
      $building = $buildingId > 0 ? $storage->load($buildingId) : NULL;
      if ($building instanceof NodeInterface && $building->bundle() === 'brebo_building' && $this->buildingCoordinates($building) !== NULL) {
        return $building;
      }
    }

    if ($project->hasField('field_brebo_building_refs') && !$project->get('field_brebo_building_refs')->isEmpty()) {
      $buildings = array_values(array_filter(
        $project->get('field_brebo_building_refs')->referencedEntities(),
        static fn ($building): bool => $building instanceof NodeInterface && $building->bundle() === 'brebo_building',
      ));
      if (count($buildings) === 1 && $this->buildingCoordinates($buildings[0]) !== NULL) {
        return $buildings[0];
      }
    }
    return NULL;
  }

  /** @return array{0: string, 1: string}|null */
  private function buildingCoordinates(mixed $building): ?array {
    if (!$building instanceof NodeInterface || $building->bundle() !== 'brebo_building') {
      return NULL;
    }
    $latitude = $building->hasField('field_brebo_latitude') ? trim((string) $building->get('field_brebo_latitude')->value) : '';
    $longitude = $building->hasField('field_brebo_longitude') ? trim((string) $building->get('field_brebo_longitude')->value) : '';
    return is_numeric($latitude) && is_numeric($longitude) ? [$latitude, $longitude] : NULL;
  }

  private function buildingAddress(NodeInterface $building): string {
    $parts = [];
    foreach (['field_brebo_address', 'field_brebo_postal_code', 'field_brebo_city'] as $field) {
      if ($building->hasField($field)) {
        $value = trim((string) $building->get($field)->value);
        if ($value !== '') {
          $parts[] = $value;
        }
      }
    }
    return implode(', ', $parts);
  }

  private function pdokMapUrl(float $latitude, float $longitude): string {
    $earthRadius = 6378137.0;
    $latRadians = max(min(deg2rad($latitude), 1.4844222297453324), -1.4844222297453324);
    $centerX = $earthRadius * deg2rad($longitude);
    $centerY = $earthRadius * log(tan(M_PI / 4 + $latRadians / 2));
    $halfHeightMetres = 350.0;
    $halfWidthMetres = $halfHeightMetres;
    $bbox = implode(',', [
      $centerX - $halfWidthMetres,
      $centerY - $halfHeightMetres,
      $centerX + $halfWidthMetres,
      $centerY + $halfHeightMetres,
    ]);
    $query = http_build_query([
      'service' => 'WMS',
      'version' => '1.3.0',
      'request' => 'GetMap',
      'layers' => 'Actueel_orthoHR',
      'styles' => '',
      'crs' => 'EPSG:3857',
      'bbox' => $bbox,
      'width' => '900',
      'height' => '900',
      'format' => 'image/jpeg',
      'transparent' => 'false',
    ], '', '&', PHP_QUERY_RFC3986);
    return 'https://service.pdok.nl/hwh/luchtfotorgb/wms/v1_0?' . $query;
  }

}
