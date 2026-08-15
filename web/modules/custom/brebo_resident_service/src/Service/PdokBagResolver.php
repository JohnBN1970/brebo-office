<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Service;

use GuzzleHttp\ClientInterface;

/** Resolves parsed address scopes against the official PDOK BAG OGC API. */
final class PdokBagResolver {

  private const ADDRESSES_URL = 'https://api.pdok.nl/kadaster/bag/ogc/v2/collections/adres/items';

  public function __construct(private readonly ClientInterface $httpClient) {}

  /**
   * Resolve a range. PDOK remains authoritative: no synthetic house numbers are created.
   *
   * @return array<int, array<string, mixed>>
   */
  public function resolve(array $scope): array {
    $params = ['limit' => 1000, 'f' => 'json'];
    // The BAG OGC API supports collection filtering. Keep query construction here so
    // all intake channels share one authoritative resolver implementation.
    $filter = [sprintf("straatnaam='%s'", str_replace("'", "''", (string) $scope['street']))];
    if (!empty($scope['city'])) {
      $filter[] = sprintf("woonplaats='%s'", str_replace("'", "''", (string) $scope['city']));
    }
    if (!empty($scope['postal_code'])) {
      $filter[] = sprintf("postcode='%s'", (string) $scope['postal_code']);
    }
    $params['filter'] = implode(' AND ', $filter);
    $params['filter-lang'] = 'cql2-text';

    $response = $this->httpClient->request('GET', self::ADDRESSES_URL, [
      'query' => $params,
      'headers' => ['Accept' => 'application/geo+json, application/json'],
      'timeout' => 15,
    ]);
    $payload = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
    $resolved = [];
    foreach (($payload['features'] ?? []) as $feature) {
      $p = $feature['properties'] ?? [];
      $number = (int) ($p['huisnummer'] ?? 0);
      if ($number < (int) $scope['range_from'] || $number > (int) $scope['range_to']) {
        continue;
      }
      if (($scope['range_parity'] ?? 'all') === 'even' && $number % 2 !== 0) {
        continue;
      }
      if (($scope['range_parity'] ?? 'all') === 'oneven' && $number % 2 === 0) {
        continue;
      }
      $resolved[] = [
        'bag_nummeraanduiding_id' => $p['nummeraanduidingIdentificatie'] ?? $p['nummeraanduiding_id'] ?? NULL,
        'bag_verblijfsobject_id' => $p['adresseerbaarObjectIdentificatie'] ?? $p['adresseerbaar_object_id'] ?? NULL,
        'street' => $p['straatnaam'] ?? $scope['street'],
        'house_number' => (string) $number,
        'house_letter' => $p['huisletter'] ?? NULL,
        'addition' => $p['huisnummertoevoeging'] ?? NULL,
        'postal_code' => $p['postcode'] ?? NULL,
        'city' => $p['woonplaats'] ?? $scope['city'] ?? NULL,
      ];
    }
    return $resolved;
  }
}
