<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

/** Defines minimum site evidence per construction activity. */
final class PerformanceEvidenceRequirementResolver {
  /** @return array{activity:string,requirements:list<array{code:string,label:string}>} */
  public function resolve(string $description): array {
    $text=mb_strtolower($description);
    $catalog=[
      'glass'=>['needles'=>['glas','beglaz','ruit'],'requirements'=>[['code'=>'before','label'=>'Situatie vóór uitvoering'],['code'=>'product','label'=>'Product-/glasidentificatie'],['code'=>'installation','label'=>'Plaatsing en aansluiting'],['code'=>'after','label'=>'Eindresultaat']]],
      'painting'=>['needles'=>['schilder','coating'],'requirements'=>[['code'=>'before','label'=>'Ondergrond vóór behandeling'],['code'=>'preparation','label'=>'Voorbehandeling/herstel'],['code'=>'product','label'=>'Productbewijs'],['code'=>'after','label'=>'Eindresultaat']]],
      'etics'=>['needles'=>['etics','gevelisol','isolatie'],'requirements'=>[['code'=>'substrate','label'=>'Ondergrond'],['code'=>'insulation','label'=>'Isolatie en bevestiging'],['code'=>'reinforcement','label'=>'Wapeningslaag/details'],['code'=>'after','label'=>'Afgewerkte gevel']]],
      'concrete'=>['needles'=>['beton','wapening'],'requirements'=>[['code'=>'damage','label'=>'Schade vóór herstel'],['code'=>'preparation','label'=>'Vrijgehakt/gereinigd herstelvlak'],['code'=>'reinforcement','label'=>'Wapening/behandeling'],['code'=>'after','label'=>'Eindresultaat']]],
      'roof'=>['needles'=>['dak','bitumen','epdm'],'requirements'=>[['code'=>'before','label'=>'Bestaande situatie'],['code'=>'substrate','label'=>'Ondergrond'],['code'=>'details','label'=>'Aansluitingen en details'],['code'=>'after','label'=>'Eindresultaat']]],
    ];
    foreach($catalog as $activity=>$set){foreach($set['needles'] as $needle){if(str_contains($text,$needle))return['activity'=>$activity,'requirements'=>$set['requirements']];}}
    return ['activity'=>'general','requirements'=>[['code'=>'before','label'=>'Situatie vóór uitvoering'],['code'=>'during','label'=>'Uitvoering / relevant detail'],['code'=>'after','label'=>'Eindresultaat']]];
  }
}
