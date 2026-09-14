<?php

declare(strict_types=1);

namespace Drupal\node {
  interface NodeInterface {
    public function bundle();
    public function id();
    public function hasField($field);
    public function get($field);
  }
}

namespace Drupal\brebo_building_data\Service {
  final class BuildingRelationRepository {
    public array $addresses = [];
    public array $identities = [];

    public function upsertAddress(int $buildingNid, array $data): array {
      $this->addresses[] = $data;
      return ['state' => 'created'];
    }

    public function upsertBagIdentity(int $buildingNid, string $type, string $bagId, array $data = []): array {
      $this->identities[] = ['type' => $type, 'bag_id' => $bagId] + $data;
      return ['state' => 'created'];
    }
  }
}

namespace {
  require __DIR__ . '/../vendor/autoload.php';
  require __DIR__ . '/../web/modules/custom/brebo_building_data/src/Service/PdokBuildingEnricher.php';

  use Drupal\brebo_building_data\Service\BuildingRelationRepository;
  use Drupal\brebo_building_data\Service\PdokBuildingEnricher;
  use Drupal\node\NodeInterface;
  use GuzzleHttp\Client;
  use GuzzleHttp\Handler\MockHandler;
  use GuzzleHttp\HandlerStack;
  use GuzzleHttp\Psr7\Response;

  function assertSameValue(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
      throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
  }

  function assertTrueValue(bool $value, string $message): void {
    if (!$value) {
      throw new RuntimeException($message);
    }
  }

  function doc(string $street, string $number, string $postcode, string $city, string $pand, string $vbo, string $numberDesignation): array {
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

  function response(array $docs): Response {
    return new Response(200, ['Content-Type' => 'application/json'], json_encode(['response' => ['docs' => $docs]], JSON_THROW_ON_ERROR));
  }

  function building(string $address, string $postcode, string $city): NodeInterface {
    return new class($address, $postcode, $city) implements NodeInterface {
      private array $values;

      public function __construct(string $address, string $postcode, string $city) {
        $this->values = [
          'field_brebo_address' => $address,
          'field_brebo_postal_code' => $postcode,
          'field_brebo_city' => $city,
        ];
      }

      public function bundle(): string { return 'brebo_building'; }
      public function id(): int { return 999; }
      public function hasField($field): bool { return array_key_exists((string) $field, $this->values); }
      public function get($field): object {
        return new class($this->values[(string) $field] ?? '') {
          public function __construct(public string $value) {}
          public function isEmpty(): bool { return $this->value === ''; }
        };
      }
    };
  }

  function enricher(array $payloads): array {
    $responses = array_map(static fn(array $docs): Response => response($docs), $payloads);
    $client = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
    $relations = new BuildingRelationRepository();
    return [new PdokBuildingEnricher($client, $relations), $relations];
  }

  [$single, $singleRelations] = enricher([
    [doc('Dam', '1', '1012JS', 'Amsterdam', 'PAND-1', 'VBO-1', 'NA-1')],
    [
      doc('Dam', '1', '1012JS', 'Amsterdam', 'PAND-1', 'VBO-1', 'NA-1'),
      doc('Dam', '2', '1012JS', 'Amsterdam', 'PAND-1', 'VBO-2', 'NA-2'),
    ],
  ]);
  $singleResult = $single->enrich(building('Dam 1', '1012JS', 'Amsterdam'));
  assertSameValue('enriched', $singleResult['state'], 'Single-address enrichment must succeed.');
  assertSameValue(2, $singleResult['address_count'], 'Single-address enrichment must expand all addresses in the matched pand.');
  assertSameValue(2, count($singleRelations->addresses), 'Two pand addresses must be stored.');

  [$numbered] = enricher([
    [doc('Plein 1944', '1', '6511JC', 'Nijmegen', 'PAND-1944', 'VBO-1944', 'NA-1944')],
    [doc('Plein 1944', '1', '6511JC', 'Nijmegen', 'PAND-1944', 'VBO-1944', 'NA-1944')],
  ]);
  $numberedResult = $numbered->enrich(building('Plein 1944 1', '6511JC', 'Nijmegen'));
  assertSameValue('PAND-1944', $numberedResult['pand_id'], 'Trailing house number must not consume numeric street-name tokens.');

  [$range, $rangeRelations] = enricher([
    [doc('Bilderdijkstraat', '87', '1053KM', 'Amsterdam', 'PAND-A', 'VBO-87', 'NA-87')],
    [
      doc('Bilderdijkstraat', '86', '1053KM', 'Amsterdam', 'PAND-X', 'VBO-86', 'NA-86'),
      doc('Bilderdijkstraat', '87', '1053KM', 'Amsterdam', 'PAND-A', 'VBO-87', 'NA-87'),
      doc('Bilderdijkstraat', '90', '1053KN', 'Amsterdam', 'PAND-B', 'VBO-90', 'NA-90'),
      doc('Bilderdijkstraat', '97', '1053KP', 'Amsterdam', 'PAND-C', 'VBO-97', 'NA-97'),
      doc('Bilderdijkstraat', '98', '1053KP', 'Amsterdam', 'PAND-Y', 'VBO-98', 'NA-98'),
      doc('Andere straat', '90', '1053KM', 'Amsterdam', 'PAND-Z', 'VBO-Z', 'NA-Z'),
    ],
  ]);
  $rangeResult = $range->enrich(building('Bilderdijkstraat 87 t/m 97', '1053KM', 'Amsterdam'));
  assertSameValue('enriched', $rangeResult['state'], 'Explicit dossier range must enrich.');
  assertSameValue(3, $rangeResult['address_count'], 'Range must be inclusive and exclude addresses outside scope.');
  assertSameValue(['87', '90', '97'], array_column($rangeRelations->addresses, 'huisnummer'), 'Range must not apply an even/odd filter.');
  $pandIds = array_values(array_unique(array_map(
    static fn(array $identity): string => $identity['type'] === 'pand' ? $identity['bag_id'] : '',
    $rangeRelations->identities,
  )));
  $pandIds = array_values(array_filter($pandIds));
  sort($pandIds);
  assertSameValue(['PAND-A', 'PAND-B', 'PAND-C'], $pandIds, 'One dossier range may span multiple BAG pand identities.');

  [$strict, $strictRelations] = enricher([
    [doc('Dam', '2', '1012JS', 'Amsterdam', 'PAND-WRONG', 'VBO-WRONG', 'NA-WRONG')],
  ]);
  $strictResult = $strict->enrich(building('Dam 1', '1012JS', 'Amsterdam'));
  assertSameValue('not_found', $strictResult['state'], 'Fuzzy first result must never be silently linked.');
  assertTrueValue($strictRelations->addresses === [] && $strictRelations->identities === [], 'A failed exact match must persist nothing.');

  echo "PDOK_BUILDING_SCOPE_CONTRACT=PASS\n";
}
