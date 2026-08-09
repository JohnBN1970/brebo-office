<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Finds and stores an authoritative PDOK location for a building.
 */
final class BuildingLocationForm extends FormBase {

  private const PDOK_FREE_URL = 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free';

  private ?NodeInterface $building = NULL;

  public function getFormId(): string {
    return 'brebo_office_building_location';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_building') {
      throw new NotFoundHttpException();
    }
    $this->building = $node;

    $default_query = implode(' ', array_filter([
      (string) $node->get('field_brebo_address')->value,
      (string) $node->get('field_brebo_postal_code')->value,
      (string) $node->get('field_brebo_city')->value,
    ]));

    $form['explanation'] = [
      '#markup' => '<p>' . $this->t('Zoek het officiële Nederlandse BAG-adres via PDOK en kies bewust de juiste locatie. De gekozen coördinaten worden in het permanente gebouxdossier opgeslagen.') . '</p>',
    ];
    $form['query'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Adres zoeken'),
      '#default_value' => $form_state->getValue('query') ?: $default_query,
      '#required' => TRUE,
      '#description' => $this->t('Gebruik bij voorkeur straat, huisnummer, postcode en plaats.'),
      '#maxlength' => 255,
    ];
    $form['search'] = [
      '#type' => 'submit',
      '#value' => $this->t('Zoeken in BAG'),
      '#submit' => ['::searchLocations'],
      '#limit_validation_errors' => [['query']],
    ];

    $results = $form_state->get('pdok_results') ?: [];
    if ($results) {
      $form['results'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-location-results']],
        'heading' => [
          '#markup' => '<h2>' . $this->t('Kies de juiste locatie') . '</h2>',
        ],
      ];
      foreach ($results as $id => $result) {
        $form['results']['result_' . md5((string) $id)] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['brebo-location-result']],
          'label' => [
            '#markup' => '<strong>' . htmlspecialchars((string) $result['label'], ENT_QUOTES, 'UTF-8') . '</strong>',
          ],
          'save' => [
            '#type' => 'submit',
            '#value' => $this->t('Deze locatie gebruiken'),
            '#button_type' => 'primary',
            '#location' => $result,
            '#building_id' => (int) $node->id(),
            '#submit' => ['::saveLocation'],
            '#limit_validation_errors' => [],
          ],
        ];
      }
    }

    $form['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Annuleren'),
      '#url' => \Drupal\Core\Url::fromRoute('brebo_office_core.building_dashboard', ['node' => $node->id()]),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function searchLocations(array &$form, FormStateInterface $form_state): void {
    $query = trim((string) $form_state->getValue('query'));
    try {
      $response = \Drupal::httpClient()->request('GET', self::PDOK_FREE_URL, [
        'query' => [
          'q' => $query,
          'rows' => 10,
          'fq' => 'bron:BAG',
          'fl' => 'id,weergavenaam,type,centroide_ll,postcode,woonplaatsnaam',
        ],
        'headers' => [
          'Accept' => 'application/json',
          'User-Agent' => 'BREBO Office/1.0 (sboffice.brebobv.nl)',
        ],
        'timeout' => 10,
      ]);
      $payload = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (GuzzleException | \JsonException $exception) {
      $this->messenger()->addError($this->t('PDOK kon nu niet worden geraadpleegd. Probeer het later opnieuw.'));
      \Drupal::logger('brebo_office_core')->error('PDOK-geocoding mislukt: @message', ['@message' => $exception->getMessage()]);
      return;
    }

    $results = [];
    foreach ($payload['response']['docs'] ?? [] as $document) {
      $coordinates = $this->parsePoint((string) ($document['centroide_ll'] ?? ''));
      if (!$coordinates || empty($document['id'])) {
        continue;
      }
      $results[(string) $document['id']] = [
        'label' => (string) ($document['weergavenaam'] ?? $document['id']),
        'longitude' => $coordinates[0],
        'latitude' => $coordinates[1],
      ];
    }

    if (!$results) {
      $this->messenger()->addWarning($this->t('Geen bruikbare BAG-locatie gevonden. Voeg een huisnummer of postcode toe en zoek opnieuw.'));
    }
    $form_state->set('pdok_results', $results);
    $form_state->setRebuild(TRUE);
  }

  public function saveLocation(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $location = $trigger['#location'] ?? NULL;
    $building_id = (int) ($trigger['#building_id'] ?? 0);
    $building = $building_id
      ? \Drupal::entityTypeManager()->getStorage('node')->load($building_id)
      : NULL;
    if (!$building instanceof NodeInterface || $building->bundle() !== 'brebo_building' || !is_array($location)) {
      $this->messenger()->addError($this->t('De gekozen locatie kon niet worden verwerkt. Zoek opnieuw.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $building->set('field_brebo_longitude', $location['longitude']);
    $building->set('field_brebo_latitude', $location['latitude']);
    $building->setNewRevision(TRUE);
    $building->setRevisionLogMessage('Gebouwlocatie bepaald via PDOK Locatieserver: ' . $location['label']);
    $building->save();

    $this->messenger()->addStatus($this->t('Locatie opgeslagen: @location', ['@location' => $location['label']]));
    $form_state->setRedirect('brebo_office_core.building_dashboard', ['node' => $building->id()]);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Button-specific submit handlers perform the action.
  }

  /**
   * Parses a WKT POINT(longitude latitude) value.
   *
   * @return array{0: string, 1: string}|null
   */
  private function parsePoint(string $point): ?array {
    if (!preg_match('/^POINT\\s*\\(\\s*(-?\\d+(?:\\.\\d+)?)\\s+(-?\\d+(?:\\.\\d+)?)\\s*\\)$/i', $point, $matches)) {
      return NULL;
    }
    return [$matches[1], $matches[2]];
  }

}
