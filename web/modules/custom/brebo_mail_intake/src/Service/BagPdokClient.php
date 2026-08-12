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
      $links = is_array($feature['links'] ?? NULL) ? $feature['links'] : [];
      $featureId = trim((string) ($feature['id'] ?? $properties['id'] ?? ''));
      $href = $this->firstHref($links, $properties);
      $displayName = $this->displayName($properties, $featureId, $href);

      // Location API search intentionally returns compact features. Do not
      // discard a valid candidate merely because the source uses highlight/
      // href instead of a traditional display_name property.
      if ($featureId === '' && $href === '' && $displayName === '') {
        continue;
      }

      $result[] = [
        'display_name' => $displayName,
        'feature_id' => $featureId,
        'href' => $href,
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

  /**
   * @param array<string, mixed> $properties
   */
  private function displayName(array $properties, string $featureId, string $href): string {
    foreach (['display_name', 'weergavenaam', 'name', 'title', 'label', 'highlight', 'text'] as $key) {
      if (!array_key_exists($key, $properties)) {
        continue;
      }
      $value = $properties[$key];
      if (is_scalar($value)) {
        $label = trim(strip_tags((string) $value));
        if ($label !== '') {
          return $label;
        }
      }
      elseif (is_array($value)) {
        $parts = [];
        array_walk_recursive($value, static function ($item) use (&$parts): void {
          if (is_scalar($item) && trim((string) $item) !== '') {
            $parts[] = trim(strip_tags((string) $item));
          }
        });
        if ($parts !== []) {
          return implode(' ', array_unique($parts));
        }
      }
    }

    if ($featureId !== '') {
      return 'PDOK adreskandidaat ' . $featureId;
    }
    return $href !== '' ? 'PDOK adreskandidaat' : '';
  }

  /**
   * @param array<int, mixed> $links
   * @param array<string, mixed> $properties
   */
  private function firstHref(array $links, array $properties): string {
    if (!empty($properties['href']) && is_scalar($properties['href'])) {
      return trim((string) $properties['href']);
    }
    foreach ($links as $link) {
      if (is_array($link) && !empty($link['href']) && is_scalar($link['href'])) {
        return trim((string) $link['href']);
      }
    }
    return '';
  }

}
