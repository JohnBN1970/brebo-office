<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Core\Http\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Read-only client for official PDOK address/BAG data.
 *
 * This service never creates or changes BREBO entities. It only resolves an
 * address candidate and returns source data that can be reviewed or used by a
 * separate materializer.
 */
final class BagPdokClient {

  private const LOCATION_SEARCH_URL = 'https://api.pdok.nl/kadaster/location-api/v1/search';
  private const BAG_BASE_URL = 'https://api.pdok.nl/kadaster/bag/ogc/v2';

  public function __construct(
    private readonly ClientFactory $httpClientFactory,
  ) {}

  /**
   * Finds official address candidates for free text.
   *
   * @return array<int, array<string, mixed>>
   */
  public function searchAddress(string $query, int $limit = 5): array {
    $query = trim($query);
    if (mb_strlen($query) < 3) {
      return [];
    }

    $client = $this->httpClientFactory->fromOptions([
      'timeout' => 10,
      'headers' => [
        'Accept' => 'application/geo+json, application/json',
        'User-Agent' => 'BREBO Office/1.0 (sboffice.brebobv.nl)',
      ],
    ]);

    try {
      $response = $client->get(self::LOCATION_SEARCH_URL, [
        'query' => [
          'q' => $query,
          'adres[version]' => 1,
          'adres[relevance]' => 1.0,
          'limit' => max(1, min(10, $limit)),
          'f' => 'geojson',
        ],
      ]);
      $payload = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (GuzzleException | \JsonException $exception) {
      throw new \RuntimeException('PDOK Location API lookup failed.', 0, $exception);
    }

    $result = [];
    foreach ($payload['features'] ?? [] as $feature) {
      if (!is_array($feature)) {
        continue;
      }
      $properties = is_array($feature['properties'] ?? NULL) ? $feature['properties'] : [];
      $displayName = trim((string) ($properties['display_name'] ?? $properties['weergavenaam'] ?? ''));
      if ($displayName === '') {
        continue;
      }

      $result[] = [
        'display_name' => $displayName,
        'feature_id' => (string) ($feature['id'] ?? $properties['id'] ?? ''),
        'properties' => $properties,
        'geometry' => is_array($feature['geometry'] ?? NULL) ? $feature['geometry'] : NULL,
        'source' => 'PDOK Location API',
        'retrieved_at' => gmdate(DATE_ATOM),
      ];
    }

    return $result;
  }

  /**
   * Loads one BAG feature when its OGC feature URL is known.
   *
   * Only URLs below the canonical BAG v2 API are accepted.
   *
   * @return array<string, mixed>|null
   */
  public function loadBagFeature(string $url): ?array {
    $url = trim($url);
    if ($url === '' || !str_starts_with($url, self::BAG_BASE_URL . '/collections/')) {
      return NULL;
    }

    $client = $this->httpClientFactory->fromOptions([
      'timeout' => 10,
      'headers' => [
        'Accept' => 'application/geo+json, application/json',
        'User-Agent' => 'BREBO Office/1.0 (sboffice.brebobv.nl)',
      ],
    ]);

    try {
      $response = $client->get($url, ['query' => ['f' => 'geojson']]);
      $payload = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (GuzzleException | \JsonException $exception) {
      throw new \RuntimeException('PDOK BAG feature lookup failed.', 0, $exception);
    }

    if (!is_array($payload)) {
      return NULL;
    }
    $payload['_brebo_source'] = 'PDOK BAG OGC API v2';
    $payload['_brebo_retrieved_at'] = gmdate(DATE_ATOM);
    return $payload;
  }

}
