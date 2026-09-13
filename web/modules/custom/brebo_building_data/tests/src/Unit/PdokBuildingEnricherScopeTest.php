<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_building_data\Unit;

use Drupal\brebo_building_data\Service\BuildingRelationRepository;
use Drupal\brebo_building_data\Service\PdokBuildingEnricher;
use Drupal\node\NodeInterface;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/** @coversDefaultClass \Drupal\brebo_building_data\Service\PdokBuildingEnricher */
final class PdokBuildingEnricherScopeTest extends TestCase {

  public function testSingleAddressRequiresExactMatchAndUsesPandAddresses(): void {
    $requests = [
      [$this->doc('Dam', '1', '1012JS', 'Amsterdam', 'PAND-1', 'VBO-1', 'NA-1')],
      [
        $this->doc('Dam', '1', '1012JS', 'Amsterdam', 'PAND-1', 'VBO-1', 'NA-1'),
        $this->doc('Dam', '2', '1012JS', 'Amsterdam', 'PAND-1', 'VBO-2', 'NA-2'),
      ],
    ];
    [$enricher, $relations] = $this->enricher($requests);

    $result = $enricher->enrich($this->building('Dam 1', '1012JS', 'Amsterdam'));

    self::assertSame('enriched', $result['state']);
    self::assertSame('PAND-1', $result['pand_id']);
    self::assertSame(2, $result['address_count']);
    self::assertCount(2, $relations->addresses);
  }

  public function testNumberedStreetParsesFinalNumberAsHouseNumber(): void {
    $requests = [
      [$this->doc('Plein 1944', '1', '6511JC', 'Nijmegen', 'PAND-1944', 'VBO-1944', 'NA-1944')],
      [$this->doc('Plein 1944', '1', '6511JC', 'Nijmegen', 'PAND-1944', 'VBO-1944', 'NA-1944')],
    ];
    [$enricher] = $this->enricher($requests);

    $result = $enricher->enrich($this->building('Plein 1944 1', '6511JC', 'Nijmegen'));

    self::assertSame('enriched', $result['state']);
    self::assertSame('PAND-1944', $result['pand_id']);
  }

  public function testExplicitRangeIsInclusiveHardScopeAcrossPandIds(): void {
    $requests = [
      [$this->doc('Bilderdijkstraat', '87', '1053KM', 'Amsterdam', 'PAND-A', 'VBO-87', 'NA-87')],
      [
        $this->doc('Bilderdijkstraat', '86', '1053KM', 'Amsterdam', 'PAND-X', 'VBO-86', 'NA-86'),
        $this->doc('Bilderdijkstraat', '87', '1053KM', 'Amsterdam', 'PAND-A', 'VBO-87', 'NA-87'),
        $this->doc('Bilderdijkstraat', '90', '1053KN', 'Amsterdam', 'PAND-B', 'VBO-90', 'NA-90'),
        $this->doc('Bilderdijkstraat', '97', '1053KP', 'Amsterdam', 'PAND-C', 'VBO-97', 'NA-97'),
        $this->doc('Bilderdijkstraat', '98', '1053KP', 'Amsterdam', 'PAND-Y', 'VBO-98', 'NA-98'),
        $this->doc('Andere straat', '90', '1053KM', 'Amsterdam', 'PAND-Z', 'VBO-Z', 'NA-Z'),
      ],
    ];
    [$enricher, $relations] = $this->enricher($requests);

    $result = $enricher->enrich($this->building('Bilderdijkstraat 87 t/m 97', '1053KM', 'Amsterdam'));

    self::assertSame('enriched', $result['state']);
    self::assertSame(3, $result['address_count']);
    self::assertSame(['87', '90', '97'], array_column($relations->addresses, 'huisnummer'));
    self::assertSame(['PAND-A', 'PAND-B', 'PAND-C'], array_values(array_unique(array_column($relations->identities, 'bag_id'))));
  }

  public function testNoExactPrimaryMatchDoesNotLinkFirstFuzzyResult(): void {
    $requests = [[
      $this->doc('Dam', '2', '1012JS', 'Amsterdam', 'PAND-WRONG', 'VBO-WRONG', 'NA-WRONG'),
    ]];
    [$enricher, $relations] = $this->enricher($requests);

    $result = $enricher->enrich($this->building('Dam 1', '1012JS', 'Amsterdam'));

    self::assertSame('not_found', $result['state']);
    self::assertSame([], $relations->addresses);
    self::assertSame([], $relations->identities);
  }

  /** @return array{PdokBuildingEnricher, object} */
  private function enricher(array $payloads): array {
    $http = $this->createMock(ClientInterface::class);
    $responses = [];
    foreach ($payloads as $docs) {
      $body = $this->createMock(StreamInterface::class);
      $body->method('__toString')->willReturn(json_encode(['response' => ['docs' => $docs]], JSON_THROW_ON_ERROR));
      $response = $this->createMock(ResponseInterface::class);
      $response->method('getBody')->willReturn($body);
      $responses[] = $response;
    }
    $http->method('request')->willReturnOnConsecutiveCalls(...$responses);

    $relations = new class extends BuildingRelationRepository {
      public array $addresses = [];
      public array $identities = [];
      public function __construct() {}
      public function upsertAddress(int $buildingNid, array $data): array {
        $this->addresses[] = $data;
        return ['state' => 'created'];
      }
      public function upsertBagIdentity(int $buildingNid, string $type, string $bagId, array $data = []): array {
        $this->identities[] = ['type' => $type, 'bag_id' => $bagId] + $data;
        return ['state' => 'created'];
      }
    };

    return [new PdokBuildingEnricher($http, $relations), $relations];
  }

  private function building(string $address, string $postcode, string $city): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('brebo_building');
    $node->method('id')->willReturn(999);
    $values = [
      'field_brebo_address' => $address,
      'field_brebo_postal_code' => $postcode,
      'field_brebo_city' => $city,
    ];
    $node->method('hasField')->willReturnCallback(static fn(string $field): bool => isset($values[$field]));
    $node->method('get')->willReturnCallback(function (string $field) use ($values) {
      $item = new class($values[$field] ?? '') {
        public function __construct(public string $value) {}
        public function isEmpty(): bool { return $this->value === ''; }
      };
      return $item;
    });
    return $node;
  }

  private function doc(string $street, string $number, string $postcode, string $city, string $pand, string $vbo, string $numberDesignation): array {
    return [
      'id' => 'adres-' . $number,
      'straatnaam' => $street,
      'huisnummer' => $number,
      'postcode' => $postcode,
      'woonplaatsnaam' => $city,
      'pand_id' => $pand,
      'adresseerbaarobject_id' => $vbo,
      'nummeraanduiding_id' => $numberDesignation,
      'status' => 'Verblijfsobject in gebruik',
    ];
  }

}
