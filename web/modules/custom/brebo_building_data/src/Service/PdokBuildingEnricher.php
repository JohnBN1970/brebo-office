<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Service;

use Drupal\node\NodeInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/** Automatically enriches BREBO buildings from authoritative PDOK BAG data. */
final class PdokBuildingEnricher {

  private const FREE_URL = 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free';

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

    $primary = $this->findPrimaryAddress($query, $address);
    if ($primary === NULL) {
      return ['state' => 'not_found', 'pand_id' => NULL, 'address_count' => 0, 'identity_count' => 0];
    }

    $primaryPandId = trim((string) ($primary['pand_id'] ?? ''));
    if ($primaryPandId === '') {
      return ['state' => 'not_found_pand', 'pand_id' => NULL, 'address_count' => 0, 'identity_count' => 0];
    }

    $addresses = $address['range_end'] !== ''
      ? $this->findAddressesForScope($address)
      : $this->findAddressesForPand($primaryPandId);

    if ($addresses === []) {
      return ['state' => 'not_found_scope', 'pand_id' => $primaryPandId, 'address_count' => 0, 'identity_count' => 0];
    }

    $now = gmdate(DATE_ATOM);
    $identityCount = 0;
    foreach ($addresses as $candidate) {
      $this->relations->upsertAddress($buildingNid, [
        'straatnaam' => $candidate['straatnaam'] ?? NULL,
        'huisnummer' => $candidate['huisnummer'] ?? NULL,
        'huisletter' => $candidate['huisletter'] ?? NULL,
        'huisnummertoevoeging' => $candidate['huisnummertoevoeging'] ?? NULL,
        'postcode' => $candidate['postcode'] ?? NULL,
        'woonplaatsnaam' => $candidate['woonplaatsnaam'] ?? NULL,
        'country' => 'Nederland',
        'is_primary' => $this->isSameAddress($candidate, $primary) ? 1 : 0,
        'source' => 'PDOK BAG',
        'source_ref' => 'locatieserver:' . (string) ($candidate['id'] ?? ''),
      ]);
      foreach ($this->identityMap($candidate) as $type => $id) {
        if ($id === '') {
          continue;
        }
        $this->relations->upsertBagIdentity($buildingNid, $type, $id, [
          'status' => (string) ($candidate['status'] ?? ''),
          'is_primary' => $type === 'pand' && $id === $primaryPandId ? 1 : 0,
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
      'fl' => 'id,weergavenaam,type,status,straatnaam,huisnummer,huisletter,huisnummertoevoeging,postcode,woonplaatsnaam,pand_id,adresseerbaarobject_id,nummeraanduiding_id',
    ]);

    foreach ($docs as $doc) {
      if ($this->matchesExpected($doc, $expected)) {
        return $doc;
      }
    }
    return NULL;
  }

  /** @return array<int, array<string, mixed>> */
  private function findAddressesForPand(string $pandId): array {
    return $this->request([
      'q' => '*',
      'rows' => 1000,
      'fq' => ['bron:BAG', 'type:adres', 'pand_id:' . $pandId],
      'fl' => 'id,weergavenaam,type,status,straatnaam,huisnummer,huisletter,huisnummertoevoeging,postcode,woonplaatsnaam,pand_id,adresseerbaarobject_id,nummeraanduiding_id',
    ]);
  }

  /**
   * Finds official BAG addresses inside the explicit BREBO house-number scope.
   *
   * The numeric range is inclusive and deliberately has no even/odd filter.
   *
   * @return array<int, array<string, mixed>>
   */
  private function findAddressesForScope(array $scope): array {
    $query = trim(implode(' ', array_filter([
      $scope['street'],
      $scope['postal_code'],
      $scope['city'],
    ])));
    $docs = $this->request([
      'q' => $query !== '' ? $query : '*',
      'rows' => 1000,
      'fq' => ['bron:BAG', 'type:adres'],
      'fl' => 'id,weergavenaam,type,status,straatnaam,huisnummer,huisletter,huisnummertoevoeging,postcode,woonplaatsnaam,pand_id,adresseerbaarobject_id,nummeraanduiding_id',
    ]);

    $start = (int) $scope['house_number'];
    $end = (int) $scope['range_end'];
    if ($start > $end) {
      [$start, $end] = [$end, $start];
    }

    $expectedStreet = mb_strtolower(trim((string) $scope['street']));
    $expectedCity = mb_strtolower(trim((string) $scope['city']));
    $expectedPostcode = strtoupper(preg_replace('/\s+/u', '', (string) $scope['postal_code']) ?? '');

    return array_values(array_filter($docs, static function (array $doc) use ($start, $end, $expectedStreet, $expectedCity, $expectedPostcode): bool {
      $number = (int) ($doc['huisnummer'] ?? 0);
      if ($number < $start || $number > $end) {
        return FALSE;
      }
      if ($expectedStreet !== '' && mb_strtolower(trim((string) ($doc['straatnaam'] ?? ''))) !== $expectedStreet) {
        return FALSE;
      }
      if ($expectedCity !== '' && mb_strtolower(trim((string) ($doc['woonplaatsnaam'] ?? ''))) !== $expectedCity) {
        return FALSE;
      }
      if ($expectedPostcode !== '') {
        $postcode = strtoupper(preg_replace('/\s+/u', '', (string) ($doc['postcode'] ?? '')) ?? '');
        // A range can cross postcode boundaries; only use postcode as a search
        // hint, not as a hard filter for every unit in the range.
        if ($postcode === '') {
          return FALSE;
        }
      }
      return TRUE;
    }));
  }

  /** @return array<int, array<string, mixed>> */
  private function request(array $query): array {
    try {
      $response = $this->httpClient->request('GET', self::FREE_URL, [
        'query' => $query,
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

}
