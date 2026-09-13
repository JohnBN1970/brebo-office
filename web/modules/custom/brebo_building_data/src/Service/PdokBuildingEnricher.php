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
   * Enrich one building from its primary BREBO address fields.
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
    $query = trim(implode(' ', array_filter([
      $address['street'],
      $address['house_number'],
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

    $pandId = trim((string) ($primary['pand_id'] ?? ''));
    if ($pandId === '') {
      return ['state' => 'not_found_pand', 'pand_id' => NULL, 'address_count' => 0, 'identity_count' => 0];
    }

    $now = gmdate(DATE_ATOM);
    $identityCount = 0;
    foreach ($this->identityMap($primary) as $type => $id) {
      if ($id === '') {
        continue;
      }
      $this->relations->upsertBagIdentity($buildingNid, $type, $id, [
        'status' => (string) ($primary['status'] ?? ''),
        'is_primary' => $type === 'pand' ? 1 : 0,
        'source' => 'PDOK BAG',
        'source_ref' => 'locatieserver:' . (string) ($primary['id'] ?? ''),
        'retrieved_at' => $now,
      ]);
      $identityCount++;
    }

    $addresses = $this->findAddressesForPand($pandId);
    $addressCount = 0;
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
          'is_primary' => $type === 'pand' ? 1 : 0,
          'source' => 'PDOK BAG',
          'source_ref' => 'locatieserver:' . (string) ($candidate['id'] ?? ''),
          'retrieved_at' => $now,
        ]);
      }
      $addressCount++;
    }

    return ['state' => 'enriched', 'pand_id' => $pandId, 'address_count' => $addressCount, 'identity_count' => $identityCount];
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
    return $docs[0] ?? NULL;
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

  /** @return array{street:string,house_number:string,postal_code:string,city:string} */
  private function buildingAddress(NodeInterface $building): array {
    return [
      'street' => $this->field($building, 'field_brebo_address'),
      'house_number' => '',
      'postal_code' => $this->field($building, 'field_brebo_postal_code'),
      'city' => $this->field($building, 'field_brebo_city'),
    ];
  }

  private function field(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '';
    }
    return trim((string) ($node->get($field)->value ?? ''));
  }

  private function matchesExpected(array $doc, array $expected): bool {
    $postcode = strtoupper(preg_replace('/\s+/', '', (string) ($doc['postcode'] ?? '')) ?? '');
    $expectedPostcode = strtoupper(preg_replace('/\s+/', '', (string) ($expected['postal_code'] ?? '')) ?? '');
    if ($expectedPostcode !== '' && $postcode !== $expectedPostcode) {
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
