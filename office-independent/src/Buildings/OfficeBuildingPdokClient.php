<?php
declare(strict_types=1);
namespace Brebo\Office\Buildings;

final class OfficeBuildingPdokClient {
  public function __construct(private ?\Closure $transport = null) {}

  private function request(string $endpoint, array $parameters): array {
    $url = 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/' . $endpoint . '?' . http_build_query($parameters);
    if ($this->transport !== null) return ($this->transport)($url);
    $context = stream_context_create(['http' => [
      'timeout' => 5, 'follow_location' => 0,
      'header' => "Accept: application/json\r\nUser-Agent: BREBO-Office/1.0\r\n",
    ]]);
    $json = @file_get_contents($url, false, $context, 0, 200001);
    if ($json === false || strlen($json) > 200000) throw new \RuntimeException('pdok_unavailable');
    return json_decode($json, true, 64, JSON_THROW_ON_ERROR);
  }

  /** Strictly an individual BAG address, not a street, city or parcel. */
  public static function location(array $doc): array {
    foreach (['id','bron','type','nummeraanduiding_id','straatnaam','huis_nlt','postcode','woonplaatsnaam','centroide_ll','weergavenaam'] as $key) {
      if (!isset($doc[$key]) || !is_string($doc[$key])) throw new \UnexpectedValueException('invalid_pdok_address');
    }
    if ($doc['bron'] !== 'BAG' || $doc['type'] !== 'adres'
      || !preg_match('/^adr-[a-zA-Z0-9-]{1,100}$/D', $doc['id'])
      || !preg_match('/^[0-9]{16}$/D', $doc['nummeraanduiding_id'])
      || !preg_match('/^[1-9][0-9]{3}[A-Z]{2}$/D', $doc['postcode'])
      || !preg_match('/^POINT\s*\(\s*(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s*\)$/iD', $doc['centroide_ll'], $m)) {
      throw new \UnexpectedValueException('invalid_pdok_address');
    }
    $address = trim($doc['straatnaam'] . ' ' . $doc['huis_nlt']);
    $lat = (float)$m[2]; $lon = (float)$m[1];
    if (trim($doc['straatnaam']) === '' || trim($doc['huis_nlt']) === ''
      || strlen($address) > 255 || trim($doc['woonplaatsnaam']) === '' || strlen($doc['woonplaatsnaam']) > 160
      || !is_finite($lat) || !is_finite($lon) || $lat < 50 || $lat > 54 || $lon < 3 || $lon > 8) {
      throw new \UnexpectedValueException('invalid_pdok_address');
    }
    return ['id' => $doc['id'], 'bag_id' => $doc['nummeraanduiding_id'], 'address' => $address,
      'postal_code' => $doc['postcode'], 'city' => $doc['woonplaatsnaam'],
      'label' => $doc['weergavenaam'], 'lat' => $lat, 'lon' => $lon];
  }

  public function search(string $query): array {
    if (strlen(trim($query)) < 3 || strlen($query) > 160) throw new \InvalidArgumentException('invalid_pdok_query');
    $response = $this->request('free', ['q' => $query, 'rows' => 10, 'fq' => 'type:adres AND bron:BAG']);
    $result = [];
    foreach (($response['response']['docs'] ?? []) as $doc) {
      try { if (is_array($doc)) $result[] = self::location($doc); }
      catch (\UnexpectedValueException) { /* Omit incomplete or non-address results. */ }
    }
    return $result;
  }

  public function lookup(string $id): array {
    if (!preg_match('/^adr-[a-zA-Z0-9-]{1,100}$/D', $id)) throw new \InvalidArgumentException('invalid_pdok_id');
    $response = $this->request('lookup', ['id' => $id]);
    $docs = $response['response']['docs'] ?? [];
    if (!is_array($docs) || count($docs) !== 1 || !is_array($docs[0]) || ($docs[0]['id'] ?? null) !== $id) {
      throw new \UnexpectedValueException('pdok_result_mismatch');
    }
    return self::location($docs[0]);
  }
}
