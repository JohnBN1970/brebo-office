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
    public int $clearCalls = 0;

    public function clearSourceRelations(int $buildingNid, string $source): array {
      $this->clearCalls++;
      $addressCount = count($this->addresses);
      $identityCount = count($this->identities);
      $this->addresses = [];
      $this->identities = [];
      return ['addresses' => $addressCount, 'identities' => $identityCount];
    }

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

  $dam1 = doc('Dam', '1', '1012JS', 'Amsterdam', 'PAND-1', 'VBO-1', 'NA-1');
  $dam2 = doc('Dam', '2', '1012JS', 'Amsterdam', 'PAND-1', 'VBO-2', 'NA-2');
  [$single, $singleRelations] = enricher([
    [$dam1],
    [$dam1],
    [$dam1, $dam2],
    [$dam1],
    [$dam2],
  ]);
  $singleResult = $single->enrich(building('Dam 1', '1012JS', 'Amsterdam'));
  assertSameValue('enriched', $singleResult['state'], 'Single-address enrichment must succeed.');
  assertSameValue(2, $singleResult['address_count'], 'Single-address enrichment must expand all addresses in the matched pand.');
  assertSameValue(2, count($singleRelations->addresses), 'Two pand addresses must be stored.');

  $plein = doc('Plein 1944', '1', '6511JC', 'Nijmegen', 'PAND-1944', 'VBO-1944', 'NA-1944');
  [$numbered] = enricher([
    [$plein],
    [$plein],
    [$plein],
    [$plein],
  ]);
  $numberedResult = $numbered->enrich(building('Plein 1944 1', '6511JC', 'Nijmegen'));
  assertSameValue('PAND-1944', $numberedResult['pand_id'], 'Trailing house number must not consume numeric street-name tokens.');

  $b87 = doc('Bilderdijkstraat', '87', '1053KM', 'Amsterdam', 'PAND-87', 'VBO-87', 'NA-87');
  $b89 = doc('Bilderdijkstraat', '89', '1053KM', 'Amsterdam', 'PAND-89', 'VBO-89', 'NA-89');
  $b91 = doc('Bilderdijkstraat', '91', '1053KN', 'Amsterdam', 'PAND-91', 'VBO-91', 'NA-91');
  $b93 = doc('Bilderdijkstraat', '93', '1053KN', 'Amsterdam', 'PAND-93', 'VBO-93', 'NA-93');
  $b95 = doc('Bilderdijkstraat', '95', '1053KP', 'Amsterdam', 'PAND-95', 'VBO-95', 'NA-95');
  $b97 = doc('Bilderdijkstraat', '97', '1053KP', 'Amsterdam', 'PAND-97', 'VBO-97', 'NA-97');
  [$range, $rangeRelations] = enricher([
    [$b87], [$b87],
    [$b89], [$b89],
    [$b91], [$b91],
    [$b93], [$b93],
    [$b95], [$b95],
    [$b97], [$b97],
  ]);
  $rangeRelations->addresses[] = ['huisnummer' => '871', 'source' => 'PDOK BAG'];
  $rangeRelations->identities[] = ['type' => 'pand', 'bag_id' => 'STALE-PAND', 'source' => 'PDOK BAG'];
  $rangeResult = $range->enrich(building('Bilderdijkstraat 87 t/m 97', '1053KM', 'Amsterdam'));
  assertSameValue('enriched', $rangeResult['state'], 'Explicit dossier range must enrich without first requiring one primary pand.');
  assertSameValue('PAND-87', $rangeResult['pand_id'], 'Range start address may provide the primary map anchor without constraining the dossier to one pand.');
  assertSameValue(6, $rangeResult['address_count'], 'Odd-to-odd range must include the odd sequence only.');
  assertSameValue(1, $rangeRelations->clearCalls, 'Successful refresh must replace the previous PDOK snapshot exactly once.');
  assertSameValue(['87', '89', '91', '93', '95', '97'], array_column($rangeRelations->addresses, 'huisnummer'), '87 t/m 97 must preserve odd-number parity and remove stale prior PDOK addresses.');
  $pandIds = array_values(array_unique(array_map(
    static fn(array $identity): string => $identity['type'] === 'pand' ? $identity['bag_id'] : '',
    $rangeRelations->identities,
  )));
  $pandIds = array_values(array_filter($pandIds));
  sort($pandIds);
  assertSameValue(['PAND-87', 'PAND-89', 'PAND-91', 'PAND-93', 'PAND-95', 'PAND-97'], $pandIds, 'One dossier range may span multiple BAG pand identities without retaining stale identities.');

  [$strict, $strictRelations] = enricher([
    [doc('Dam', '2', '1012JS', 'Amsterdam', 'PAND-WRONG', 'VBO-WRONG', 'NA-WRONG')],
  ]);
  $strictResult = $strict->enrich(building('Dam 1', '1012JS', 'Amsterdam'));
  assertSameValue('not_found', $strictResult['state'], 'Fuzzy first result must never be silently linked.');
  assertTrueValue($strictRelations->addresses === [] && $strictRelations->identities === [], 'A failed exact match must persist nothing.');
  assertSameValue(0, $strictRelations->clearCalls, 'Failed PDOK resolution must preserve the existing snapshot.');

  echo "PDOK_BUILDING_SCOPE_CONTRACT=PASS\n";
}
