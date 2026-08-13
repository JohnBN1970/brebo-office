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

  private const LOCATION_SEARCH_URL = 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free';
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
        'Accept' => 'application/json',
        'User-Agent' => 'BREBO Office/1.0 (sboffice.brebobv.nl)',
      ],
    ]);

    try {
      $response = $client->get(self::LOCATION_SEARCH_URL, [
        'query' => [
          'q' => $query,
          'fq' => 'type:adres',
          'rows' => max(1, min(10, $limit)),
        ],
      ]);
      $payload = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (GuzzleException | \JsonException $exception) {
      throw new \RuntimeException('PDOK Locatieserver lookup failed.', 0, $exception);
    }

    $docs = $payload['response']['docs'] ?? [];
    if (!is_array($docs)) {
      return [];
    }

    $result = [];
    foreach ($docs as $doc) {
      if (!is_array($doc)) {
        continue;
      }

      $displayName = trim((string) ($doc['weergavenaam'] ?? ''));
      $featureId = trim((string) ($doc['id'] ?? ''));
      if ($displayName === '' && $featureId === '') {
        continue;
      }

      $result[] = [
        'display_name' => $displayName !== '' ? $displayName : ('PDOK adreskandidaat ' . $featureId),
        'feature_id' => $featureId,
        'href' => '',
        'properties' => $doc,
        'geometry' => NULL,
        'source' => 'PDOK Locatieserver',
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
