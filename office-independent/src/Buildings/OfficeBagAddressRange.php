<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use InvalidArgumentException;

/**
 * Pure PDOK/BAG search-result selection. Never synthesize missing house numbers:
 * each proposed Office address must originate from a returned BAG record.
 */
final class OfficeBagAddressRange {
  public static function parse(string $from, string $to): array {
    if (!preg_match('/^[1-9][0-9]{0,4}$/D', trim($from))
      || !preg_match('/^[1-9][0-9]{0,4}$/D', trim($to))) {
      throw new InvalidArgumentException('invalid_house_number_range');
    }
    $first=(int)$from; $last=(int)$to;
    if ($last<$first || $last-$first>499) {
      throw new InvalidArgumentException('house_number_range_too_large_or_reversed');
    }
    return [$first,$last];
  }

  /**
   * @param array<int,array<string,mixed>> $documents PDOK Locatieserver docs.
   * @return array<int,array<string,mixed>> Verified unique addresses in range.
   */
  public static function select(array $documents, int $first, int $last): array {
    if ($first<1 || $last<$first || $last-$first>499) {
      throw new InvalidArgumentException('invalid_house_number_range');
    }
    $result=[];
    foreach ($documents as $doc) {
      if (!is_array($doc) || !in_array((string)($doc['type']??''), ['adres','adresseerbaarobject'], true)) continue;
      $number=filter_var($doc['huisnummer']??null,FILTER_VALIDATE_INT);
      if (!is_int($number) || $number<$first || $number>$last) continue;
      $id=trim((string)($doc['nummeraanduiding_id']??$doc['adresseerbaarobject_id']??''));
      $street=trim((string)($doc['straatnaam']??''));
      $city=trim((string)($doc['woonplaatsnaam']??''));
      if (!preg_match('/^[a-zA-Z0-9._:-]{1,128}$/D',$id) || $street==='' || $city==='') continue;
      $postal=trim((string)($doc['postcode']??''));
      $letter=trim((string)($doc['huisletter']??''));
      $addition=trim((string)($doc['huisnummertoevoeging']??''));
      $key=$id;
      $result[$key]=[
        'bag_address_id'=>$id,'street'=>$street,'house_number'=>$number,
        'house_letter'=>$letter,'house_addition'=>$addition,'postal_code'=>$postal,
        'city'=>$city,'primary_floor'=>null,'dwelling_type_id'=>null,
      ];
    }
    $values=array_values($result);
    usort($values,static fn(array $a,array $b):int=>[$a['street'],$a['house_number'],$a['house_letter'],$a['house_addition']] <=> [$b['street'],$b['house_number'],$b['house_letter'],$b['house_addition']]);
    return $values;
  }
}
