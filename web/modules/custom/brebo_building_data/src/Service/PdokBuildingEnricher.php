<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Service;

use Drupal\node\NodeInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/** Automatically enriches BREBO buildings from authoritative PDOK BAG data. */
final class PdokBuildingEnricher {

  private const FREE_URL = 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free';
  private const LOOKUP_URL = 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/lookup';
  private const ADDRESS_FIELDS = 'id,weergavenaam,type,status,straatnaam,huisnummer,huisletter,huisnummertoevoeging,postcode,woonplaatsnaam,pand_id,adresseerbaarobject_id,nummeraanduiding_id,centroide_ll';

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly BuildingRelationRepository $relations,
  ) {}

  /**
   * Enrich one building from its BREBO address scope.
   *
   * For a single address the matched BAG pand is used to discover all official
   * addresses in that pand. For an explicit house-number range, the range is
   * the hard scope and may legitimately span multiple BAG pand identities.
   *
   * Address and BAG relations are duplicate-safe. Existing verified building
   * truth is deliberately not overwritten here.
   *
   * @return array{state:string,pand_id:?string,address_count:int,identity_count:int}
   */
  public function enrich(NodeInterface $building): array {
    if ($building->bundle() !== 'brebo_building') {
      throw new \InvalidArgumentException('PDOK enrichment requires a BREBO building.');
    }

    $buildingNid = (int) $building->id();
    $address = $this->buildingAddress($building);
    $number = trim($address['house_number'] . $address['house_letter'] . ($address['addition'] !== '' ? '-' . $address['addition'] : ''));
    $query = trim(implode(' ', array_filter([
      $address['street'],
      $number,
      $address['postal_code'],
      $address['city'],
    ])));
    if ($query === '') {
      return ['state' => 'skipped_no_address', 'pand_id' => NULL, 'address_count' => 0, 'identity_count' => 0];
    }

    $primary = NULL;
    $primaryPandId = NULL;
    if ($address['range_end'] !== '') {
      // The range is authoritative. Query every applicable house number
      // explicitly so a long street cannot hide in-range addresses behind the
      // Free API row cap.
      $addresses = $this->findAddressesForScope($address);
      if ($addresses === []) {
        return ['state' => 'not_found_scope', 'pand_id' => NULL, 'address_count' => 0, 'identity_count' => 0];
      }
      $primary = $this->primaryForRange($addresses, $address);
      $primaryPandId = trim((string) ($primary['pand_id'] ?? '')) ?: NULL;
    }
    else {
      $primary = $this->findPrimaryAddress($query, $address);
      if ($primary === NULL) {
        return ['state' => 'not_found', 'pand_id' => NULL, 'address_count' => 0, 'identity_count' => 0];
      }

      $primaryPandId = trim((string) ($primary['pand_id'] ?? ''));
      if ($primaryPandId === '') {
        return ['state' => 'not_found_pand', 'pand_id' => NULL, 'address_count' => 0, 'identity_count' => 0];
      }

      $addresses = $this->findAddressesForPand($primaryPandId);
      if ($addresses === []) {
        return ['state' => 'not_found_scope', 'pand_id' => $primaryPandId, 'address_count' => 0, 'identity_count' => 0];
      }
    }

    // PDOK refresh is a synchronization, not an append operation. Only after
    // every upstream request and lookup has succeeded do we replace the prior
    // PDOK-owned snapshot. Manual and other-source relations remain untouched.
    $this->relations->clearSourceRelations($buildingNid, 'PDOK BAG');

    $now = gmdate(DATE_ATOM);
    $identityCount = 0;
    foreach ($addresses as $candidate) {
      $coordinates = $this->coordinatesFromDoc($candidate);
      $this->relations->upsertAddress($buildingNid, [
        'straatnaam' => $candidate['straatnaam'] ?? NULL,
        'huisnummer' => $candidate['huisnummer'] ?? NULL,
        'huisletter' => $candidate['huisletter'] ?? NULL,
        'huisnummertoevoeging' => $candidate['huisnummertoevoeging'] ?? NULL,
        'postcode' => $candidate['postcode'] ?? NULL,
        'woonplaatsnaam' => $candidate['woonplaatsnaam'] ?? NULL,
        'country' => 'Nederland',
        'latitude' => $coordinates['latitude'],
        'longitude' => $coordinates['longitude'],
        'is_primary' => $primary !== NULL && $this->isSameAddress($candidate, $primary) ? 1 : 0,
        'source' => 'PDOK BAG',
        'source_ref' => 'locatieserver:' . (string) ($candidate['id'] ?? ''),
      ]);
      foreach ($this->identityMap($candidate) as $type => $id) {
        if ($id === '') {
          continue;
        }
        $this->relations->upsertBagIdentity($buildingNid, $type, $id, [
          'status' => (string) ($candidate['status'] ?? ''),
          'is_primary' => $type === 'pand' && $primaryPandId !== NULL && $id === $primaryPandId ? 1 : 0,
          'source' => 'PDOK BAG',
          'source_ref' => 'locatieserver:' . (string) ($candidate['id'] ?? ''),
          'retrieved_at' => $now,
        ]);
        $identityCount++;
      }
    }

    return ['state' => 'enriched', 'pand_id' => $primaryPandId, 'address_count' => count($addresses), 'identity_count' => $identityCount];
  }

  /** @return array<string, mixed>|null */
  private function findPrimaryAddress(string $query, array $expected): ?array {
    $docs = $this->request([
      'q' => $query,
      'rows' => 10,
      'fq' => ['bron:BAG', 'type:adres'],
      'fl' => self::ADDRESS_FIELDS,
    ]);

    foreach ($docs as $doc) {
      if ($this->matchesExpected($doc, $expected)) {
        return $this->lookupAddressDetails($doc);
      }
    }
    return NULL;
  }

  /** @return array<int, array<string, mixed>> */
  private function findAddressesForPand(string $pandId): array {
    $docs = $this->request([
      'q' => '*',
      'rows' => 100,
      'fq' => ['bron:BAG', 'type:adres', 'pand_id:' . $pandId],
      'fl' => self::ADDRESS_FIELDS,
    ]);

    return array_map(fn(array $doc): array => $this->lookupAddressDetails($doc), $docs);
  }

  /**
   * Finds official BAG addresses inside the explicit BREBO house-number scope.
   *
   * The numeric range is inclusive. When both range bounds have the same parity
   * (for example 87 t/m 97 or 88 t/m 98), that parity defines the sequence.
   * Mixed-parity bounds remain a continuous range. Letters and additions for
   * each selected base house number remain in scope.
   *
   * @return array<int, array<string, mixed>>
   */
  private function findAddressesForScope(array $scope): array {
    $start = (int) $scope['house_number'];
    $end = (int) $scope['range_end'];
    if ($start > $end) {
      [$start, $end] = [$end, $start];
    }

    $step = ($start % 2) === ($end % 2) ? 2 : 1;
    $expectedStreet = mb_strtolower(trim((string) $scope['street']));
    $expectedCity = mb_strtolower(trim((string) $scope['city']));
    $addresses = [];

    for ($number = $start; $number <= $end; $number += $step) {
      $query = trim(implode(' ', array_filter([
        $scope['street'],
        (string) $number,
        $scope['postal_code'],
        $scope['city'],
      ])));
      $docs = $this->request([
        'q' => $query,
        'rows' => 100,
        'fq' => ['bron:BAG', 'type:adres'],
        'fl' => self::ADDRESS_FIELDS,
      ]);

      foreach ($docs as $doc) {
        if ((int) ($doc['huisnummer'] ?? 0) !== $number) {
          continue;
        }
        if ($expectedStreet !== '' && mb_strtolower(trim((string) ($doc['straatnaam'] ?? ''))) !== $expectedStreet) {
          continue;
        }
        if ($expectedCity !== '' && mb_strtolower(trim((string) ($doc['woonplaatsnaam'] ?? ''))) !== $expectedCity) {
          continue;
        }

        $doc = $this->lookupAddressDetails($doc);
        // Lookup may normalize details, but it must never be allowed to move a
        // result outside the selected base house number.
        if ((int) ($doc['huisnummer'] ?? 0) !== $number) {
          continue;
        }

        $key = trim((string) ($doc['id'] ?? ''));
        if ($key === '') {
          $key = implode('|', [
            mb_strtolower(trim((string) ($doc['straatnaam'] ?? ''))),
            (string) ($doc['huisnummer'] ?? ''),
            strtoupper(trim((string) ($doc['huisletter'] ?? ''))),
            strtoupper(trim((string) ($doc['huisnummertoevoeging'] ?? ''))),
            strtoupper(preg_replace('/\s+/u', '', (string) ($doc['postcode'] ?? '')) ?? ''),
          ]);
        }
        $addresses[$key] = $doc;
      }
    }

    $addresses = array_values($addresses);
    usort($addresses, static function (array $a, array $b): int {
      $numberCompare = ((int) ($a['huisnummer'] ?? 0)) <=> ((int) ($b['huisnummer'] ?? 0));
      if ($numberCompare !== 0) {
        return $numberCompare;
      }
      $aSuffix = strtoupper(trim((string) ($a['huisletter'] ?? '')) . '-' . trim((string) ($a['huisnummertoevoeging'] ?? '')));
      $bSuffix = strtoupper(trim((string) ($b['huisletter'] ?? '')) . '-' . trim((string) ($b['huisnummertoevoeging'] ?? '')));
      return $aSuffix <=> $bSuffix;
    });

    return $addresses;
  }

  /**
   * Enrich a Free API hit through Lookup because identifiers such as pand_id
   * are not guaranteed to be populated on every free-search document.
   *
   * @param array<string, mixed> $doc
   *
   * @return array<string, mixed>
   */
  private function lookupAddressDetails(array $doc): array {
    $id = trim((string) ($doc['id'] ?? ''));
    if ($id === '') {
      return $doc;
    }

    $details = $this->request([
      'id' => $id,
      'fl' => self::ADDRESS_FIELDS,
    ], self::LOOKUP_URL);
    if ($details === []) {
      return $doc;
    }

    // Lookup is authoritative for full BAG identifiers; retain Free fields only
    // when Lookup did not return them.
    return array_replace($doc, $details[0]);
  }

  /** @return array<string, mixed> */
  private function primaryForRange(array $addresses, array $scope): array {
    $start = (int) ($scope['house_number'] ?? 0);
    foreach ($addresses as $candidate) {
      if ((int) ($candidate['huisnummer'] ?? 0) === $start) {
        return $candidate;
      }
    }
    return $addresses[0];
  }

  /** @return array<int, array<string, mixed>> */
  private function request(array $query, string $url = self::FREE_URL): array {
    try {
      $response = $this->httpClient->request('GET', $url, [
        // PDOK expects repeated fq parameters (fq=a&fq=b). Passing an array
        // directly to Guzzle produces fq[0]=a&fq[1]=b, which PDOK rejects.
        'query' => $this->queryString($query),
        'headers' => [
          'Accept' => 'application/json',
          'User-Agent' => 'BREBO Office/1.0 (sboffice.brebobv.nl)',
        ],
        'timeout' => 10,
      ]);
      $payload = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
      return array_values(array_filter((array) ($payload['response']['docs'] ?? []), 'is_array'));
    }
    catch (GuzzleException | \JsonException $e) {
      throw new \RuntimeException('PDOK BAG enrichment failed: ' . $e->getMessage(), 0, $e);
    }
  }

  /** Builds an RFC 3986 query string while preserving repeated parameters. */
  private function queryString(array $query): string {
    $pairs = [];
    foreach ($query as $key => $value) {
      foreach (is_array($value) ? $value : [$value] as $item) {
        $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $item);
      }
    }
    return implode('&', $pairs);
  }

  /** @return array{street:string,house_number:string,house_letter:string,addition:string,range_end:string,postal_code:string,city:string} */
  private function buildingAddress(NodeInterface $building): array {
    $parsed = $this->parseAddressLine($this->field($building, 'field_brebo_address'));
    return [
      'street' => $parsed['street'],
      'house_number' => $parsed['house_number'],
      'house_letter' => $parsed['house_letter'],
      'addition' => $parsed['addition'],
      'range_end' => $parsed['range_end'],
      'postal_code' => $this->field($building, 'field_brebo_postal_code'),
      'city' => $this->field($building, 'field_brebo_city'),
    ];
  }

  /** @return array{street:string,house_number:string,house_letter:string,addition:string,range_end:string} */
  private function parseAddressLine(string $line): array {
    $line = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
    $empty = ['street' => $line, 'house_number' => '', 'house_letter' => '', 'addition' => '', 'range_end' => ''];
    if ($line === '') {
      return $empty;
    }

    if (preg_match('/^(.+)\s+(\d+)\s*(?:t\s*\/\s*m|tm|tot)\s*(\d+)$/iu', $line, $rangeMatches)) {
      $street = trim((string) ($rangeMatches[1] ?? ''));
      $start = trim((string) ($rangeMatches[2] ?? ''));
      $end = trim((string) ($rangeMatches[3] ?? ''));
      if ($street !== '' && $start !== '' && $end !== '') {
        return [
          'street' => $street,
          'house_number' => $start,
          'house_letter' => '',
          'addition' => '',
          'range_end' => $end,
        ];
      }
    }

    if (!preg_match('/^(.+)\s+(\d+)\s*([A-Za-z]?)\s*(?:[-\/]\s*([A-Za-z0-9.-]+))?$/u', $line, $matches)) {
      return $empty;
    }

    $street = trim((string) ($matches[1] ?? ''));
    $houseNumber = trim((string) ($matches[2] ?? ''));
    $houseLetter = strtoupper(trim((string) ($matches[3] ?? '')));
    $addition = strtoupper(trim((string) ($matches[4] ?? '')));
    if ($street === '' || $houseNumber === '') {
      return $empty;
    }

    return [
      'street' => $street,
      'house_number' => $houseNumber,
      'house_letter' => $houseLetter,
      'addition' => $addition,
      'range_end' => '',
    ];
  }

  private function field(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '';
    }
    return trim((string) ($node->get($field)->value ?? ''));
  }

  private function matchesExpected(array $doc, array $expected): bool {
    $expectedStreet = mb_strtolower(trim((string) ($expected['street'] ?? '')));
    if ($expectedStreet !== '' && mb_strtolower(trim((string) ($doc['straatnaam'] ?? ''))) !== $expectedStreet) {
      return FALSE;
    }

    $expectedCity = mb_strtolower(trim((string) ($expected['city'] ?? '')));
    if ($expectedCity !== '' && mb_strtolower(trim((string) ($doc['woonplaatsnaam'] ?? ''))) !== $expectedCity) {
      return FALSE;
    }

    $postcode = strtoupper(preg_replace('/\s+/', '', (string) ($doc['postcode'] ?? '')) ?? '');
    $expectedPostcode = strtoupper(preg_replace('/\s+/', '', (string) ($expected['postal_code'] ?? '')) ?? '');
    if ($expectedPostcode !== '' && $postcode !== $expectedPostcode) {
      return FALSE;
    }

    $expectedNumber = trim((string) ($expected['house_number'] ?? ''));
    if ($expectedNumber !== '' && trim((string) ($doc['huisnummer'] ?? '')) !== $expectedNumber) {
      return FALSE;
    }

    $expectedLetter = strtoupper(trim((string) ($expected['house_letter'] ?? '')));
    if ($expectedLetter !== '' && strtoupper(trim((string) ($doc['huisletter'] ?? ''))) !== $expectedLetter) {
      return FALSE;
    }

    $expectedAddition = strtoupper(trim((string) ($expected['addition'] ?? '')));
    if ($expectedAddition !== '' && strtoupper(trim((string) ($doc['huisnummertoevoeging'] ?? ''))) !== $expectedAddition) {
      return FALSE;
    }

    return TRUE;
  }

  private function isSameAddress(array $a, array $b): bool {
    return strtoupper((string) ($a['postcode'] ?? '')) === strtoupper((string) ($b['postcode'] ?? ''))
      && (string) ($a['huisnummer'] ?? '') === (string) ($b['huisnummer'] ?? '')
      && strtoupper((string) ($a['huisletter'] ?? '')) === strtoupper((string) ($b['huisletter'] ?? ''))
      && strtoupper((string) ($a['huisnummertoevoeging'] ?? '')) === strtoupper((string) ($b['huisnummertoevoeging'] ?? ''));
  }

  /** @return array<string,string> */
  private function identityMap(array $doc): array {
    return [
      'pand' => trim((string) ($doc['pand_id'] ?? '')),
      'adresseerbaarobject' => trim((string) ($doc['adresseerbaarobject_id'] ?? '')),
      'verblijfsobject' => trim((string) ($doc['adresseerbaarobject_id'] ?? '')),
      'nummeraanduiding' => trim((string) ($doc['nummeraanduiding_id'] ?? '')),
    ];
  }

  /** @return array{latitude:?float,longitude:?float} */
  private function coordinatesFromDoc(array $doc): array {
    $point = trim((string) ($doc['centroide_ll'] ?? ''));
    if (!preg_match('/^POINT\s*\(\s*(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s*\)$/i', $point, $matches)) {
      return ['latitude' => NULL, 'longitude' => NULL];
    }
    $longitude = (float) $matches[1];
    $latitude = (float) $matches[2];
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
      return ['latitude' => NULL, 'longitude' => NULL];
    }
    return ['latitude' => $latitude, 'longitude' => $longitude];
  }

}
