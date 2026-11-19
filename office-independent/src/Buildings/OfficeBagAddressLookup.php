<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

require_once __DIR__.'/OfficeBagAddressRange.php';

/** Read-only official BAG search, returns only actual source records. */
final class OfficeBagAddressLookup {
  public static function search(string $street,string $city,int $from,int $to): array {
    if ($from<1 || $to<$from || $to-$from>499) throw new \InvalidArgumentException('invalid_range');
    foreach ([$street,$city] as $term) {
      if (mb_strlen($term)<2 || mb_strlen($term)>120) throw new \InvalidArgumentException('invalid_location');
    }
    $url='https://api.pdok.nl/bzk/locatieserver/search/v3_1/free?'.http_build_query([
      'q'=>$street.' '.$city,'rows'=>500,'fq'=>'bron:BAG',
      'fl'=>'type,nummeraanduiding_id,adresseerbaarobject_id,straatnaam,huisnummer,huisletter,huisnummertoevoeging,postcode,woonplaatsnaam'
    ]);
    $context=stream_context_create(['http'=>[
      'timeout'=>8,'header'=>"Accept: application/json\r\nUser-Agent: BREBO-Office/1.0\r\n"
    ]]);
    $response=@file_get_contents($url,false,$context);
    if ($response===false || strlen($response)>1500000) throw new \RuntimeException('pdok_unavailable');
    $data=json_decode($response,true,64,JSON_THROW_ON_ERROR);
    $docs=$data['response']['docs']??null;
    if (!is_array($docs)) throw new \RuntimeException('invalid_pdok_response');
    $matches=OfficeBagAddressRange::select($docs,$from,$to);
    return array_values(array_filter($matches,static fn(array $row):bool=>
      mb_strtolower($row['street'])===mb_strtolower($street)
      && mb_strtolower($row['city'])===mb_strtolower($city)
    ));
  }
}
